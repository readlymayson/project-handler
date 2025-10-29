<?php

require_once 'config.php';

class DealCreator
{
    private $call;
    private $logger;
    private array $userRoleCache = []; // Кэш для ролей пользователей
    private ?DocumentGenerator $documentGenerator = null; // Генератор документов (устаревший)
    private ?ExternalDocumentGenerator $externalDocumentGenerator = null; // Внешний генератор документов

    public function __construct($call, $logger, DocumentGenerator $documentGenerator = null, ExternalDocumentGenerator $externalDocumentGenerator = null)
    {
        $this->call = $call;
        $this->logger = $logger;
        $this->documentGenerator = $documentGenerator;
        $this->externalDocumentGenerator = $externalDocumentGenerator;
    }

    /**
     * Извлекает ID проекта из поля UF_CRM_PROJECT_LINK
     * Если это ссылка - извлекает ID, если число - возвращает как есть
     */
    public function extractProjectId($projectLink): int
    {
        if (empty($projectLink)) {
            return 0;
        }

        // Если это число - возвращаем как есть
        if (is_numeric($projectLink)) {
            return (int)$projectLink;
        }

        // Если это строка, пытаемся извлечь ID из ссылки
        if (is_string($projectLink)) {
            // Паттерны для извлечения ID из различных форматов ссылок
            $patterns = [
                '/\/workgroups\/group\/(\d+)/',  // /workgroups/group/123/
                '/\/group\/(\d+)/',              // /group/123/
                '/group_id=(\d+)/',              // group_id=123
                '/id=(\d+)/',                    // id=123
                '/(\d+)/'                        // просто число в строке
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $projectLink, $matches)) {
                    return (int)$matches[1];
                }
            }
        }

        // Если не удалось извлечь ID
        $this->logger->log("Не удалось извлечь ID проекта из: " . $projectLink);
        return 0;
    }

    /**
     * Проверяет, существует ли уже сделка с таким названием для данной компании в текущем месяце
     * 
     * @param string $dealTitle Название сделки (название проекта)
     * @param int $companyId ID компании
     * @return bool true если найден дубликат, false если дубликатов нет
     */
    private function checkDuplicateDeal($dealTitle, $companyId): bool
    {
        if (!CHECK_DUPLICATES) {
            return false;
        }

        if (empty($dealTitle)) {
            $this->logger->log([
                'type' => 'duplicate_check',
                'status' => 'error',
                'message' => 'Название сделки не указано для проверки дубликатов'
            ]);
            return false;
        }

        try {
            $firstDayOfMonth = new DateTime('first day of this month');
            $lastDayOfMonth = new DateTime('last day of this month');

            // Формируем фильтр: проверяем по названию сделки И по компании
            $filter = [
                'CATEGORY_ID' => MONTHLY_WORK_FUNNEL_ID, // Проверяем только в воронке "Работы за предыдущий месяц"
                'TITLE' => $dealTitle, // Точное совпадение названия
                '>=BEGINDATE' => $firstDayOfMonth->format('Y-m-d'),
                '<=BEGINDATE' => $lastDayOfMonth->format('Y-m-d')
            ];
            
            // Если указан ID компании, добавляем фильтр по компании
            if (!empty($companyId) && $companyId > 0) {
                $filter['COMPANY_ID'] = $companyId;
            }
            
            apiDelay();
            
            $result = $this->call->callBitrix24API('crm.deal.list', [
                'filter' => $filter,
                'select' => ['ID', 'TITLE', 'BEGINDATE', 'CATEGORY_ID', 'COMPANY_ID']
            ]);

            $deals = $result['result'] ?? [];
            $hasDuplicates = !empty($deals);
            
            if ($hasDuplicates) {
                $this->logger->log([
                    'type' => 'duplicate_check',
                    'status' => 'found',
                    'deal_title' => $dealTitle,
                    'company_id' => $companyId,
                    'funnel_id' => MONTHLY_WORK_FUNNEL_ID,
                    'duplicates_count' => count($deals),
                    'duplicates' => $deals,
                    'message' => "Найдены дубликаты сделок с названием '$dealTitle' для компании $companyId в воронке " . MONTHLY_WORK_FUNNEL_ID
                ]);
            } else {
                $this->logger->log([
                    'type' => 'duplicate_check',
                    'status' => 'no_duplicates',
                    'deal_title' => $dealTitle,
                    'company_id' => $companyId,
                    'funnel_id' => MONTHLY_WORK_FUNNEL_ID,
                    'message' => "Дубликаты не найдены для сделки '$dealTitle' компании $companyId"
                ]);
            }
            
            return $hasDuplicates;

        } catch (Exception $e) {
            $this->logger->log([
                'type' => 'duplicate_check',
                'status' => 'error',
                'deal_title' => $dealTitle,
                'company_id' => $companyId,
                'funnel_id' => MONTHLY_WORK_FUNNEL_ID,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Создает сделку в воронке "Работы за предыдущий месяц"
     * 
     * @param array $company Данные исходной компании
     * @param array $projectTimeData Данные о времени по проекту
     * @return array Результат создания сделки
     */
    public function createMonthlyWorkDeal($company, $projectTimeData): array
    {
        try {
            $projectLink = $company['UF_CRM_PROJECT_LINK'] ?? '';
            $projectId = $this->extractProjectId($projectLink);
            $originalCompanyId = $company['ID'];
            
            if ($projectId === 0) {
                throw new Exception("У компании $originalCompanyId не указана ссылка на проект или не удалось извлечь ID проекта");
            }

            // Получаем информацию о проекте
            $projectInfo = $this->getProjectInfo($projectId);
            if (empty($projectInfo)) {
                throw new Exception("Не удалось получить информацию о проекте $projectId");
            }

            // Получаем информацию о клиентах и компании
            $clientInfo = $this->getClientInfo($originalCompanyId);
            
            $dealTitle = $projectInfo['NAME'] ?? '';
            
            // Проверяем на дубликаты по названию сделки и компании
            if ($this->checkDuplicateDeal($dealTitle, $originalCompanyId)) {
                return [
                    'status' => 'duplicate',
                    'message' => "Сделка с названием '$dealTitle' для компании $originalCompanyId уже существует в текущем месяце"
                ];
            }
            
            // Формируем данные для новой сделки
            $dealData = $this->prepareDealData($company, $projectInfo, $clientInfo, $projectTimeData);
            
            // Создаем сделку
            $result = $this->call->callBitrix24API('crm.deal.add', [
                'fields' => $dealData
            ]);

            if (empty($result['result'])) {
                throw new Exception("Ошибка при создании сделки: " . json_encode($result));
            }

            $newDealId = $result['result'];
            
            // Генерируем документы через внешние библиотеки (Excel, CSV, PDF)
            if ($this->externalDocumentGenerator !== null && ENABLE_EXTERNAL_DOCUMENT_GENERATOR) {
                $documentsResult = $this->externalDocumentGenerator->generateDocumentsForDeal(
                    $newDealId,
                    $company,
                    $projectTimeData,
                    $projectId
                );
                
                if ($documentsResult['status'] === 'success') {
                    $this->logger->log([
                        'type' => 'external_documents_generation',
                        'status' => 'success',
                        'deal_id' => $newDealId,
                        'documents' => array_keys($documentsResult['documents'] ?? []),
                        'message' => 'Документы успешно сгенерированы через внешние библиотеки'
                    ]);
                    
                    // Добавляем ссылки на Excel и CSV файлы в комментарий таймлайна
                    $this->addDocumentsLinksToTimeline($newDealId, $documentsResult['documents'] ?? []);
                } else {
                    $this->logger->log([
                        'type' => 'external_documents_generation',
                        'status' => 'error',
                        'deal_id' => $newDealId,
                        'message' => $documentsResult['message'] ?? 'Неизвестная ошибка при генерации внешних документов'
                    ]);
                }
            }
            
            // Дополнительно генерируем документы через Bitrix24 Document Generator (PDF)
            if ($this->documentGenerator !== null && ENABLE_DOCUMENT_GENERATOR) {
                $documentsResult = $this->documentGenerator->generateDocumentsForDeal(
                    $newDealId,
                    $company,
                    $projectTimeData,
                    $projectId
                );
                
                if ($documentsResult['status'] === 'success') {
                    $this->logger->log([
                        'type' => 'documents_generation',
                        'status' => 'success',
                        'deal_id' => $newDealId,
                        'documents' => array_keys($documentsResult['documents'] ?? []),
                        'message' => 'Документы успешно сгенерированы через Bitrix24 Document Generator'
                    ]);
                } else {
                    $this->logger->log([
                        'type' => 'documents_generation',
                        'status' => 'error',
                        'deal_id' => $newDealId,
                        'message' => $documentsResult['message'] ?? 'Неизвестная ошибка при генерации документов'
                    ]);
                }
            }
            
            // Отправляем уведомления
            if (NOTIFY_ON_DEAL_CREATION) {
                $this->sendDealCreationNotification($newDealId, $projectInfo['NAME'], $projectTimeData);
            }
            
            // Создаем задачу для обработки проекта
            $taskResult = null;
            if (CREATE_TASK_ON_DEAL_CREATION) {
                $taskResult = $this->createTaskForDeal($newDealId, $projectId, $projectInfo['NAME'], $dealData['CLOSEDATE'], $dealData['ASSIGNED_BY_ID']);
                if ($taskResult && $taskResult['status'] === 'success') {
                    $this->logger->log([
                        'type' => 'task_creation',
                        'status' => 'success',
                        'deal_id' => $newDealId,
                        'task_id' => $taskResult['task_id'],
                        'project_id' => $projectId,
                        'deadline' => $dealData['CLOSEDATE'],
                        'message' => 'Задача успешно создана для сделки'
                    ]);
                }
            }
            
            $this->logger->log([
                'type' => 'deal_creation',
                'status' => 'success',
                'original_company_id' => $originalCompanyId,
                'new_deal_id' => $newDealId,
                'project_id' => $projectId,
                'project_name' => $projectInfo['NAME'],
                'funnel_id' => MONTHLY_WORK_FUNNEL_ID,
                'total_hours' => $projectTimeData['total_hours'],
                'total_cost' => $projectTimeData['total_cost'],
                'assigned_by_id' => $company['ASSIGNED_BY_ID'] ?? 1,
                'task_id' => $taskResult['task_id'] ?? null,
                'message' => 'Сделка "Работы за предыдущий месяц" успешно создана в воронке ' . MONTHLY_WORK_FUNNEL_ID . ', ответственный: ' . ($company['ASSIGNED_BY_ID'] ?? 1)
            ]);


            return [
                'status' => 'success',
                'deal_id' => $newDealId,
                'task_id' => $taskResult['task_id'] ?? null,
                'message' => 'Сделка успешно создана'
            ];

        } catch (Exception $e) {
            $this->logger->log([
                'type' => 'deal_creation',
                'status' => 'error',
                'original_company_id' => $company['ID'] ?? 0,
                'message' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Получает информацию о проекте
     */
    private function getProjectInfo($projectId): array
    {
        // Добавляем задержку перед API вызовом
        apiDelay();
        
        $result = $this->call->callBitrix24API('sonet_group.get', [
            'FILTER' => ['ID' => $projectId]
        ]);

        return $result['result'][0] ?? [];
    }

    /**
     * Получает информацию о клиентах и компании из исходной компании
     */
    public function getClientInfo($companyId): array
    {
        // Добавляем задержку перед API вызовом
        apiDelay();
        
        $result = $this->call->callBitrix24API('crm.company.get', [
            'id' => $companyId
        ]);

        $company = $result['result'] ?? [];
        
        return [
            'contact_id' => null, // У компаний нет контактов напрямую
            'company_id' => $company['ID'] ?? null,
            'title' => $company['TITLE'] ?? '',
            'default_price' => $company['UF_CRM_DEFAULT_RATE'] ?? DEFAULT_HOURLY_RATE
        ];
    }

    /**
     * Получает первого проект-менеджера из отдела
     */
    private function getProjectManager(): int
    {
        // Добавляем задержку перед API вызовом
        apiDelay();
        
        // Получаем пользователей отдела проект-менеджеры
        $result = $this->call->callBitrix24API('department.get', []);
        $departments = $result['result'] ?? [];
        
        $projectManagerDeptId = null;
        foreach ($departments as $dept) {
            if (stripos($dept['NAME'], 'проект') !== false && 
                stripos($dept['NAME'], 'менеджер') !== false) {
                $projectManagerDeptId = $dept['ID'];
                break;
            }
        }

        if ($projectManagerDeptId) {
            // Добавляем задержку перед вторым API вызовом
            apiDelay();
            
            $usersResult = $this->call->callBitrix24API('user.get', [
                'filter' => ['UF_DEPARTMENT' => $projectManagerDeptId, 'ACTIVE' => 'Y']
            ]);
            
            $users = $usersResult['result'] ?? [];
            if (!empty($users)) {
                return (int)$users[0]['ID'];
            }
        }

        // Если не найден проект-менеджер, возвращаем ID 1 (администратор)
        return 1;
    }

    /**
     * Подготавливает данные для создания сделки
     */
    private function prepareDealData($company, $projectInfo, $clientInfo, $projectTimeData): array
    {
        $currentDate = new DateTime();
        $firstDayOfMonth = new DateTime('first day of this month');
        
        // Дата завершения - до 6 числа текущего месяца
        $closeDate = clone $firstDayOfMonth;
        $closeDate->modify('+' . CLOSE_DATE_OFFSET_DAYS . ' days');
        
        // Дата начала - первое число, если не выходной
        $startDate = clone $firstDayOfMonth;
        if (WORK_DAYS_ONLY) {
            $weekday = $startDate->format('N');
            if ($weekday >= 6) { // Если суббота или воскресенье
                $startDate->modify('next monday');
            }
        }

        // Формируем комментарий с разбивкой по ролям и детальным отчетом по задачам
        $projectId = $this->extractProjectId($company['UF_CRM_PROJECT_LINK']);
        $comment = $this->formatTimeComment($projectTimeData['roles_time'], $projectId);

        // Получаем ответственного из исходной компании, если не задан - используем администратора
        $assignedById = $company['ASSIGNED_BY_ID'] ?? 1;
        if (empty($assignedById) || $assignedById <= 0) {
            $assignedById = 1; // Администратор по умолчанию
        }

        return [
            'TITLE' => $projectInfo['NAME'], // Название проекта
            'CATEGORY_ID' => MONTHLY_WORK_FUNNEL_ID, // Воронка "Работы за предыдущий месяц"
            'STAGE_ID' => FUNNEL_STAGES['NEW'], // Первая стадия "проекты"
            'OPPORTUNITY' => $projectTimeData['total_cost'], // Сумма
            'CLOSEDATE' => $closeDate->format('Y-m-d'), // Дата завершения
            'BEGINDATE' => $startDate->format('Y-m-d'), // Дата начала
            'CONTACT_ID' => $clientInfo['contact_id'], // Клиент
            'COMPANY_ID' => $clientInfo['company_id'], // Компания
            'ASSIGNED_BY_ID' => $assignedById, // Ответственный из исходной компании
            'COMMENTS' => $comment, // Комментарий с разбивкой времени
            'UF_CRM_PROJECT_LINK' => $company['UF_CRM_PROJECT_LINK'], // Ссылка на проект
            'UF_CRM_ORIGINAL_COMPANY' => $company['ID'] // Ссылка на исходную компанию
        ];
    }

    /**
     * Форматирует комментарий с разбивкой времени по исполнителям
     */
    private function formatTimeComment($rolesTime, $projectId = null): string
    {
        $comment = "Учет времени по проекту:\n\n";
        
        // Сводка по исполнителям (ролям)
        $comment .= "СВОДКА ПО ИСПОЛНИТЕЛЯМ:\n";
        $comment .= str_repeat("-", 30) . "\n";
        
        $totalHours = 0;
        $totalCost = 0;
        
        foreach ($rolesTime as $role => $timeData) {
            $hours = $timeData['hours'];
            $minutes = $timeData['minutes'];
            $decimalHours = $timeData['decimal_hours'];
            
            // Рассчитываем стоимость для этой роли
            $rate = getRoleRate($role);
            $cost = $decimalHours * $rate;
            $totalCost += $cost;
            $totalHours += $decimalHours;
            
            $comment .= "$role:\n";
            $comment .= "  Время: {$hours} ч {$minutes} м ({$decimalHours} ч)\n";
            $comment .= "  Ставка: {$rate} руб/час\n";
            $comment .= "  Стоимость: " . number_format($cost, 2, ',', ' ') . " руб\n\n";
        }
        
        // Итоговая информация
        $comment .= str_repeat("=", 40) . "\n";
        $comment .= "Общая стоимость по проекту: " . number_format($totalCost, 2, ',', ' ') . " руб\n";
        
        return $comment;
    }

    /**
     * Генерирует детальный отчет по задачам с разбивкой по ролям и времени
     * @deprecated Метод больше не используется - отчет теперь группируется по исполнителям
     */
    private function generateDetailedTaskReport($projectId): string
    {
        // Метод оставлен для обратной совместимости, но больше не используется
        return "Детальный отчет по задачам отключен. Используется группировка по исполнителям.\n\n";
    }

    /**
     * Получает данные о времени по проекту с разбивкой по ролям
     */
    public function getProjectTimeData($projectId, $companyData = null): array
    {
        try {
            $tasks = $this->getProjectTasks($projectId);
            if (empty($tasks)) {
                return [
                    'total_hours' => 0,
                    'total_cost' => 0,
                    'roles_time' => []
                ];
            }

            $rolesTime = [];
            $totalHours = 0;
            $globalUserRoles = []; // Глобальное отслеживание ролей пользователей
            $globalRoleCounters = []; // Глобальные счетчики для одинаковых ролей

            foreach ($tasks as $task) {
                $taskTimeData = $this->getTaskTimeByRolesWithGlobalNumbering($task['id'], $globalUserRoles, $globalRoleCounters);
                
                foreach ($taskTimeData as $role => $timeData) {
                    if (!isset($rolesTime[$role])) {
                        $rolesTime[$role] = [
                            'hours' => 0,
                            'minutes' => 0,
                            'decimal_hours' => 0
                        ];
                    }
                    
                    $rolesTime[$role]['decimal_hours'] += $timeData['decimal_hours'];
                    $totalHours += $timeData['decimal_hours'];
                }
            }

            // Конвертируем десятичные часы в часы и минуты
            foreach ($rolesTime as $role => &$timeData) {
                $decimalHours = $timeData['decimal_hours'];
                
                // Округляем до часа, если меньше часа
                if ($decimalHours < MIN_HOUR_ROUNDING && $decimalHours > ROUNDING_THRESHOLD) {
                    $decimalHours = MIN_HOUR_ROUNDING;
                }
                
                $timeData['decimal_hours'] = round($decimalHours, 2);
                $timeData['hours'] = floor($decimalHours);
                $timeData['minutes'] = round(($decimalHours - $timeData['hours']) * 60);
            }

            // Пересчитываем общее время с учетом округления
            $totalHours = array_sum(array_column($rolesTime, 'decimal_hours'));
            
            // Рассчитываем стоимость
            $totalCost = $this->calculateTotalCost($rolesTime, $companyData);

            return [
                'total_hours' => round($totalHours, 2),
                'total_cost' => $totalCost,
                'roles_time' => $rolesTime
            ];

        } catch (Exception $e) {
            $this->logger->log("Ошибка при получении данных о времени проекта $projectId: " . $e->getMessage());
            return [
                'total_hours' => 0,
                'total_cost' => 0,
                'roles_time' => []
            ];
        }
    }

    /**
     * Получает задачи проекта за предыдущий месяц
     */
    public function getProjectTasks($projectId): array
    {
        $method = 'tasks.task.list';
        $allTasks = [];
        $start = 0;

        $lastMonthFirstDay = (new DateTime('first day of last month'))
            ->setTime(0, 0, 0)
            ->format('Y-m-d\TH:i:sP');

        $lastMonthEnd = (new DateTime('last day of last month'))
            ->setTime(23, 59, 0)
            ->format('Y-m-d\TH:i:sP');

        $this->logger->log([
            'action' => 'get_project_tasks_start',
            'project_id' => $projectId,
            'last_month_end' => $lastMonthEnd,
            'last_month_first_day' => $lastMonthFirstDay
        ]);

        do {
            $paramsUnfinished = [
                'filter' => [
                    'GROUP_ID' => $projectId,
                    'CLOSED_DATE' => null,
                    '!REAL_STATUS' => 5
                ],
                'select' => ['ID', 'TITLE', 'TIME_ESTIMATE', 'TIME_SPENT_IN_LOGS', 'CLOSED_DATE'],
                'start' => $start
            ];

            $paramsFinished = [
                'filter' => [
                    'GROUP_ID' => $projectId,
                    '>CLOSED_DATE' => $lastMonthFirstDay,
                ],
                'select' => ['ID', 'TITLE', 'TIME_ESTIMATE', 'TIME_SPENT_IN_LOGS', 'CLOSED_DATE'],
                'start' => $start
            ];

            // Добавляем задержку между батчами (кроме первого)
            if ($start > 0) {
                batchDelay();
            }

            $this->logger->log([
                'action' => 'api_call_start',
                'method' => $method,
                'start' => $start
            ]);

            $responseUnfinished = $this->call->callBitrix24API($method, $paramsUnfinished);
            $tasksUnfinished = $responseUnfinished['result']['tasks'] ?? [];

            $responseFinished = $this->call->callBitrix24API($method, $paramsFinished);
            $tasksFinished = $responseFinished['result']['tasks'] ?? [];

            $this->logger->log([
                'action' => 'api_call_response',
                'response_unfinished' => $responseUnfinished,
                'response_finished' => $responseFinished,
                'has_error_unfinished' => isset($responseUnfinished['error']),
                'has_error_finished' => isset($responseFinished['error']),
                'error_code_unfinished' => $responseUnfinished['error'] ?? null,
                'error_code_finished' => $responseFinished['error'] ?? null
            ]);

            if (isset($responseUnfinished['error'])) {
                throw new Exception("API Error (unfinished): {$responseUnfinished['error']} - {$responseUnfinished['error_description']}");
            }

            if (isset($responseFinished['error'])) {
                throw new Exception("API Error (finished): {$responseFinished['error']} - {$responseFinished['error_description']}");
            }

            $tasks = array_merge($tasksUnfinished, $tasksFinished);
            $allTasks = array_merge($allTasks, $tasks);

            $this->logger->log([
                'action' => 'get_project_tasks_batch',
                'batch_number' => ($start / 50) + 1,
                'tasks_in_batch' => count($tasks),
                'unfinished_count' => count($tasksUnfinished),
                'finished_count' => count($tasksFinished),
                'total_tasks_so_far' => count($allTasks)
            ]);

            $start += 50;
        } while ((!empty($tasksUnfinished) || !empty($tasksFinished)) && count($tasks) >= 50);

        $this->logger->log([
            'action' => 'get_project_tasks_complete',
            'project_id' => $projectId,
            'total_tasks' => count($allTasks)
        ]);

        return $allTasks;
    }

    /**
     * Получает время по ролям для конкретной задачи с нумерацией одинаковых ролей
     */
    private function getTaskTimeByRoles($taskId): array
    {
        // Вся затрата времени в задаче учитывается в пользу исполнителя задачи
        // 1) Получаем суммарное время за прошлый месяц по задаче
        apiDelay();
        $response = $this->call->callBitrix24API('task.elapseditem.getlist', ['TASKID' => $taskId]);
        $elapsedItems = $response['result'] ?? [];

        $firstDay = (new DateTime("first day of last month"))->setTime(0, 0, 0);
        $lastDay = (new DateTime("last day of last month"))->setTime(23, 59, 59);

        $totalHours = 0;
        foreach ($elapsedItems as $item) {
            if (empty($item['CREATED_DATE'])) {
                continue;
            }
            $createdDate = new DateTime($item['CREATED_DATE']);
            if ($createdDate < $firstDay || $createdDate > $lastDay) {
                continue;
            }
            $seconds = (int)($item['SECONDS'] ?? 0);
            $hours = $seconds / 3600;
            if ($hours < MIN_HOUR_ROUNDING && $hours > ROUNDING_THRESHOLD) {
                $hours = MIN_HOUR_ROUNDING;
            }
            $totalHours += $hours;
        }

        if ($totalHours <= 0) {
            return [];
        }

        // 2) Получаем исполнителя задачи и его роль
        $responsibleId = $this->getTaskResponsibleId($taskId);
        $role = $this->getUserRole($responsibleId);

        return [
            $role => ['decimal_hours' => $totalHours]
        ];
    }

    /**
     * Получает время по ролям для конкретной задачи с глобальной нумерацией
     */
    private function getTaskTimeByRolesWithGlobalNumbering($taskId, &$globalUserRoles, &$globalRoleCounters): array
    {
        // Вся затрата времени в задаче учитывается в пользу исполнителя задачи (с глобальной нумерацией роли)
        apiDelay();
        $response = $this->call->callBitrix24API('task.elapseditem.getlist', ['TASKID' => $taskId]);
        $elapsedItems = $response['result'] ?? [];

        $firstDay = (new DateTime("first day of last month"))->setTime(0, 0, 0);
        $lastDay = (new DateTime("last day of last month"))->setTime(23, 59, 59);

        $totalHours = 0;
        foreach ($elapsedItems as $item) {
            if (empty($item['CREATED_DATE'])) {
                continue;
            }
            $createdDate = new DateTime($item['CREATED_DATE']);
            if ($createdDate < $firstDay || $createdDate > $lastDay) {
                continue;
            }
            $seconds = (int)($item['SECONDS'] ?? 0);
            $hours = $seconds / 3600;
            if ($hours < MIN_HOUR_ROUNDING && $hours > ROUNDING_THRESHOLD) {
                $hours = MIN_HOUR_ROUNDING;
            }
            $totalHours += $hours;
        }

        if ($totalHours <= 0) {
            return [];
        }

        // Получаем исполнителя задачи и маппим к глобально-нумерованной роли
        $responsibleId = $this->getTaskResponsibleId($taskId);
        $baseRole = $this->getUserRole($responsibleId);
        $uniqueRole = $this->getUniqueRoleForUser($responsibleId, $baseRole, $globalUserRoles, $globalRoleCounters);

        return [
            $uniqueRole => ['decimal_hours' => $totalHours]
        ];
    }

    /**
     * Получает ID ответственного (исполнителя) задачи
     */
    private function getTaskResponsibleId($taskId): int
    {
        try {
            apiDelay();
            $result = $this->call->callBitrix24API('tasks.task.get', [
                'taskId' => $taskId
            ]);
            $task = $result['result']['task'] ?? [];
            $responsibleId = (int)($task['responsibleId'] ?? $task['RESPONSIBLE_ID'] ?? 0);
            return $responsibleId ?: 0;
        } catch (Exception $e) {
            $this->logger->log("Ошибка получения RESPONSIBLE_ID для задачи $taskId: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Получает уникальную роль для пользователя с номером
     */
    private function getUniqueRoleForUser($userId, $baseRole, &$userRoles, &$roleCounters): string
    {
        // Если пользователь уже обработан, возвращаем его роль
        if (isset($userRoles[$userId])) {
            return $userRoles[$userId];
        }

        // Инициализируем счетчик для роли, если его еще нет
        if (!isset($roleCounters[$baseRole])) {
            $roleCounters[$baseRole] = 0;
        }

        // Увеличиваем счетчик
        $roleCounters[$baseRole]++;

        // Формируем уникальную роль
        $uniqueRole = $baseRole;
        if ($roleCounters[$baseRole] > 1) {
            $uniqueRole = $baseRole . ' #' . $roleCounters[$baseRole];
        }

        // Сохраняем роль для пользователя
        $userRoles[$userId] = $uniqueRole;

        return $uniqueRole;
    }

    /**
     * Получает роль пользователя на основе пользовательского поля UF_USR_1756216886115
     */
    private function getUserRole($userId): string
    {
        if ($userId == 0) {
            return 'Неизвестно';
        }

        // Проверяем кэш
        if (isset($this->userRoleCache[$userId])) {
            return $this->userRoleCache[$userId];
        }

        // Добавляем задержку перед API вызовом
        apiDelay();

        // Получаем информацию о пользователе с пользовательским полем
        $result = $this->call->callBitrix24API('user.get', [
            'filter' => ['ID' => $userId],
            'select' => ['ID', 'NAME', 'LAST_NAME', 'UF_USR_1756216886115']
        ]);

        $user = $result['result'][0] ?? [];
        $userRoleField = $user['UF_USR_1756216886115'] ?? '';
        
        // Определяем роль на основе пользовательского поля списка
        $role = $this->mapUserFieldToRole($userRoleField);
        
        // Кэшируем результат
        $this->userRoleCache[$userId] = $role;
        
        return $role;
    }

    /**
     * Маппинг должностей на роли
     */
    private function mapPositionToRole($position): string
    {
        if (empty($position)) {
            return 'Неизвестно';
        }

        $position = strtolower(trim($position));

        // Маппинг должностей на роли согласно UF_CRM полям
        $positionMappings = [
            'frontend' => 'Front-end разработчик',
            'front-end' => 'Front-end разработчик',
            'фронтенд' => 'Front-end разработчик',
            'фронт-енд' => 'Front-end разработчик',
            'frontend разработчик' => 'Front-end разработчик',
            'front-end разработчик' => 'Front-end разработчик',
            
            'backend' => 'Back-end разработчик',
            'back-end' => 'Back-end разработчик',
            'бэкенд' => 'Back-end разработчик',
            'бэк-енд' => 'Back-end разработчик',
            'backend разработчик' => 'Back-end разработчик',
            'back-end разработчик' => 'Back-end разработчик',
            
            'дизайнер' => 'Дизайнер',
            'designer' => 'Дизайнер',
            'веб-дизайнер' => 'Дизайнер',
            'web designer' => 'Дизайнер',
            'ui/ux дизайнер' => 'Дизайнер',
            'ui/ux designer' => 'Дизайнер',
            
            'проект-менеджер' => 'Проект-менеджер',
            'project manager' => 'Проект-менеджер',
            'пм' => 'Проект-менеджер',
            'pm' => 'Проект-менеджер',
            'менеджер проекта' => 'Проект-менеджер',
            
            'контент-менеджер' => 'Контент-менеджер',
            'content manager' => 'Контент-менеджер',
            'контент менеджер' => 'Контент-менеджер',
            'км' => 'Контент-менеджер',
            'cm' => 'Контент-менеджер'
        ];

        // Ищем точное совпадение
        if (isset($positionMappings[$position])) {
            return $positionMappings[$position];
        }

        // Ищем частичное совпадение
        foreach ($positionMappings as $key => $role) {
            if (strpos($position, $key) !== false) {
                return $role;
            }
        }

        // Если не найдено совпадение, возвращаем исходную должность
        return ucfirst($position);
    }

    /**
     * Маппинг пользовательского поля списка на роли
     * Основан на реальных данных из системы
     */
    private function mapUserFieldToRole($userFieldValue): string
    {
        if (empty($userFieldValue)) {
            return 'Неизвестно';
        }

        $userFieldValue = trim($userFieldValue);
        
        // Маппинг ID значений пользовательского поля на роли
        // Основан на анализе реальных должностей пользователей
        $userFieldMappings = [
            // ID: 142 - Front-end разработчик
            '142' => 'Front-end разработчик',
            
            // ID: 144 - Back-end разработчик  
            '144' => 'Back-end разработчик',
            
            // ID: 146 - Дизайнер
            '146' => 'Дизайнер',
            
            // ID: 148 - Контент-менеджер
            '148' => 'Контент-менеджер',
            
            // ID: 140 - Проект-менеджер
            '140' => 'Проект-менеджер',
            
            // ID: 150 - Директолог
            '150' => 'Директолог',
            
            // ID: 152 - SEO-специалист
            '152' => 'SEO-специалист',
            
            // ID: 154 - Юрист
            '154' => 'Юрист',
            
            // ID: 156 - Битрикс24 разработчик
            '156' => 'Битрикс24 разработчик'
        ];

        // Ищем точное совпадение по ID
        if (isset($userFieldMappings[$userFieldValue])) {
            return $userFieldMappings[$userFieldValue];
        }

        // Если значение не является ID, пробуем получить текстовое значение
        if (is_numeric($userFieldValue)) {
            $listValue = $this->getUserFieldListValue($userFieldValue);
            if ($listValue) {
                // Маппинг текстовых значений на роли
                $textMappings = [
                    'Front-end разработчик' => 'Front-end разработчик',
                    'Back-end разработчик' => 'Back-end разработчик',
                    'Дизайнер' => 'Дизайнер',
                    'Проект-менеджер' => 'Проект-менеджер',
                    'Контент-менеджер' => 'Контент-менеджер',
                    'Директолог' => 'Директолог',
                    'SEO-специалист' => 'SEO-специалист',
                    'Юрист' => 'Юрист',
                    'Битрикс24 разработчик' => 'Битрикс24 разработчик'
                ];
                
                if (isset($textMappings[$listValue])) {
                    return $textMappings[$listValue];
                }
            }
        }

        // Если не найдено совпадение, возвращаем исходное значение
        return $userFieldValue;
    }

    /**
     * Получает текстовое значение списка по ID
     */
    private function getUserFieldListValue($listId): ?string
    {
        try {
            // Получаем информацию о пользовательском поле
            $result = $this->call->callBitrix24API('userfield.get', [
                'filter' => ['FIELD_NAME' => 'UF_USR_1756216886115']
            ]);
            
            $userField = $result['result'][0] ?? [];
            $enumValues = $userField['ENUM'] ?? [];
            
            // Ищем значение по ID
            foreach ($enumValues as $enumValue) {
                if ($enumValue['ID'] == $listId) {
                    return $enumValue['VALUE'] ?? null;
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            $this->logger->log("Ошибка получения значения списка для ID $listId: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Рассчитывает общую стоимость работ
     */
    private function calculateTotalCost($rolesTime, $companyData = null): float
    {
        $totalCost = 0;
        
        foreach ($rolesTime as $role => $timeData) {
            $rate = getRoleRate($role, $companyData);
            $totalCost += $timeData['decimal_hours'] * $rate;
        }

        return round($totalCost, 2);
    }

    /**
     * Отправляет уведомления о создании сделки
     */
    private function sendDealCreationNotification($dealId, $projectName, $projectTimeData): void
    {
        try {
            $message = "Создана новая сделка 'Работы за предыдущий месяц'\n\n";
            $message .= "Проект: $projectName\n";
            $message .= "ID сделки: $dealId\n";
            $message .= "Общее время: {$projectTimeData['total_hours']} часов\n";
            $message .= "Общая стоимость: {$projectTimeData['total_cost']} руб.\n\n";
            $message .= "Ссылка на сделку: https://akvilon-marketing.bitrix24.ru/crm/deal/details/$dealId/";

            foreach (NOTIFY_USERS as $userId) {
                $this->call->callBitrix24API('im.notify', [
                    'to' => $userId,
                    'message' => $message,
                    'type' => 'SYSTEM'
                ]);
            }

        } catch (Exception $e) {
            $this->logger->log("Ошибка при отправке уведомления о создании сделки $dealId: " . $e->getMessage());
        }
    }

    /**
     * Создает задачу для обработки проекта после создания сделки
     * 
     * @param int $dealId ID созданной сделки
     * @param int $projectId ID проекта
     * @param string $projectName Название проекта
     * @param string $deadline Крайний срок (дата в формате Y-m-d)
     * @param int $responsibleId ID ответственного
     * @return array Результат создания задачи
     */
    private function createTaskForDeal($dealId, $projectId, $projectName, $deadline, $responsibleId): array
    {
        try {
            // Добавляем задержку перед API вызовом
            apiDelay();
            
            $taskTitle = "Обработать проект " . $projectName;
            $taskDescription = "Необходимо обработать проект после создания сделки \"Работы за предыдущий месяц\".\n\n"
                . "Ссылка на сделку: https://akvilon-marketing.bitrix24.ru/crm/deal/details/$dealId/\n"
                . "Проект: https://akvilon-marketing.bitrix24.ru/workgroups/group/$projectId/";
            
            $result = $this->call->callBitrix24API('tasks.task.add', [
                'fields' => [
                    'TITLE' => $taskTitle,
                    'DESCRIPTION' => $taskDescription,
                    'RESPONSIBLE_ID' => $responsibleId,
                    'CREATED_BY' => 1, // Администратор как создатель
                    'DEADLINE' => $deadline, // Крайний срок такой же, как у сделки
                    'UF_CRM_TASK' => ['D_' . $dealId], // Привязка к сделке
                ],
            ]);

            if (empty($result['result'])) {
                throw new Exception("Ошибка при создании задачи: " . json_encode($result));
            }

            return [
                'status' => 'success',
                'task_id' => $result['result']['task']['id'] ?? $result['result']['id'] ?? null,
                'message' => 'Задача успешно создана'
            ];

        } catch (Exception $e) {
            $this->logger->log([
                'type' => 'task_creation',
                'status' => 'error',
                'deal_id' => $dealId,
                'project_id' => $projectId,
                'message' => 'Ошибка при создании задачи: ' . $e->getMessage()
            ]);
            
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Добавляет ссылки на Excel и CSV файлы в комментарий таймлайна сделки
     * 
     * @param int $dealId ID сделки
     * @param array $documents Массив сгенерированных документов
     * @return void
     */
    private function addDocumentsLinksToTimeline($dealId, array $documents): void
    {
        try {
            $links = [];
            
            // Проверяем наличие Excel файла
            if (isset($documents['excel_report']) && !empty($documents['excel_report']['url'])) {
                $excelUrl = $documents['excel_report']['url'];
                $excelFilename = $documents['excel_report']['filename'] ?? 'report.xlsx';
                $bbCodeUrl = $excelUrl;
                $bbCodeFilename = $excelFilename;
                $links[] = "[url=" . $bbCodeUrl . "]" . $bbCodeFilename . "[/url]";
            }
            
            // Проверяем наличие CSV файла
            if (isset($documents['csv_report']) && !empty($documents['csv_report']['url'])) {
                $csvUrl = $documents['csv_report']['url'];
                $csvFilename = $documents['csv_report']['filename'] ?? 'report.csv';
                $bbCodeUrl = $csvUrl;
                $bbCodeFilename = $csvFilename;
                $links[] = "[url=" . $bbCodeUrl . "]" . $bbCodeFilename . "[/url]";
            }
            
            // Если есть хотя бы одна ссылка, добавляем комментарий
            if (!empty($links)) {
                apiDelay();
                
                $commentText = "Сгенерированы отчеты:\n" . implode("\n", $links);
                
                $result = $this->call->callBitrix24API('crm.timeline.comment.add', [
                    'fields' => [
                        'ENTITY_ID' => $dealId,
                        'ENTITY_TYPE' => 'deal',
                        'COMMENT' => $commentText
                    ]
                ]);
                
                if (isset($result['result']) && !empty($result['result'])) {
                    $this->logger->log([
                        'type' => 'timeline_comment',
                        'status' => 'success',
                        'deal_id' => $dealId,
                        'comment_id' => $result['result'],
                        'message' => 'Ссылки на документы добавлены в таймлайн сделки'
                    ]);
                } else {
                    $this->logger->log([
                        'type' => 'timeline_comment',
                        'status' => 'error',
                        'deal_id' => $dealId,
                        'message' => 'Не удалось добавить комментарий в таймлайн: ' . json_encode($result)
                    ]);
                }
            }
            
        } catch (Exception $e) {
            $this->logger->log([
                'type' => 'timeline_comment',
                'status' => 'error',
                'deal_id' => $dealId,
                'message' => 'Ошибка при добавлении ссылок в таймлайн: ' . $e->getMessage()
            ]);
        }
    }
}
