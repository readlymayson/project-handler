<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'logs/error.log');
error_reporting(E_ALL);

try {
    if (!file_exists('set.php')) {
        throw new Exception('Файл set.php не найден.');
    }
    if (!file_exists('class.php')) {
        throw new Exception('Файл class.php не найден.');
    }
    if (!file_exists('../logger/class.php')) {
        throw new Exception('Файл logger/class.php не найден.');
    }
    if (!file_exists('../require/usualClass.php')) {
        throw new Exception('Файл ../require/usualClass.php не найден.');
    }
    if (!file_exists('DealCreator.php')) {
        throw new Exception('Файл DealCreator.php не найден.');
    }
    if (!file_exists('DocumentGenerator.php')) {
        throw new Exception('Файл DocumentGenerator.php не найден.');
    }
    if (!file_exists('ExternalDocumentGenerator.php')) {
        throw new Exception('Файл ExternalDocumentGenerator.php не найден.');
    }
    
    require_once 'set.php';
    require_once '../require/usualClass.php';
    require_once 'class.php';
    require_once 'DealCreator.php';
    require_once 'DocumentGenerator.php';
    require_once 'ExternalDocumentGenerator.php';
    require_once '../logger/class.php';
    $call = new Usual(BITRIX24_WEBHOOK_URL);
    $logger = new Logger('akvilon_check.log', __DIR__ . '/logs');
    $check = new ProjectCheck($call, $logger);
    
    // Инициализируем генераторы документов
    $documentGenerator = new DocumentGenerator($call, $logger); // Устаревший (Bitrix24)
    $externalDocumentGenerator = new ExternalDocumentGenerator($call, $logger); // Новый (внешние библиотеки)
    $dealCreator = new DealCreator($call, $logger, $documentGenerator, $externalDocumentGenerator);

    $companies = $check->getCompaniesWithProjectLink();
    
    foreach ($companies as $company) {
        $isStop = false;
        if (!empty($company['UF_CRM_NOTIFY_DATE'])) {
            $notifyDate = new DateTime($company['UF_CRM_NOTIFY_DATE']);
            $currentDate = new DateTime();
            // Проверяем, было ли уведомление в текущем месяце
            $isStop = $notifyDate->format('Y-m') === $currentDate->format('Y-m');
        }
        $company['UF_CRM_PROJECT_LINK'] = $company['UF_CRM_PROJECT_LINK'] ?? '';
        $company['ASSIGNED_BY_ID'] = $company['ASSIGNED_BY_ID'] ?? 0;
        $company['UF_CRM_HOURS_LIMIT'] = $company['UF_CRM_HOURS_LIMIT'] ?? 0;
        
        // Создание сделки "Работы за предыдущий месяц"
        if (!empty($company['UF_CRM_PROJECT_LINK'])) {
            try {
                $projectId = $dealCreator->extractProjectId($company['UF_CRM_PROJECT_LINK']);
                
                if ($projectId > 0) {
                    $projectTimeData = $dealCreator->getProjectTimeData($projectId, $company);
                    
                    // Создаем сделку только если есть затраченное время
                    if ($projectTimeData['total_hours'] > 0) {
                        $dealResult = $dealCreator->createMonthlyWorkDeal($company, $projectTimeData);
                        $logger->log($dealResult);
                    } else {
                        $logger->log([
                            'type' => 'deal_creation',
                            'status' => 'skipped',
                            'company_id' => $company['ID'],
                            'project_link' => $company['UF_CRM_PROJECT_LINK'],
                            'project_id' => $projectId,
                            'message' => 'Сделка не создана - нет затраченного времени в предыдущем месяце'
                        ]);
                    }
                } else {
                    $logger->log([
                        'type' => 'deal_creation',
                        'status' => 'error',
                        'company_id' => $company['ID'],
                        'project_link' => $company['UF_CRM_PROJECT_LINK'],
                        'message' => 'Не удалось извлечь ID проекта из ссылки'
                    ]);
                }
            } catch (Exception $e) {
                $logger->log([
                    'type' => 'deal_creation',
                    'status' => 'error',
                    'company_id' => $company['ID'],
                    'message' => 'Ошибка при создании сделки: ' . $e->getMessage()
                ]);
            }
        }
        
        // Проверка лимитов часов
        if (!empty($company['UF_CRM_PROJECT_LINK']) && !$isStop) {
            try {
                $result = $check->checkProjectHours($company, $dealCreator);
                $logger->log($result);
            } catch (Exception $e) {
                $logger->log([
                    'type' => 'hours_check',
                    'status' => 'error',
                    'company_id' => $company['ID'],
                    'message' => 'Ошибка при проверке лимитов часов: ' . $e->getMessage()
                ]);
            }
        } else {
            if ($isStop) {
                $logger->log(['info' => "Уведомление о превышении лимита в этом месяце уже отправлялось. company: #{$company['ID']} {$company['TITLE']}"]);
            } else {
                $logger->log(['error' => "Неправильная ссылка на проект. company: #{$company['ID']} {$company['TITLE']}"]);
            }
        }
    }
} catch (Exception $e) {
    // Логируем критические ошибки инициализации
    if (isset($logger)) {
        $logger->log([
            'type' => 'critical_error',
            'status' => 'error',
            'message' => 'Критическая ошибка инициализации: ' . $e->getMessage()
        ]);
    }
    echo "Критическая ошибка: " . $e->getMessage();
}
