<?php

require_once 'config.php';

class DealCreator
{
    private Usual $call;
    private Logger $logger;
    private array $userRoleCache = []; // Кэш для ролей пользователей
    private ?DocumentGenerator $documentGenerator = null; // Генератор документов

    public function __construct($call, Logger $logger, DocumentGenerator $documentGenerator = null)
    {
        $this->call = $call;
        $this->logger = $logger;
        $this->documentGenerator = $documentGenerator;
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
     * Проверяет, существует ли уже сделка для этого проекта в текущем месяце
     */
    private function checkDuplicateDeal($projectId): bool
    {
        if (!CHECK_DUPLICATES) {
            return false;
        }

        try {
            $currentDate = new DateTime();
            $firstDayOfMonth = new DateTime('first day of this month');
            $lastDayOfMonth = new DateTime('last day of this month');

            $result = $this->call->callBitrix24API('crm.deal.list', [
                'filter' => [
                    'UF_CRM_PROJECT_LINK' => $projectId,
                    '>=BEGINDATE' => $firstDayOfMonth->format('Y-m-d'),
                    '<=BEGINDATE' => $lastDayOfMonth->format('Y-m-d'),
                    'TITLE' => '%' . $this->getProjectInfo($projectId)['NAME'] . '%'
                ],
                'select' => ['ID', 'TITLE', 'BEGINDATE']
            ]);

            $deals = $result['result'] ?? [];
            return !empty($deals);

        } catch (Exception $e) {
            $this->logger->log("Ошибка при проверке дубликатов для проекта $projectId: " . $e->getMessage());
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

            // Проверяем на дубликаты
            if ($this->checkDuplicateDeal($projectId)) {
                return [
                    'status' => 'duplicate',
                    'message' => "Сделка для проекта $projectId уже существует в текущем месяце"
                ];
            }

            // Получаем информацию о проекте
            $projectInfo = $this->getProjectInfo($projectId);
            if (empty($projectInfo)) {
                throw new Exception("Не удалось получить информацию о проекте $projectId");
            }

            // Получаем информацию о клиентах и компании
            $clientInfo = $this->getClientInfo($originalCompanyId);
            
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
            
            // Генерируем и прикрепляем документы
            if ($this->documentGenerator !== null) {
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
                        'message' => 'Документы успешно сгенерированы и прикреплены'
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
                'message' => 'Сделка "Работы за предыдущий месяц" успешно создана в воронке ' . MONTHLY_WORK_FUNNEL_ID . ', ответственный: ' . ($company['ASSIGNED_BY_ID'] ?? 1)
            ]);


            return [
                'status' => 'success',
                'deal_id' => $newDealId,
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
     * Форматирует комментарий с разбивкой времени по ролям и детальным отчетом по задачам
     */
    private function formatTimeComment($rolesTime, $projectId = null): string
    {
        $comment = "Учет времени по проекту:\n\n";
        
        // Добавляем детальный отчет по задачам, если передан ID проекта
        if ($projectId) {
            $comment .= $this->generateDetailedTaskReport($projectId);
            $comment .= "\n" . str_repeat("=", 50) . "\n\n";
        }
        
        // Общая сводка по ролям
        $comment .= "СВОДКА ПО РОЛЯМ:\n";
        $comment .= str_repeat("-", 20) . "\n";
        
        foreach ($rolesTime as $role => $timeData) {
            $hours = $timeData['hours'];
            $minutes = $timeData['minutes'];
            $decimalHours = $timeData['decimal_hours'];
            
            $comment .= "$role – {$hours} ч {$minutes} м ({$decimalHours} ч)\n";
        }
        
        return $comment;
    }

    /**
     * Генерирует детальный отчет по задачам с разбивкой по ролям и времени
     */
    private function generateDetailedTaskReport($projectId): string
    {
        try {
            $tasks = $this->getProjectTasks($projectId);
            if (empty($tasks)) {
                return "ДЕТАЛЬНЫЙ ОТЧЕТ ПО ЗАДАЧАМ:\n" . 
                       str_repeat("-", 30) . "\n" .
                       "Задач не найдено\n\n";
            }

            $report = "ДЕТАЛЬНЫЙ ОТЧЕТ ПО ЗАДАЧАМ:\n";
            $report .= str_repeat("-", 30) . "\n\n";

            $totalTasks = count($tasks);
            $totalTime = 0;

            foreach ($tasks as $index => $task) {
                $taskId = $task['id'] ?? $task['ID'] ?? 0;
                $taskTitle = $task['title'] ?? $task['TITLE'] ?? 'Без названия';
                $timeSpent = $task['timeSpentInLogs'] ?? $task['TIME_SPENT_IN_LOGS'] ?? 0;
                $timeEstimate = $task['timeEstimate'] ?? $task['TIME_ESTIMATE'] ?? 0;
                $closedDate = $task['closedDate'] ?? $task['CLOSED_DATE'] ?? null;

                // Получаем детальную информацию о времени по ролям для этой задачи
                $taskRolesTime = $this->getTaskTimeByRoles($taskId);
                
                // Конвертируем секунды в часы
                $hoursSpent = round($timeSpent / 3600, 2);
                $hoursEstimate = round($timeEstimate / 3600, 2);
                $totalTime += $hoursSpent;

                $report .= "ЗАДАЧА #" . ($index + 1) . " (ID: $taskId)\n";
                $report .= "Название: " . substr($taskTitle, 0, 60) . 
                          (strlen($taskTitle) > 60 ? '...' : '') . "\n";
                $report .= "Время затрачено: {$hoursSpent} ч\n";
                $report .= "Время оценено: {$hoursEstimate} ч\n";
                
                if ($closedDate) {
                    $report .= "Дата закрытия: $closedDate\n";
                } else {
                    $report .= "Статус: В работе\n";
                }

                // Добавляем разбивку по ролям для этой задачи
                if (!empty($taskRolesTime)) {
                    $report .= "Время по ролям:\n";
                    foreach ($taskRolesTime as $role => $timeData) {
                        $decimalHours = $timeData['decimal_hours'];
                        $hours = floor($decimalHours);
                        $minutes = round(($decimalHours - $hours) * 60);
                        $report .= "  • $role: {$hours} ч {$minutes} м ({$decimalHours} ч)\n";
                    }
                } else {
                    $report .= "Время по ролям: не найдено\n";
                }

                $report .= "\n";
            }

            $report .= "ИТОГО:\n";
            $report .= "Всего задач: $totalTasks\n";
            $report .= "Общее время: {$totalTime} ч\n\n";

            return $report;

        } catch (Exception $e) {
            $this->logger->log("Ошибка при генерации детального отчета по задачам для проекта $projectId: " . $e->getMessage());
            return "ДЕТАЛЬНЫЙ ОТЧЕТ ПО ЗАДАЧАМ:\n" . 
                   str_repeat("-", 30) . "\n" .
                   "Ошибка при получении данных: " . $e->getMessage() . "\n\n";
        }
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
        $method = 'task.elapseditem.getlist';
        $params = ['TASKID' => $taskId];
        
        // Добавляем задержку перед каждым API вызовом
        apiDelay();
        
        $response = $this->call->callBitrix24API($method, $params);
        $elapsedItems = $response['result'] ?? [];

        $rolesTime = [];
        $userRoles = []; // Отслеживаем роли пользователей
        $roleCounters = []; // Счетчики для одинаковых ролей
        $firstDay = (new DateTime("first day of last month"))->setTime(0, 0, 0);
        $lastDay = (new DateTime("last day of last month"))->setTime(23, 59, 59);

        foreach ($elapsedItems as $item) {
            if (empty($item['CREATED_DATE'])) {
                continue;
            }

            $createdDate = new DateTime($item['CREATED_DATE']);
            if ($createdDate < $firstDay || $createdDate > $lastDay) {
                continue;
            }

            // Получаем роль пользователя
            $userId = $item['USER_ID'] ?? 0;
            $baseRole = $this->getUserRole($userId);
            
            // Определяем уникальную роль с номером
            $uniqueRole = $this->getUniqueRoleForUser($userId, $baseRole, $userRoles, $roleCounters);
            
            $seconds = (int)($item['SECONDS'] ?? 0);
            $hours = $seconds / 3600;
            
            // Округляем до часа, если меньше часа
            if ($hours < MIN_HOUR_ROUNDING && $hours > ROUNDING_THRESHOLD) {
                $hours = MIN_HOUR_ROUNDING;
            }

            if (!isset($rolesTime[$uniqueRole])) {
                $rolesTime[$uniqueRole] = ['decimal_hours' => 0];
            }
            
            $rolesTime[$uniqueRole]['decimal_hours'] += $hours;
        }

        return $rolesTime;
    }

    /**
     * Получает время по ролям для конкретной задачи с глобальной нумерацией
     */
    private function getTaskTimeByRolesWithGlobalNumbering($taskId, &$globalUserRoles, &$globalRoleCounters): array
    {
        $method = 'task.elapseditem.getlist';
        $params = ['TASKID' => $taskId];
        
        // Добавляем задержку перед каждым API вызовом
        apiDelay();
        
        $response = $this->call->callBitrix24API($method, $params);
        $elapsedItems = $response['result'] ?? [];

        $rolesTime = [];
        $firstDay = (new DateTime("first day of last month"))->setTime(0, 0, 0);
        $lastDay = (new DateTime("last day of last month"))->setTime(23, 59, 59);

        foreach ($elapsedItems as $item) {
            if (empty($item['CREATED_DATE'])) {
                continue;
            }

            $createdDate = new DateTime($item['CREATED_DATE']);
            if ($createdDate < $firstDay || $createdDate > $lastDay) {
                continue;
            }

            // Получаем роль пользователя
            $userId = $item['USER_ID'] ?? 0;
            $baseRole = $this->getUserRole($userId);
            
            // Определяем уникальную роль с глобальным номером
            $uniqueRole = $this->getUniqueRoleForUser($userId, $baseRole, $globalUserRoles, $globalRoleCounters);
            
            $seconds = (int)($item['SECONDS'] ?? 0);
            $hours = $seconds / 3600;
            
            // Округляем до часа, если меньше часа
            if ($hours < MIN_HOUR_ROUNDING && $hours > ROUNDING_THRESHOLD) {
                $hours = MIN_HOUR_ROUNDING;
            }

            if (!isset($rolesTime[$uniqueRole])) {
                $rolesTime[$uniqueRole] = ['decimal_hours' => 0];
            }
            
            $rolesTime[$uniqueRole]['decimal_hours'] += $hours;
        }

        return $rolesTime;
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
     * Получает роль пользователя на основе должности
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

        // Получаем информацию о пользователе
        $result = $this->call->callBitrix24API('user.get', [
            'filter' => ['ID' => $userId]
        ]);

        $user = $result['result'][0] ?? [];
        $userPosition = $user['WORK_POSITION'] ?? '';
        
        // Определяем роль на основе должности
        $role = $this->mapPositionToRole($userPosition);
        
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
}
