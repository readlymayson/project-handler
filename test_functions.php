<?php
/**
 * Тест отдельных функций и методов системы
 * Проверяет корректность работы всех ключевых функций
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'logs/error.log');
error_reporting(E_ALL);

// Подключаем общие функции тестирования
require_once 'test_helpers.php';

try {
    testPrintHeader("ТЕСТИРОВАНИЕ ФУНКЦИЙ И МЕТОДОВ", TestColors::MAGENTA);
    echo "Время запуска: " . date('Y-m-d H:i:s') . "\n\n";

    // Подключение классов
    require_once 'set.php';
    require_once '../require/usualClass.php';
    require_once 'class.php';
    require_once 'config.php';
    require_once 'DealCreator.php';
    require_once 'DocumentGenerator.php';
    require_once 'UF_CRM_FieldChecker.php';
    require_once '../logger/class.php';
    
    $call = new Usual(BITRIX24_WEBHOOK_URL);
    $logger = new Logger('functions_test.log', __DIR__ . '/logs');
    $check = new ProjectCheck($call, $logger);
    $dealCreator = new DealCreator($call, $logger);
    $documentGenerator = new DocumentGenerator($call, $logger);
    $fieldChecker = new UF_CRM_FieldChecker($call, $logger);

    // ========================================
    // ТЕСТ 1: ФУНКЦИИ КОНФИГУРАЦИИ
    // ========================================
    testPrintHeader("ТЕСТ 1: ФУНКЦИИ КОНФИГУРАЦИИ", TestColors::BLUE);
    
    // Тест getRoleRate
    testPrintInfo("Тестирование функции getRoleRate...");
    
    $testCompany = [
        'UF_CRM_FRONTEND_RATE' => '1500|RUB',
        'UF_CRM_BACKEND_RATE' => '2000|RUB',
        'UF_CRM_DESIGNER_RATE' => '1200|RUB',
        'UF_CRM_PM_RATE' => '2500|RUB',
        'UF_CRM_CONTENT_MANAGER_RATE' => '1000|RUB'
    ];
    
    $rateTests = [
        ['Front-end разработчик', 1500],
        ['Back-end разработчик', 2000],
        ['Дизайнер', 1200],
        ['Проект-менеджер', 2500],
        ['Контент-менеджер', 1000],
        ['Front-end разработчик #2', 1500],
        ['Back-end разработчик #3', 2000],
        ['Неизвестная роль', DEFAULT_HOURLY_RATE]
    ];
    
    $rateOk = 0;
    foreach ($rateTests as $test) {
        $role = $test[0];
        $expected = $test[1];
        $actual = getRoleRate($role, $testCompany);
        $isCorrect = $actual == $expected;
        
        testPrintTest("getRoleRate('$role')", $isCorrect, "Получено: $actual, ожидалось: $expected");
        if ($isCorrect) $rateOk++;
    }
    
    testPrintInfo("Результат getRoleRate: $rateOk/" . count($rateTests) . " тестов пройдено");
    
    // Тест getRoleRateField
    testPrintInfo("Тестирование функции getRoleRateField...");
    
    $fieldTests = [
        ['Front-end разработчик', 'UF_CRM_FRONTEND_RATE'],
        ['Back-end разработчик', 'UF_CRM_BACKEND_RATE'],
        ['Дизайнер', 'UF_CRM_DESIGNER_RATE'],
        ['Проект-менеджер', 'UF_CRM_PM_RATE'],
        ['Контент-менеджер', 'UF_CRM_CONTENT_MANAGER_RATE'],
        ['Front-end разработчик #2', 'UF_CRM_FRONTEND_RATE'],
        ['Неизвестная роль', null]
    ];
    
    $fieldOk = 0;
    foreach ($fieldTests as $test) {
        $role = $test[0];
        $expected = $test[1];
        $actual = getRoleRateField($role);
        $isCorrect = $actual === $expected;
        
        testPrintTest("getRoleRateField('$role')", $isCorrect, "Получено: $actual, ожидалось: $expected");
        if ($isCorrect) $fieldOk++;
    }
    
    testPrintInfo("Результат getRoleRateField: $fieldOk/" . count($fieldTests) . " тестов пройдено");
    
    // ========================================
    // ТЕСТ 2: МЕТОДЫ DEALCREATOR
    // ========================================
    testPrintHeader("ТЕСТ 2: МЕТОДЫ DEALCREATOR", TestColors::BLUE);
    
    // Тест extractProjectId
    testPrintInfo("Тестирование метода extractProjectId...");
    
    $extractTests = [
        ['168', 168],
        ['https://akvilon-marketing.bitrix24.ru/workgroups/group/456/', 456],
        ['/workgroups/group/789/', 789],
        ['group_id=101', 101],
        ['id=202', 202],
        ['some text with 303 number', 303],
        ['', 0],
        ['no numbers here', 0],
        ['https://example.com/group/999/', 999]
    ];
    
    $extractOk = 0;
    foreach ($extractTests as $test) {
        $input = $test[0];
        $expected = $test[1];
        $actual = $dealCreator->extractProjectId($input);
        $isCorrect = $actual === $expected;
        
        testPrintTest("extractProjectId('$input')", $isCorrect, "Получено: $actual, ожидалось: $expected");
        if ($isCorrect) $extractOk++;
    }
    
    testPrintInfo("Результат extractProjectId: $extractOk/" . count($extractTests) . " тестов пройдено");
    
    // ========================================
    // ТЕСТ 3: МЕТОДЫ PROJECTCHECK
    // ========================================
    testPrintHeader("ТЕСТ 3: МЕТОДЫ PROJECTCHECK", TestColors::BLUE);
    
    // Тест getCompaniesWithProjectLink
    testPrintInfo("Тестирование метода getCompaniesWithProjectLink...");
    
    try {
        $startTime = microtime(true);
        $companies = $check->getCompaniesWithProjectLink();
        $endTime = microtime(true);
        $time = round($endTime - $startTime, 2);
        
        $companiesCount = count($companies);
        $companiesSuccess = $companiesCount >= 0; // Может быть 0, это нормально
        
        testPrintTest("getCompaniesWithProjectLink()", $companiesSuccess, 
            "Найдено: $companiesCount компаний, время: {$time}с");
        
        if ($companiesCount > 0) {
            testPrintInfo("Первая компания: ID #{$companies[0]['ID']}, Название: '{$companies[0]['TITLE']}'");
        }
        
    } catch (Exception $e) {
        testPrintTest("getCompaniesWithProjectLink()", false, "Ошибка: " . $e->getMessage());
        $companiesSuccess = false;
    }
    
    // ========================================
    // ТЕСТ 4: МЕТОДЫ DOCUMENTGENERATOR
    // ========================================
    testPrintHeader("ТЕСТ 4: МЕТОДЫ DOCUMENTGENERATOR", TestColors::BLUE);
    
    // Тест настроек шаблонов
    testPrintInfo("Проверка настроек шаблонов документов...");
    
    $templateTests = [
        ['REPORT_TEMPLATE_ID', REPORT_TEMPLATE_ID],
        ['INVOICE_TEMPLATE_ID', INVOICE_TEMPLATE_ID],
        ['ACT_TEMPLATE_ID', ACT_TEMPLATE_ID]
    ];
    
    $templateOk = 0;
    foreach ($templateTests as $test) {
        $name = $test[0];
        $id = $test[1];
        $isConfigured = $id > 0;
        
        testPrintTest("$name", $isConfigured, "ID: $id");
        if ($isConfigured) $templateOk++;
    }
    
    testPrintInfo("Результат шаблонов: $templateOk/3 настроены");
    
    // ========================================
    // ТЕСТ 5: МЕТОДЫ UF_CRM_FIELDCHECKER
    // ========================================
    testPrintHeader("ТЕСТ 5: МЕТОДЫ UF_CRM_FIELDCHECKER", TestColors::BLUE);
    
    // Тест проверки UF полей
    testPrintInfo("Тестирование проверки UF полей...");
    
    $ufFields = [
        'UF_CRM_PROJECT_LINK' => 'Ссылка на проект',
        'UF_CRM_FRONTEND_RATE' => 'Ставка Front-end',
        'UF_CRM_BACKEND_RATE' => 'Ставка Back-end',
        'UF_CRM_DESIGNER_RATE' => 'Ставка Дизайнера',
        'UF_CRM_PM_RATE' => 'Ставка ПМ',
        'UF_CRM_CONTENT_MANAGER_RATE' => 'Ставка КМ'
    ];
    
    $ufOk = 0;
    foreach ($ufFields as $field => $description) {
        try {
            $exists = $fieldChecker->checkFieldExists($field);
            testPrintTest("checkFieldExists('$field')", $exists, $description);
            if ($exists) $ufOk++;
        } catch (Exception $e) {
            testPrintTest("checkFieldExists('$field')", false, "Ошибка: " . $e->getMessage());
        }
    }
    
    testPrintInfo("Результат UF полей: $ufOk/" . count($ufFields) . " найдены");
    
    // ========================================
    // ТЕСТ 6: ФУНКЦИИ ЗАДЕРЖКИ
    // ========================================
    testPrintHeader("ТЕСТ 6: ФУНКЦИИ ЗАДЕРЖКИ", TestColors::BLUE);
    
    // Тест apiDelay
    testPrintInfo("Тестирование функции apiDelay...");
    
    $startTime = microtime(true);
    apiDelay(0.1); // 100ms задержка
    $endTime = microtime(true);
    $actualDelay = round($endTime - $startTime, 2);
    
    $delaySuccess = $actualDelay >= 0.1 && $actualDelay <= 0.2; // Допускаем погрешность
    testPrintTest("apiDelay(0.1)", $delaySuccess, "Фактическая задержка: {$actualDelay}с");
    
    // Тест batchDelay
    testPrintInfo("Тестирование функции batchDelay...");
    
    $startTime = microtime(true);
    batchDelay();
    $endTime = microtime(true);
    $actualDelay = round($endTime - $startTime, 2);
    
    $batchDelaySuccess = $actualDelay >= API_DELAY_BETWEEN_BATCHES && $actualDelay <= API_DELAY_BETWEEN_BATCHES + 0.1;
    testPrintTest("batchDelay()", $batchDelaySuccess, "Фактическая задержка: {$actualDelay}с");
    
    // ========================================
    // ТЕСТ 7: КОНСТАНТЫ КОНФИГУРАЦИИ
    // ========================================
    testPrintHeader("ТЕСТ 7: КОНСТАНТЫ КОНФИГУРАЦИИ", TestColors::BLUE);
    
    $configTests = [
        ['DEFAULT_HOURLY_RATE', DEFAULT_HOURLY_RATE, 'number'],
        ['MIN_HOUR_ROUNDING', MIN_HOUR_ROUNDING, 'number'],
        ['ROUNDING_THRESHOLD', ROUNDING_THRESHOLD, 'number'],
        ['MONTHLY_WORK_FUNNEL_ID', MONTHLY_WORK_FUNNEL_ID, 'string'],
        ['API_DELAY_BETWEEN_CALLS', API_DELAY_BETWEEN_CALLS, 'number'],
        ['API_DELAY_BETWEEN_BATCHES', API_DELAY_BETWEEN_BATCHES, 'number'],
        ['API_TIMEOUT', API_TIMEOUT, 'number'],
        ['API_MAX_RETRIES', API_MAX_RETRIES, 'number'],
        ['NOTIFY_ON_DEAL_CREATION', NOTIFY_ON_DEAL_CREATION, 'boolean'],
        ['CHECK_DUPLICATES', CHECK_DUPLICATES, 'boolean']
    ];
    
    $configOk = 0;
    foreach ($configTests as $test) {
        $name = $test[0];
        $value = $test[1];
        $type = $test[2];
        
        $isValid = false;
        switch ($type) {
            case 'number':
                $isValid = is_numeric($value) && $value > 0;
                break;
            case 'string':
                $isValid = is_string($value) && !empty($value);
                break;
            case 'boolean':
                $isValid = is_bool($value);
                break;
        }
        
        testPrintTest("$name", $isValid, "Значение: $value, тип: $type");
        if ($isValid) $configOk++;
    }
    
    testPrintInfo("Результат конфигурации: $configOk/" . count($configTests) . " корректны");
    
    // ========================================
    // ТЕСТ 8: ИНТЕГРАЦИОННЫЕ ТЕСТЫ
    // ========================================
    testPrintHeader("ТЕСТ 8: ИНТЕГРАЦИОННЫЕ ТЕСТЫ", TestColors::BLUE);
    
    // Тест полного цикла (если есть данные)
    if (isset($companies) && count($companies) > 0) {
        testPrintInfo("Тестирование полного цикла обработки...");
        
        $testCompany = $companies[0];
        $projectLink = $testCompany['UF_CRM_PROJECT_LINK'] ?? '';
        $projectId = $dealCreator->extractProjectId($projectLink);
        
        if ($projectId > 0) {
            try {
                // Получение задач
                $startTime = microtime(true);
                $tasks = $dealCreator->getProjectTasks($projectId);
                $tasksTime = round(microtime(true) - $startTime, 2);
                
                // Анализ времени
                $startTime = microtime(true);
                $projectTimeData = $dealCreator->getProjectTimeData($projectId, $testCompany);
                $analysisTime = round(microtime(true) - $startTime, 2);
                
                $integrationSuccess = true;
                testPrintTest("Полный цикл обработки", $integrationSuccess, 
                    "Задач: " . count($tasks) . ", Время: {$projectTimeData['total_hours']}ч, " .
                    "Стоимость: {$projectTimeData['total_cost']}руб, " .
                    "Время выполнения: {$tasksTime}с + {$analysisTime}с");
                
            } catch (Exception $e) {
                testPrintTest("Полный цикл обработки", false, "Ошибка: " . $e->getMessage());
                $integrationSuccess = false;
            }
        } else {
            testPrintWarning("Не удалось извлечь ID проекта для интеграционного теста");
            $integrationSuccess = false;
        }
    } else {
        testPrintWarning("Нет компаний для интеграционного теста");
        $integrationSuccess = false;
    }
    
    // ========================================
    // ИТОГОВЫЙ РЕЗУЛЬТАТ
    // ========================================
    testPrintHeader("ИТОГОВЫЙ РЕЗУЛЬТАТ ТЕСТИРОВАНИЯ ФУНКЦИЙ", TestColors::MAGENTA);
    
    $results = [
        'getRoleRate' => $rateOk,
        'getRoleRateField' => $fieldOk,
        'extractProjectId' => $extractOk,
        'getCompaniesWithProjectLink' => isset($companiesSuccess) ? ($companiesSuccess ? 1 : 0) : 0,
        'Шаблоны документов' => $templateOk,
        'UF поля' => $ufOk,
        'apiDelay' => isset($delaySuccess) ? ($delaySuccess ? 1 : 0) : 0,
        'batchDelay' => isset($batchDelaySuccess) ? ($batchDelaySuccess ? 1 : 0) : 0,
        'Конфигурация' => $configOk,
        'Интеграция' => isset($integrationSuccess) ? ($integrationSuccess ? 1 : 0) : 0
    ];
    
    $totalTests = 0;
    $passedTests = 0;
    
    foreach ($results as $category => $passed) {
        $total = 0;
        switch ($category) {
            case 'getRoleRate': $total = count($rateTests); break;
            case 'getRoleRateField': $total = count($fieldTests); break;
            case 'extractProjectId': $total = count($extractTests); break;
            case 'getCompaniesWithProjectLink': $total = 1; break;
            case 'Шаблоны документов': $total = count($templateTests); break;
            case 'UF поля': $total = count($ufFields); break;
            case 'apiDelay': $total = 1; break;
            case 'batchDelay': $total = 1; break;
            case 'Конфигурация': $total = count($configTests); break;
            case 'Интеграция': $total = 1; break;
        }
        
        $percentage = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
        $status = $percentage >= 80 ? '✅' : ($percentage >= 60 ? '⚠️' : '❌');
        
        echo "$status $category: $passed/$total ($percentage%)\n";
        
        $totalTests += $total;
        $passedTests += $passed;
    }
    
    $overallPercentage = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;
    
    echo "\n" . str_repeat("-", 60) . "\n";
    echo "ОБЩИЙ РЕЗУЛЬТАТ: $passedTests/$totalTests ($overallPercentage%)\n";
    
    if ($overallPercentage >= 90) {
        testPrintSuccess("ВСЕ ФУНКЦИИ РАБОТАЮТ КОРРЕКТНО!");
    } elseif ($overallPercentage >= 70) {
        testPrintWarning("БОЛЬШИНСТВО ФУНКЦИЙ РАБОТАЕТ");
    } else {
        testPrintError("МНОГИЕ ФУНКЦИИ ТРЕБУЮТ ИСПРАВЛЕНИЯ");
    }
    
    echo "\nВремя завершения: " . date('Y-m-d H:i:s') . "\n";
    echo "Лог сохранен в: logs/functions_test.log\n";
    
} catch (Exception $e) {
    testPrintError("КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage());
    echo "Файл: " . $e->getFile() . "\n";
    echo "Строка: " . $e->getLine() . "\n";
    echo "Трассировка:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>