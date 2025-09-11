<?php

require_once 'config.php';

class DealCreator
{
    private Usual $call;
    private Logger $logger;

    public function __construct($call, Logger $logger)
    {
        $this->call = $call;
        $this->logger = $logger;
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
            
            // Получаем ответственного проект-менеджера
            $projectManager = $this->getProjectManager();
            
            // Формируем данные для новой сделки
            $dealData = $this->prepareDealData($company, $projectInfo, $clientInfo, $projectTimeData, $projectManager);
            
            // Создаем сделку
            $result = $this->call->callBitrix24API('crm.deal.add', [
                'fields' => $dealData
            ]);

            if (empty($result['result'])) {
                throw new Exception("Ошибка при создании сделки: " . json_encode($result));
            }

            $newDealId = $result['result'];
            
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
                'message' => 'Сделка "Работы за предыдущий месяц" успешно создана в воронке ' . MONTHLY_WORK_FUNNEL_ID
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
        $result = $this->call->callBitrix24API('crm.company.get', [
            'id' => $companyId
        ]);

        $company = $result['result'] ?? [];
        
        return [
            'contact_id' => null, // У компаний нет контактов напрямую
            'company_id' => $company['ID'] ?? null,
            'title' => $company['TITLE'] ?? '',
            'default_price' => $company['UF_CRM_PRICE_DEFAULT'] ?? null
        ];
    }

    /**
     * Получает первого проект-менеджера из отдела
     */
    private function getProjectManager(): int
    {
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
    private function prepareDealData($company, $projectInfo, $clientInfo, $projectTimeData, $projectManager): array
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

        // Формируем комментарий с разбивкой по ролям
        $comment = $this->formatTimeComment($projectTimeData['roles_time']);

        return [
            'TITLE' => $projectInfo['NAME'], // Название проекта
            'CATEGORY_ID' => MONTHLY_WORK_FUNNEL_ID, // Воронка "Работы за предыдущий месяц"
            'STAGE_ID' => FUNNEL_STAGES['NEW'], // Первая стадия "проекты"
            'OPPORTUNITY' => $projectTimeData['total_cost'], // Сумма
            'CLOSEDATE' => $closeDate->format('Y-m-d'), // Дата завершения
            'BEGINDATE' => $startDate->format('Y-m-d'), // Дата начала
            'CONTACT_ID' => $clientInfo['contact_id'], // Клиент
            'COMPANY_ID' => $clientInfo['company_id'], // Компания
            'TYPE_ID' => DEAL_TYPES['WORK_BY_HOURS'], // Тип сделки "работа по часам"
            'SOURCE_ID' => DEAL_SOURCES['EXISTING_CLIENT'], // Источник "существующий клиент"
            'ASSIGNED_BY_ID' => $projectManager, // Ответственный
            'COMMENTS' => $comment, // Комментарий с разбивкой времени
            'UF_CRM_PROJECT_LINK' => $company['UF_CRM_PROJECT_LINK'], // Ссылка на проект
            'UF_CRM_ORIGINAL_COMPANY' => $company['ID'] // Ссылка на исходную компанию
        ];
    }

    /**
     * Форматирует комментарий с разбивкой времени по ролям
     */
    private function formatTimeComment($rolesTime): string
    {
        $comment = "Учет времени по проекту:\n\n";
        
        foreach ($rolesTime as $role => $timeData) {
            $hours = $timeData['hours'];
            $minutes = $timeData['minutes'];
            $decimalHours = $timeData['decimal_hours'];
            
            $comment .= "$role – {$hours} ч {$minutes} м ({$decimalHours} ч)\n";
        }
        
        return $comment;
    }

    /**
     * Получает данные о времени по проекту с разбивкой по ролям
     */
    public function getProjectTimeData($projectId, $defaultPrice = null): array
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

            foreach ($tasks as $task) {
                $taskTimeData = $this->getTaskTimeByRoles($task['id']);
                
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
            $totalCost = $this->calculateTotalCost($rolesTime, $defaultPrice);

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
    private function getProjectTasks($projectId): array
    {
        $method = 'tasks.task.list';
        $allTasks = [];
        $start = 0;

        $lastMonthEnd = (new DateTime('last day of last month'))
            ->setTime(23, 59, 59)
            ->format('Y-m-d\TH:i:sP');

        do {
            $params = [
                'filter' => [
                    'GROUP_ID' => $projectId,
                    '>CLOSED_DATE' => $lastMonthEnd,
                ],
                'select' => ['ID', 'TITLE', 'TIME_ESTIMATE', 'TIME_SPENT_IN_LOGS', 'CLOSED_DATE'],
                'start' => $start
            ];

            $response = $this->call->callBitrix24API($method, $params);
            $tasks = $response['result']['tasks'] ?? [];
            $allTasks = array_merge($allTasks, $tasks);
            $start += 50;
        } while (!empty($tasks) && count($tasks) >= 50);

        return $allTasks;
    }

    /**
     * Получает время по ролям для конкретной задачи
     */
    private function getTaskTimeByRoles($taskId): array
    {
        $method = 'task.elapseditem.getlist';
        $params = ['TASKID' => $taskId];
        
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
            $role = $this->getUserRole($userId);
            
            $seconds = (int)($item['SECONDS'] ?? 0);
            $hours = $seconds / 3600;
            
            // Округляем до часа, если меньше часа
            if ($hours < MIN_HOUR_ROUNDING && $hours > ROUNDING_THRESHOLD) {
                $hours = MIN_HOUR_ROUNDING;
            }

            if (!isset($rolesTime[$role])) {
                $rolesTime[$role] = ['decimal_hours' => 0];
            }
            
            $rolesTime[$role]['decimal_hours'] += $hours;
        }

        return $rolesTime;
    }

    /**
     * Получает роль пользователя
     */
    private function getUserRole($userId): string
    {
        if ($userId == 0) {
            return 'Неизвестно';
        }

        // Получаем информацию о пользователе
        $result = $this->call->callBitrix24API('user.get', [
            'filter' => ['ID' => $userId]
        ]);

        $user = $result['result'][0] ?? [];
        $userName = $user['NAME'] . ' ' . $user['LAST_NAME'];
        
        // Здесь можно добавить логику определения роли по должности или отделу
        // Пока возвращаем имя пользователя
        return $userName;
    }

    /**
     * Рассчитывает общую стоимость работ
     */
    private function calculateTotalCost($rolesTime, $defaultPrice = null): float
    {
        $totalCost = 0;
        
        foreach ($rolesTime as $role => $timeData) {
            $rate = getRoleRate($role, $defaultPrice);
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
