<?php
class ProjectCheck
{
    private Usual $call;
    private Logger $logger;

    public function __construct($call, Logger $logger)
    {
        $this->call = $call;
        $this->logger = $logger;
    }

    /**
     * @throws Exception
     */
    private function getProjectTotalHours($projectId): int | float
    {
        $totalHours = 0;
        $tasks = $this->getProjectTasks($projectId);
        if (empty($tasks)) {
            $this->logger->log("Не найдено ни одного таска по фильтру в проекте $projectId");
            return 0;
        }
        $totalHours = $this->getTaskTimeForMultipleTasks($tasks);
        return round($totalHours, 2);
    }

    /**
     * @throws Exception
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
                    '>CLOSED_DATE' => $lastMonthEnd,
                ],
                'select' => ['ID', 'TITLE', 'TIME_ESTIMATE', 'TIME_SPENT_IN_LOGS', 'CLOSED_DATE'],
                'start' => $start
            ];

            $responseUnfinished = $this->call->callBitrix24API($method, $paramsUnfinished);
            $tasksUnfinished = $responseUnfinished['result']['tasks'] ?? [];

            $responseFinished = $this->call->callBitrix24API($method, $paramsFinished);
            $tasksFinished = $responseFinished['result']['tasks'] ?? [];

            $tasks = array_merge($tasksUnfinished, $tasksFinished);
            $allTasks = array_merge($allTasks, $tasks);

            $start += 50;
        } while ((!empty($tasksUnfinished) || !empty($tasksFinished)) && count($tasks) >= 50);

        return $allTasks;
    }

    private function getTaskTimeForMultipleTasks(array $tasks, $lastMonth = false): float|int
    {
        $totalHours = 0;

        foreach ($tasks as $task) {
            try {
                $taskId = $task['id'] ?? 0;
                $method = 'task.elapseditem.getlist';
                $params = [
                    'TASKID' => $taskId,
                ];

                $response = $this->call->callBitrix24API($method, $params);


                if (isset($response['result'])) {
                    $elapsedItems = $response['result'];
                } else {
                    $this->logger->log("Response for task $taskId: " . json_encode($response));
                    $elapsedItems = [];
                }
                $totalHours += $this->calculateTimeForTask($elapsedItems, $lastMonth);
            } catch (Exception $e) {
                $this->logger->log("Error processing task " . $taskId . ": " . $e->getMessage());
                return 0;
            }
        }

        return $totalHours;
    }

    /**
     * Использвуется в getTaskTimeForMultipleTasks, собирает затрач. время в задаче
     * @param array $elapsedItems
     * @param bool $lastMonth
     * @return float|int
     */
    private function calculateTimeForTask(array $elapsedItems, $lastMonth): float | int
    {
        $totalTime = 0;
        $interval = $lastMonth ? 'last month' : 'this month';

        if ($elapsedItems === [] || $elapsedItems === 0) {
            $this->logger->log("No elapsed items found for interval: $interval");
            return 0;
        }

        $firstDay = (new DateTime("first day of $interval"))->setTime(0, 0, 0);
        $lastDay = (new DateTime("last day of $interval"))->setTime(23, 59, second: 59);

        foreach ($elapsedItems as $item) {
            try {
                if ($item == null || $item == 0) {
                    $this->logger->log("Empty elapsed item");
                    continue;
                }
                if (empty($item['CREATED_DATE']) || !is_string($item['CREATED_DATE'])) {
                    $this->logger->log("Invalid CREATED_DATE: " . json_encode($item));
                    continue;
                }

                $createdDate = new DateTime($item['CREATED_DATE']);

                if ($createdDate > $lastDay) {
                    break;
                }

                if ($createdDate >= $firstDay) {
                    $totalTime += (int)$item['SECONDS'];
                }
            } catch (Exception $e) {
                $this->logger->log("Error processing elapsed item: " . $e->getMessage());
                continue;
            }
        }

        if ($totalTime === 0) {
            $this->logger->log("No time spent in the task");
            return 0;
        }
        return $totalTime / 3600;
    }

    /**
     * @throws Exception
     */
    private function sendNotification($userId, $message)
    {
        if (!$userId) {
            return '';
        }
        $method = 'im.notify';
        $params = [
            'to' => $userId,
            'message' => $message,
            'type' => 'SYSTEM'
        ];
        return $this->call->callBitrix24API($method, $params);
    }

    /**
     * Получает компании с привязанными проектами
     * @throws Exception
     */
    public function getCompaniesWithProjectLink(): array
    {
        $method = 'crm.company.list';
        $allCompanies = [];
        $start = 0;

        do {
            $params = [
                'filter' => [
                    '!UF_CRM_PROJECT_LINK' => [null, '', false],
                ],
                'select' => ['ID', 'TITLE', 'UF_CRM_PROJECT_LINK', 'UF_CRM_PRICE_DEFAULT', 'ASSIGNED_BY_ID', 'UF_CRM_HOURS_LIMIT', 'UF_CRM_NOTIFY_DATE', 'UF_CRM_EXTRANET_USER'],
                'start' => $start
            ];

            $response = $this->call->callBitrix24API($method, $params);
            $companies = $response['result'] ?? [];

            $allCompanies = array_merge($allCompanies, $companies);

            $start += 50;
        } while (!empty($companies) && count($companies) >= 50);

        return $allCompanies;
    }

    public function checkProjectHours($company, $dealCreator): array
    {
        $companyId = $company['ID'];
        $projectLink = $company['UF_CRM_PROJECT_LINK'] ?? '';
        $projectId = $dealCreator->extractProjectId($projectLink);
        $hoursLimit = $company['UF_CRM_HOURS_LIMIT'] ?? 0;
        $notifyUser = $company['UF_CRM_EXTRANET_USER'] ?? 0;
        $responsible = $company['ASSIGNED_BY_ID'] ?? 0;
        
        if ($projectId <= 0) {
            throw new InvalidArgumentException('Project ID must be greater than 0');
        }
        
        if ($hoursLimit <= 0) {
            return [
                'status' => 'no_limit',
                'message' => "У компании $companyId не установлен лимит часов"
            ];
        }

        try {
            $totalHours = $this->getProjectTotalHours($projectId);
            if ($totalHours > $hoursLimit) {
                // Проверяем, было ли уже уведомление в этом месяце
                $notifyDate = $company['UF_CRM_NOTIFY_DATE'] ?? '';
                $currentDate = new DateTime();
                $wasNotifiedThisMonth = false;
                
                if (!empty($notifyDate)) {
                    try {
                        $lastNotifyDate = new DateTime($notifyDate);
                        $wasNotifiedThisMonth = $lastNotifyDate->format('Y-m') === $currentDate->format('Y-m');
                    } catch (Exception $e) {
                        $this->logger->log("Ошибка при парсинге даты уведомления: " . $e->getMessage());
                    }
                }
                
                if ($wasNotifiedThisMonth) {
                    return [
                        'status' => 'already_notified',
                        'total_hours' => $totalHours,
                        'hours_limit' => $hoursLimit,
                        'excess_hours' => round($totalHours - $hoursLimit, 2),
                        'message' => "Лимит превышен, но уведомление в этом месяце уже отправлялось. Проект $projectId"
                    ];
                }
                $projResult = $this->call->callBitrix24API('sonet_group.get', [
                    'FILTER' => [
                        'ID' => $projectId
                    ]
                ]);
                $projectName = $projResult['result'][0]['NAME'] ?? '';
                $companyName = $company['TITLE'] ?? '';
                
                $message = "🚨 ПРЕВЫШЕН ЛИМИТ ЧАСОВ! 🚨\n\n"
                    . "📊 Проект: #$projectId $projectName\n"
                    . "🏢 Компания: $companyName\n"
                    . "⏰ Текущее время: $totalHours часов\n"
                    . "⚠️ Лимит: $hoursLimit часов\n"
                    . "📈 Превышение: " . round($totalHours - $hoursLimit, 2) . " часов\n\n"
                    . "🔗 Ссылка на компанию: https://akvilon-marketing.bitrix24.ru/crm/company/details/$companyId/\n"
                    . "🔗 Ссылка на проект: https://akvilon-marketing.bitrix24.ru/workgroups/group/$projectId/";

                // Отправляем уведомления
                $notifyResult_1 = $this->sendNotification($notifyUser, $message); // Экстранет пользователь
                $notifyResult_2 = $this->sendNotification(1, $message); // Администратор
                $notifyResult_3 = $this->sendNotification($responsible, $message); // Ответственный
                
                // Обновляем дату уведомления в компании
                $updateResult = [];
                if (!empty($notifyResult_1['result']) && !empty($notifyResult_2['result']) && !empty($notifyResult_3['result'])) {
                    $currentDate = new DateTime();
                    $updateResult = $this->call->callBitrix24API('crm.company.update', [
                        'id' => $companyId,
                        'fields' => [
                            'UF_CRM_NOTIFY_DATE' => $currentDate->format('d.m.Y')
                        ],
                    ]);
                }

                return [
                    'status' => 'limit_exceeded',
                    'total_hours' => $totalHours,
                    'hours_limit' => $hoursLimit,
                    'excess_hours' => round($totalHours - $hoursLimit, 2),
                    'project_id' => $projectId,
                    'project_name' => $projectName,
                    'company_id' => $companyId,
                    'company_name' => $companyName,
                    'notifications' => [
                        'extranet_user' => !empty($notifyResult_1['result']),
                        'admin' => !empty($notifyResult_2['result']),
                        'responsible' => !empty($notifyResult_3['result']),
                        'update' => !empty($updateResult['result']),
                    ],
                    'message' => $message
                ];
            } elseif ($totalHours === 0) {
                return [
                    'status' => 'empty',
                    'message' => "Не найдено задач по текущему месяцу. Проект $projectId"
                ];
            } else {
                return [
                    'status' => 'ok',
                    'total_hours' => $totalHours,
                    'hours_limit' => $hoursLimit,
                    'remaining_hours' => round($hoursLimit - $totalHours, 2),
                    'message' => "Лимит не превышен. Текущее количество часов: $totalHours, лимит: $hoursLimit, осталось: " . round($hoursLimit - $totalHours, 2) . " часов. Проект $projectId"
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Создает задачу "Счет и акт" в конце месяца для указанной компании
     *
     * @param array $company Данные компании
     * @param DealCreator $dealCreator Экземпляр DealCreator для извлечения ID проекта
     * @return array Результат создания задачи
     */ 
    public function checkFirstDateForTask($company, $dealCreator): array
    {
        try {
            $companyId = $company['ID'] ?? 0;
            $responsibleId = $company['ASSIGNED_BY_ID'] ?? 0;
            $projectLink = $company['UF_CRM_PROJECT_LINK'] ?? '';
            $projectId = $dealCreator->extractProjectId($projectLink);

            $today = new DateTime();
            $firstDayOfMonth = new DateTime('first day of this month');

            if ($today->format('Y-m-d') !== $firstDayOfMonth->format('Y-m-d')) {
                return [
                    'type' => 'taskCreate',
                    'status' => 'skip',
                    'message' => 'Задача создается только в первый день месяца (' .
                        $today->format('d.m.Y') . ')'
                ];
            }

            $tasks = $this->getProjectTasks($projectId);
            $totalTime = $this->getTaskTimeForMultipleTasks($tasks,true);
            if (empty($totalTime) || $totalTime === 0) {
                return [
                    'type' => 'taskCreate',
                    'status' => 'empty tasks',
                    'message' => 'Задачи в прошлом месяце не имеют в себе затраченного времени',
                ];
            }

            if ($projectId <= 0) {
                throw new Exception("У компании $companyId не указана ссылка на проект или не удалось извлечь ID проекта");
            }

            $deadline = clone $today;
            $addedDays = 0;

            while ($addedDays < 3) {
                $deadline->modify('+1 day');
                $weekday = $deadline->format('N');

                if ($weekday < 6) {
                    $addedDays++;
                }
            }

            $taskTitle = "Создать задачу Счета и акта для компании #$companyId " . $company['TITLE'] . " И проекта #$projectId";
            $taskDescription = "Необходимо подготовить счет и акт для компании.\n"
                . "Ссылка на компанию: https://akvilon-marketing.bitrix24.ru/crm/company/details/$companyId/\n".
                "Ссылка на создание: https://akvilon-marketing.bitrix24.ru/workgroups/group/$projectId/tasks/task/edit/0/?SCOPE=tasks_grid&GROUP_ID=$projectId\n";

            $createTaskResult = $this->call->callBitrix24API('tasks.task.add', [
                'fields' => [
                    'TITLE' => $taskTitle,
                    'DESCRIPTION' => $taskDescription,
                    'RESPONSIBLE_ID' => $responsibleId,
                    'CREATED_BY' => 1,
                    'GROUP_ID' => $projectId,
                    'DEADLINE' => $deadline->format('Y-m-d'),
                    'UF_CRM_TASK' => ['C_' . $companyId],
                ],
            ]);

            if (empty($createTaskResult['result'])) {
                throw new Exception("Ошибка при создании задачи: " . json_encode($createTaskResult));
            }

            return [
                'type' => 'task',
                'status' => 'success',
                'task' => $createTaskResult['result']['id'],
                'message' => 'Задача "Счет и акт" успешно создана',
                'deadline' => $deadline->format('Y-m-d'),
            ];
        } catch (Exception $e) {
            $this->logger->log("Ошибка в checkFirstDateForTask для компании $companyId: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}
