<?php

require_once 'config.php';

/**
 * Класс для генерации документов в Bitrix24 через встроенную систему Document Generator
 * 
 * Этот класс использует API Bitrix24 для генерации документов по заранее созданным шаблонам.
 * Необходимо сначала создать шаблоны документов в интерфейсе Bitrix24 (CRM -> Настройки -> Шаблоны документов),
 * а затем указать их ID в config.php
 */
class DocumentGenerator
{
    private Usual $call;
    private Logger $logger;

    public function __construct($call, Logger $logger)
    {
        $this->call = $call;
        $this->logger = $logger;
    }

    /**
     * Проверяет настройки генератора документов
     * 
     * @return array Статус настроек
     */
    public function checkDocumentGeneratorSettings(): array
    {
        $settings = [
            'enabled' => ENABLE_DOCUMENT_GENERATOR,
            'auto_generate' => AUTO_GENERATE_DOCUMENTS,
            'generate_on_creation' => GENERATE_ON_DEAL_CREATION,
            'generate_on_update' => GENERATE_ON_DEAL_UPDATE,
            'templates' => [
                'report' => REPORT_TEMPLATE_ID,
                'invoice' => INVOICE_TEMPLATE_ID,
                'act' => ACT_TEMPLATE_ID
            ],
            'timeout' => DOCUMENT_GENERATION_TIMEOUT,
            'retry_attempts' => DOCUMENT_RETRY_ATTEMPTS,
            'retry_delay' => DOCUMENT_RETRY_DELAY
        ];

        // Проверяем, настроены ли шаблоны
        $configuredTemplates = 0;
        foreach ($settings['templates'] as $template => $id) {
            if ($id > 0) {
                $configuredTemplates++;
            }
        }

        $settings['configured_templates'] = $configuredTemplates;
        $settings['total_templates'] = count($settings['templates']);
        $settings['is_ready'] = $settings['enabled'] && $configuredTemplates > 0;

        return $settings;
    }

    /**
     * Генерирует все документы для сделки через Bitrix24 Document Generator
     * 
     * @param int $dealId ID сделки
     * @param array $company Данные компании
     * @param array $projectTimeData Данные о времени по проекту
     * @param int $projectId ID проекта
     * @return array Результат генерации документов
     */
    public function generateDocumentsForDeal($dealId, $company, $projectTimeData, $projectId): array
    {
        // Проверяем, включена ли генерация документов
        if (!ENABLE_DOCUMENT_GENERATOR) {
            $this->logger->info("Генерация документов отключена в настройках");
            return [
                'status' => 'disabled',
                'message' => 'Генерация документов отключена в настройках',
                'documents' => []
            ];
        }
        
        try {
            $documents = [];
            
            // Подготавливаем данные для шаблонов
            $templateData = $this->prepareTemplateData($company, $projectTimeData, $projectId);
            
            // Генерируем отчет Excel (по шаблону REPORT_TEMPLATE_ID)
            if (defined('REPORT_TEMPLATE_ID') && REPORT_TEMPLATE_ID > 0) {
                $reportDoc = $this->generateDocumentFromTemplate(
                    REPORT_TEMPLATE_ID,
                    'Deal',
                    $dealId,
                    $templateData
                );
                if ($reportDoc) {
                    $documents['report'] = $reportDoc;
                }
            }
            
            // Генерируем счет (по шаблону INVOICE_TEMPLATE_ID)
            if (defined('INVOICE_TEMPLATE_ID') && INVOICE_TEMPLATE_ID > 0) {
                $invoiceDoc = $this->generateDocumentFromTemplate(
                    INVOICE_TEMPLATE_ID,
                    'Deal',
                    $dealId,
                    $templateData
                );
                if ($invoiceDoc) {
                    $documents['invoice'] = $invoiceDoc;
                }
            }
            
            // Генерируем акт (по шаблону ACT_TEMPLATE_ID)
            if (defined('ACT_TEMPLATE_ID') && ACT_TEMPLATE_ID > 0) {
                $actDoc = $this->generateDocumentFromTemplate(
                    ACT_TEMPLATE_ID,
                    'Deal',
                    $dealId,
                    $templateData
                );
                if ($actDoc) {
                    $documents['act'] = $actDoc;
                }
            }
            
            if (empty($documents)) {
                return [
                    'status' => 'warning',
                    'message' => 'Не настроены ID шаблонов документов в config.php. Документы не созданы.'
                ];
            }
            
            return [
                'status' => 'success',
                'documents' => $documents,
                'message' => 'Документы успешно сгенерированы через Bitrix24'
            ];
            
        } catch (Exception $e) {
            $this->logger->log("Ошибка при генерации документов для сделки $dealId: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Генерирует документ из шаблона Bitrix24
     * 
     * @param int $templateId ID шаблона в Bitrix24
     * @param string $entityType Тип сущности (Deal, Contact, Company и т.д.)
     * @param int $entityId ID сущности
     * @param array $values Дополнительные значения для шаблона
     * @return array|null Информация о созданном документе
     */
    private function generateDocumentFromTemplate($templateId, $entityType, $entityId, $values = []): ?array
    {
        try {
            apiDelay();
            
            $params = [
                'templateId' => $templateId,
                'entityTypeId' => $this->getEntityTypeId($entityType),
                'entityId' => $entityId
            ];
            
            // Добавляем дополнительные значения, если они есть
            if (!empty($values)) {
                $params['values'] = $values;
            }
            
            $result = $this->call->callBitrix24API('crm.documentgenerator.document.add', $params);
            
            if (isset($result['error'])) {
                $this->logger->log([
                    'error' => 'Ошибка при генерации документа из шаблона',
                    'template_id' => $templateId,
                    'error_message' => $result['error_description'] ?? $result['error']
                ]);
                return null;
            }
            
            $documentId = $result['result']['document']['id'] ?? null;
            
            if (!$documentId) {
                $this->logger->log([
                    'error' => 'Не получен ID документа после генерации',
                    'template_id' => $templateId,
                    'response' => $result
                ]);
                return null;
            }
            
            $this->logger->log([
                'success' => 'Документ успешно сгенерирован',
                'document_id' => $documentId,
                'template_id' => $templateId
            ]);
            
            return [
                'id' => $documentId,
                'templateId' => $templateId
            ];
            
        } catch (Exception $e) {
            $this->logger->log("Ошибка при генерации документа из шаблона $templateId: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Получает числовой ID типа сущности для Bitrix24 API
     */
    private function getEntityTypeId($entityType): int
    {
        $entityTypes = [
            'Lead' => 1,
            'Deal' => 2,
            'Contact' => 3,
            'Company' => 4,
            'Invoice' => 5,
            'Quote' => 7,
            'SmartInvoice' => 31
        ];
        
        return $entityTypes[$entityType] ?? 2; // По умолчанию Deal
    }
    
    /**
     * Подготавливает данные для заполнения шаблонов документов
     */
    private function prepareTemplateData($company, $projectTimeData, $projectId): array
    {
        $lastMonth = new DateTime('first day of last month');
        $monthName = $this->getMonthNameInGenitive($lastMonth->format('n'));
        $year = $lastMonth->format('Y');
        
        // Получаем информацию о договоре
        $contractInfo = $this->getContractInfo($company);
        
        // Формируем таблицу с данными по ролям для шаблонов
        $rolesData = [];
        $itemNumber = 1;
        
        foreach ($projectTimeData['roles_time'] as $role => $timeData) {
            $rate = getRoleRate($role, $company);
            $hours = $timeData['decimal_hours'];
            $amount = $hours * $rate;
            
            // Убираем номер из роли для наименования услуги
            $cleanRole = preg_replace('/ #\d+$/', '', $role);
            $cleanRole = mb_strtolower($cleanRole);
            
            $rolesData[] = [
                'NUMBER' => $itemNumber,
                'ROLE' => $cleanRole,
                'SERVICE_NAME_INVOICE' => "Оплата услуг {$cleanRole} в {$monthName} {$year} года по приложению {$contractInfo['appendix_number']} от {$contractInfo['appendix_date']} к Договору {$contractInfo['contract_number']} от {$contractInfo['contract_date']}",
                'SERVICE_NAME_ACT' => "Услуги {$cleanRole} в {$monthName} {$year} года по приложению {$contractInfo['appendix_number']} от {$contractInfo['appendix_date']} к Договору {$contractInfo['contract_number']} от {$contractInfo['contract_date']}",
                'HOURS' => $hours,
                'RATE' => $rate,
                'AMOUNT' => $amount
            ];
            
            $itemNumber++;
        }
        
        // Получаем данные задач для отчета
        $tasksData = $this->getTasksDataForReport($projectId, $lastMonth);
        
        return [
            'MONTH_NAME' => $monthName,
            'YEAR' => $year,
            'CONTRACT_NUMBER' => $contractInfo['contract_number'],
            'CONTRACT_DATE' => $contractInfo['contract_date'],
            'APPENDIX_NUMBER' => $contractInfo['appendix_number'],
            'APPENDIX_DATE' => $contractInfo['appendix_date'],
            'TOTAL_HOURS' => $projectTimeData['total_hours'],
            'TOTAL_COST' => $projectTimeData['total_cost'],
            'ROLES_DATA' => $rolesData,
            'TASKS_DATA' => $tasksData,
            'INVOICE_NUMBER' => $this->generateDocumentNumber('invoice', $company['ID']),
            'ACT_NUMBER' => $this->generateDocumentNumber('act', $company['ID']),
            'VAT_RATE' => VAT_RATE,
            'COMPANY_NAME' => COMPANY_NAME
        ];
    }
    
    /**
     * Получает данные задач для отчета
     */
    private function getTasksDataForReport($projectId, $lastMonth): array
    {
        $tasks = [];
        
        try {
            $firstDay = clone $lastMonth;
            $firstDay->setTime(0, 0, 0);
            
            $lastDay = new DateTime('last day of ' . $lastMonth->format('Y-m'));
            $lastDay->setTime(23, 59, 59);
            
            // Получаем список задач
            $method = 'tasks.task.list';
            $params = [
                'filter' => [
                    'GROUP_ID' => $projectId,
                ],
                'select' => ['ID', 'TITLE', 'CREATED_DATE', 'CREATED_BY', 'TIME_SPENT_IN_LOGS']
            ];
            
            apiDelay();
            $response = $this->call->callBitrix24API($method, $params);
            $tasksList = $response['result']['tasks'] ?? [];
            
            foreach ($tasksList as $task) {
                $taskId = $task['id'] ?? $task['ID'];
                
                // Получаем информацию о затраченном времени за прошлый месяц
                $taskTime = $this->getTaskTimeForPeriod($taskId, $firstDay, $lastDay);
                
                if ($taskTime['total_hours'] > 0) {
                    $createdDate = new DateTime($task['createdDate'] ?? $task['CREATED_DATE']);
                    
                    $tasks[] = [
                        'CREATED_DATE' => $createdDate->format('d.m.Y'),
                        'TITLE' => $task['title'] ?? $task['TITLE'],
                        'EXECUTOR_POSITION' => $taskTime['positions'],
                        'HOURS' => round($taskTime['total_hours'], 2)
                    ];
                }
            }
            
        } catch (Exception $e) {
            $this->logger->log("Ошибка при получении данных задач для отчета: " . $e->getMessage());
        }
        
        return $tasks;
    }

    /**
     * Получает информацию о затраченном времени за период
     */
    private function getTaskTimeForPeriod($taskId, $firstDay, $lastDay): array
    {
        $totalHours = 0;
        $positions = [];
        
        try {
            apiDelay();
            $response = $this->call->callBitrix24API('task.elapseditem.getlist', [
                'TASKID' => $taskId
            ]);
            
            $elapsedItems = $response['result'] ?? [];
            
            foreach ($elapsedItems as $item) {
                if (empty($item['CREATED_DATE'])) {
                    continue;
                }
                
                $createdDate = new DateTime($item['CREATED_DATE']);
                
                if ($createdDate >= $firstDay && $createdDate <= $lastDay) {
                    $seconds = (int)($item['SECONDS'] ?? 0);
                    $hours = $seconds / 3600;
                    $totalHours += $hours;
                    
                    // Получаем должность пользователя
                    $userId = $item['USER_ID'] ?? 0;
                    if ($userId > 0) {
                        $position = $this->getUserPosition($userId);
                        if (!in_array($position, $positions)) {
                            $positions[] = $position;
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            $this->logger->log("Ошибка при получении времени задачи $taskId: " . $e->getMessage());
        }
        
        return [
            'total_hours' => $totalHours,
            'positions' => implode(', ', $positions)
        ];
    }

    /**
     * Получает должность пользователя
     */
    private function getUserPosition($userId): string
    {
        try {
            apiDelay();
            $result = $this->call->callBitrix24API('user.get', [
                'filter' => ['ID' => $userId]
            ]);
            
            $user = $result['result'][0] ?? [];
            return $user['WORK_POSITION'] ?? 'Не указано';
            
        } catch (Exception $e) {
            $this->logger->log("Ошибка при получении должности пользователя $userId: " . $e->getMessage());
            return 'Не указано';
        }
    }
    
    /**
     * Получает информацию о договоре и приложении из компании
     */
    private function getContractInfo($company): array
    {
        return [
            'contract_number' => $company['UF_CRM_CONTRACT_NUMBER'] ?? DEFAULT_CONTRACT_NUMBER,
            'contract_date' => $company['UF_CRM_CONTRACT_DATE'] ?? DEFAULT_CONTRACT_DATE,
            'appendix_number' => $company['UF_CRM_APPENDIX_NUMBER'] ?? DEFAULT_APPENDIX_NUMBER,
            'appendix_date' => $company['UF_CRM_APPENDIX_DATE'] ?? DEFAULT_APPENDIX_DATE
        ];
    }

    /**
     * Генерирует номер документа
     */
    private function generateDocumentNumber($type, $companyId): string
    {
        $prefix = ($type === 'invoice') ? INVOICE_NUMBER_PREFIX : ACT_NUMBER_PREFIX;
        $date = date('Ymd');
        return $prefix . '-' . $date . '-' . $companyId;
    }

    /**
     * Получает название месяца в родительном падеже
     */
    private function getMonthNameInGenitive($monthNumber): string
    {
        $months = [
            1 => 'январе', 2 => 'феврале', 3 => 'марте', 4 => 'апреле',
            5 => 'мае', 6 => 'июне', 7 => 'июле', 8 => 'августе',
            9 => 'сентябре', 10 => 'октябре', 11 => 'ноябре', 12 => 'декабре'
        ];
        
        return $months[(int)$monthNumber] ?? '';
    }
}