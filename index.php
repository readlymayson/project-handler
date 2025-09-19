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
    require_once 'set.php';
    require_once '../require/usualClass.php';
    require_once 'class.php';
    require_once 'DealCreator.php';
    require_once '../logger/class.php';
    $call = new Usual(BITRIX24_WEBHOOK_URL);
    $logger = new Logger('akvilon_check.log', __DIR__ . '/logs');
    $check = new ProjectCheck($call, $logger);
    $dealCreator = new DealCreator($call, $logger);

    $companies = $check->getCompaniesWithProjectLink();
    $today = new DateTime();
    $firstDayOfMonth = new DateTime('first day of this month');
    $isFirstDayOfMonth = $today->format('Y-m-d') === $firstDayOfMonth->format('Y-m-d');
    
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
        
        // Создание задачи "Счет и акт"
        $resultTask = $check->checkFirstDateForTask($company, $dealCreator);
        $logger->log($resultTask);
        
        // Создание сделки "Работы за предыдущий месяц" в первый день месяца
        if ($isFirstDayOfMonth && !empty($company['UF_CRM_PROJECT_LINK'])) {
            $projectId = $dealCreator->extractProjectId($company['UF_CRM_PROJECT_LINK']);
            
            if ($projectId > 0) {
                // Получаем default_price из компании
                $defaultPrice = $company['UF_CRM_DEFAULT_RATE'] ?? DEFAULT_HOURLY_RATE;
                
                $projectTimeData = $dealCreator->getProjectTimeData($projectId, $defaultPrice);
                
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
        }
        
        // Проверка лимитов часов (работает в течение всего месяца, если не было уведомления в текущем месяце)
        if (!empty($company['UF_CRM_PROJECT_LINK']) && !$isStop) {
            $result = $check->checkProjectHours($company, $dealCreator);
            $logger->log($result);
        } else {
            if ($isStop) {
                $logger->log(['info' => "Уведомление о превышении лимита в этом месяце уже отправлялось. company: #{$company['ID']} {$company['TITLE']}"]);
            } else {
                $logger->log(['error' => "Неправильная ссылка на проект. company: #{$company['ID']} {$company['TITLE']}"]);
            }
        }
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
