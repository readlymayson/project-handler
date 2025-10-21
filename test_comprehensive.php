<?php
/**
 * Комплексный тест всего функционала системы
 * Проверяет все компоненты: API, создание сделок, генерацию документов, UF поля
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'logs/error.log');
error_reporting(E_ALL);

// Подключаем общие функции тестирования
require_once 'test_helpers.php';

try {
    testPrintHeader("КОМПЛЕКСНОЕ ТЕСТИРОВАНИЕ СИСТЕМЫ", TestColors::MAGENTA);
    echo "Время запуска: " . date('Y-m-d H:i:s') . "\n";
    echo "Версия PHP: " . PHP_VERSION . "\n\n";

    // ========================================
    // ПРОВЕРКА ЗАВИСИМОСТЕЙ
    // ========================================
    testPrintCompactHeader("1. ЗАВИСИМОСТИ", TestColors::BLUE);
    
    $requiredFiles = [
        'set.php' => 'Настройки подключения',
        'class.php' => 'Класс ProjectCheck',
        'DealCreator.php' => 'Класс DealCreator',
        'DocumentGenerator.php' => 'Класс DocumentGenerator',
        'UF_CRM_FieldChecker.php' => 'Класс UF_CRM_FieldChecker',
        'config.php' => 'Конфигурация системы',
        '../logger/class.php' => 'Класс Logger',
        '../require/usualClass.php' => 'Класс Usual'
    ];
    
    $filesOk = 0;
    $totalFiles = count($requiredFiles);
    
    foreach ($requiredFiles as $file => $description) {
        $exists = file_exists($file);
        if ($exists) $filesOk++;
    }
    
    if ($filesOk < $totalFiles) {
        testPrintError("Не все необходимые файлы найдены!");
        exit(1);
    }
    
    testPrintSummary("Файлы", $filesOk, $totalFiles);
    
    echo "\n";
    
    // ========================================
    // ПОДКЛЮЧЕНИЕ КЛАССОВ
    // ========================================
    testPrintHeader("2. ПОДКЛЮЧЕНИЕ КЛАССОВ", TestColors::BLUE);
    
    try {
        require_once 'set.php';
        require_once '../require/usualClass.php';
        require_once 'class.php';
        require_once 'config.php';
        require_once 'DealCreator.php';
        require_once 'DocumentGenerator.php';
        require_once 'UF_CRM_FieldChecker.php';
        require_once '../logger/class.php';
        
        testPrintSuccess("Все классы успешно подключены");
    } catch (Exception $e) {
        testPrintError("Ошибка подключения классов: " . $e->getMessage());
        exit(1);
    }
    
    // ========================================
    // ИНИЦИАЛИЗАЦИЯ ОБЪЕКТОВ
    // ========================================
    testPrintHeader("3. ИНИЦИАЛИЗАЦИЯ ОБЪЕКТОВ", TestColors::BLUE);
    
    try {
        $call = new Usual(BITRIX24_WEBHOOK_URL);
        $logger = new Logger('comprehensive_test.log', __DIR__ . '/logs');
        $check = new ProjectCheck($call, $logger);
        $dealCreator = new DealCreator($call, $logger);
        $documentGenerator = new DocumentGenerator($call, $logger);
        $fieldChecker = new UF_CRM_FieldChecker($call, $logger);
        
        testPrintSuccess("Все объекты успешно инициализированы");
    } catch (Exception $e) {
        testPrintError("Ошибка инициализации: " . $e->getMessage());
        exit(1);
    }
    
    // ========================================
    // ТЕСТ КОНФИГУРАЦИИ
    // ========================================
    testPrintHeader("4. ТЕСТ КОНФИГУРАЦИИ", TestColors::BLUE);
    
    $configTests = [
        'DEFAULT_HOURLY_RATE' => [DEFAULT_HOURLY_RATE, 'number', 'Базовая ставка'],
        'MIN_HOUR_ROUNDING' => [MIN_HOUR_ROUNDING, 'number', 'Минимальное округление'],
        'ROUNDING_THRESHOLD' => [ROUNDING_THRESHOLD, 'number', 'Порог округления'],
        'MONTHLY_WORK_FUNNEL_ID' => [MONTHLY_WORK_FUNNEL_ID, 'string', 'ID воронки'],
        'API_DELAY_BETWEEN_CALLS' => [API_DELAY_BETWEEN_CALLS, 'number', 'Задержка API'],
        'NOTIFY_ON_DEAL_CREATION' => [NOTIFY_ON_DEAL_CREATION, 'boolean', 'Уведомления']
    ];
    
    $configOk = 0;
    foreach ($configTests as $name => $test) {
        $value = $test[0];
        $type = $test[1];
        $description = $test[2];
        
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
        
        testPrintTest("$description ($name)", $isValid, "Значение: $value");
        if ($isValid) $configOk++;
    }
    
    testPrintInfo("Конфигурация: $configOk/" . count($configTests) . " параметров корректны");
    
    // ========================================
    // ТЕСТ API ПОДКЛЮЧЕНИЯ
    // ========================================
    testPrintHeader("5. ТЕСТ API ПОДКЛЮЧЕНИЯ", TestColors::BLUE);
    
    try {
        testPrintInfo("Тестирование подключения к Bitrix24 API...");
        $startTime = microtime(true);
        
        // Простой тест API
        $result = $call->callBitrix24API('user.current', []);
        $endTime = microtime(true);
        $responseTime = round($endTime - $startTime, 2);
        
        if (isset($result['result'])) {
            testPrintSuccess("API подключение работает (время ответа: {$responseTime}с)");
            testPrintInfo("Пользователь: " . ($result['result']['NAME'] ?? 'Неизвестно'));
        } else {
            testPrintError("API вернул ошибку: " . json_encode($result));
        }
    } catch (Exception $e) {
        testPrintError("Ошибка API: " . $e->getMessage());
    }
    
    // ========================================
    // ТЕСТ ФУНКЦИЙ ИЗВЛЕЧЕНИЯ ID ПРОЕКТА
    // ========================================
    testPrintHeader("6. ТЕСТ ИЗВЛЕЧЕНИЯ ID ПРОЕКТА", TestColors::BLUE);
    
    $projectIdTests = [
        '168' => 168,
        'https://akvilon-marketing.bitrix24.ru/workgroups/group/456/' => 456,
        '/workgroups/group/789/' => 789,
        'group_id=101' => 101,
        'id=202' => 202,
        'some text with 303 number' => 303,
        '' => 0,
        'no numbers here' => 0,
        'https://example.com/group/999/' => 999
    ];
    
    $projectIdOk = 0;
    foreach ($projectIdTests as $input => $expected) {
        $result = $dealCreator->extractProjectId($input);
        $isCorrect = $result === $expected;
        testPrintTest("Извлечение ID из '$input'", $isCorrect, "Получено: $result, ожидалось: $expected");
        if ($isCorrect) $projectIdOk++;
    }
    
    testPrintInfo("Извлечение ID: $projectIdOk/" . count($projectIdTests) . " тестов пройдено");
    
    // ========================================
    // ТЕСТ ФУНКЦИЙ ТАРИФОВ
    // ========================================
    testPrintHeader("7. ТЕСТ ФУНКЦИЙ ТАРИФОВ", TestColors::BLUE);
    
    $testCompany = [
        'UF_CRM_FRONTEND_RATE' => '1500|RUB',
        'UF_CRM_BACKEND_RATE' => '2000|RUB',
        'UF_CRM_DESIGNER_RATE' => '1200|RUB',
        'UF_CRM_PM_RATE' => '2500|RUB',
        'UF_CRM_CONTENT_MANAGER_RATE' => '1000|RUB'
    ];
    
    $rateTests = [
        'Front-end разработчик' => 1500,
        'Back-end разработчик' => 2000,
        'Дизайнер' => 1200,
        'Проект-менеджер' => 2500,
        'Контент-менеджер' => 1000,
        'Front-end разработчик #2' => 1500,
        'Неизвестная роль' => DEFAULT_HOURLY_RATE
    ];
    
    $rateOk = 0;
    foreach ($rateTests as $role => $expectedRate) {
        $actualRate = getRoleRate($role, $testCompany);
        $isCorrect = $actualRate == $expectedRate;
        testPrintTest("Тариф для '$role'", $isCorrect, "Получено: $actualRate, ожидалось: $expectedRate");
        if ($isCorrect) $rateOk++;
    }
    
    testPrintInfo("Тарифы: $rateOk/" . count($rateTests) . " тестов пройдено");
    
    // ========================================
    // ТЕСТ МАППИНГА ДОЛЖНОСТЕЙ
    // ========================================
    testPrintHeader("8. ТЕСТ МАППИНГА ДОЛЖНОСТЕЙ", TestColors::BLUE);
    
    // Копируем логику из DealCreator для тестирования
    function testMapPositionToRole($position) {
        if (empty($position)) return 'Неизвестно';
        $position = strtolower(trim($position));
        
        $positionMappings = [
            'frontend' => 'Front-end разработчик',
            'front-end' => 'Front-end разработчик',
            'фронтенд' => 'Front-end разработчик',
            'backend' => 'Back-end разработчик',
            'back-end' => 'Back-end разработчик',
            'бэкенд' => 'Back-end разработчик',
            'дизайнер' => 'Дизайнер',
            'designer' => 'Дизайнер',
            'проект-менеджер' => 'Проект-менеджер',
            'project manager' => 'Проект-менеджер',
            'пм' => 'Проект-менеджер',
            'контент-менеджер' => 'Контент-менеджер',
            'content manager' => 'Контент-менеджер'
        ];
        
        if (isset($positionMappings[$position])) {
            return $positionMappings[$position];
        }
        
        foreach ($positionMappings as $key => $role) {
            if (strpos($position, $key) !== false) {
                return $role;
            }
        }
        
        return ucfirst($position);
    }
    
    $positionTests = [
        'Frontend разработчик' => 'Front-end разработчик',
        'Backend разработчик' => 'Back-end разработчик',
        'Дизайнер' => 'Дизайнер',
        'Проект-менеджер' => 'Проект-менеджер',
        'Контент-менеджер' => 'Контент-менеджер',
        'frontend' => 'Front-end разработчик',
        'backend' => 'Back-end разработчик',
        'дизайнер' => 'Дизайнер',
        'пм' => 'Проект-менеджер',
        'км' => 'Контент-менеджер',
        'UI/UX дизайнер' => 'Дизайнер',
        'Project Manager' => 'Проект-менеджер',
        'Неизвестная должность' => 'Неизвестная должность',
        '' => 'Неизвестно'
    ];
    
    $positionOk = 0;
    foreach ($positionTests as $input => $expected) {
        $result = testMapPositionToRole($input);
        $isCorrect = $result === $expected;
        testPrintTest("Маппинг '$input'", $isCorrect, "Получено: '$result', ожидалось: '$expected'");
        if ($isCorrect) $positionOk++;
    }
    
    testPrintInfo("Маппинг должностей: $positionOk/" . count($positionTests) . " тестов пройдено");
    
    // ========================================
    // ТЕСТ ПОЛУЧЕНИЯ КОМПАНИЙ
    // ========================================
    testPrintHeader("9. ТЕСТ ПОЛУЧЕНИЯ КОМПАНИЙ", TestColors::BLUE);
    
    try {
        testPrintInfo("Получение компаний с привязанными проектами...");
        $startTime = microtime(true);
        
        $companies = $check->getCompaniesWithProjectLink();
        $endTime = microtime(true);
        $responseTime = round($endTime - $startTime, 2);
        
        $companiesCount = count($companies);
        testPrintSuccess("Получено компаний: $companiesCount (время: {$responseTime}с)");
        
        if ($companiesCount > 0) {
            testPrintInfo("Первая компания: ID #{$companies[0]['ID']}, Название: '{$companies[0]['TITLE']}'");
            testPrintInfo("Ссылка на проект: '{$companies[0]['UF_CRM_PROJECT_LINK']}'");
        } else {
            testPrintWarning("Нет компаний с привязанными проектами для тестирования");
        }
    } catch (Exception $e) {
        testPrintError("Ошибка получения компаний: " . $e->getMessage());
    }
    
    // ========================================
    // ТЕСТ ПОЛУЧЕНИЯ ЗАДАЧ ПРОЕКТА
    // ========================================
    if (isset($companies) && count($companies) > 0) {
        testPrintHeader("10. ТЕСТ ПОЛУЧЕНИЯ ЗАДАЧ ПРОЕКТА", TestColors::BLUE);
        
        $testCompany = $companies[0];
        $projectLink = $testCompany['UF_CRM_PROJECT_LINK'] ?? '';
        $projectId = $dealCreator->extractProjectId($projectLink);
        
        if ($projectId > 0) {
            try {
                testPrintInfo("Получение задач проекта ID: $projectId...");
                $startTime = microtime(true);
                
                $tasks = $dealCreator->getProjectTasks($projectId);
                $endTime = microtime(true);
                $responseTime = round($endTime - $startTime, 2);
                
                $tasksCount = count($tasks);
                testPrintSuccess("Получено задач: $tasksCount (время: {$responseTime}с)");
                
                if ($tasksCount > 0) {
                    testPrintInfo("Первая задача: ID #{$tasks[0]['id']}, Название: '{$tasks[0]['title']}'");
                    testPrintInfo("Время в логах: {$tasks[0]['timeSpentInLogs']} сек");
                } else {
                    testPrintWarning("Нет задач в проекте для тестирования");
                }
            } catch (Exception $e) {
                testPrintError("Ошибка получения задач: " . $e->getMessage());
            }
        } else {
            testPrintWarning("Не удалось извлечь ID проекта из ссылки: '$projectLink'");
        }
    }
    
    // ========================================
    // ТЕСТ АНАЛИЗА ВРЕМЕНИ ПО ПРОЕКТУ
    // ========================================
    if (isset($projectId) && $projectId > 0) {
        testPrintHeader("11. ТЕСТ АНАЛИЗА ВРЕМЕНИ ПО ПРОЕКТУ", TestColors::BLUE);
        
        try {
            testPrintInfo("Анализ времени по проекту...");
            $startTime = microtime(true);
            
            $projectTimeData = $dealCreator->getProjectTimeData($projectId, $testCompany);
            $endTime = microtime(true);
            $responseTime = round($endTime - $startTime, 2);
            
            testPrintSuccess("Анализ завершен (время: {$responseTime}с)");
            testPrintInfo("Общее время: {$projectTimeData['total_hours']} часов");
            testPrintInfo("Общая стоимость: {$projectTimeData['total_cost']} руб.");
            testPrintInfo("Количество ролей: " . count($projectTimeData['roles_time']));
            
            if (!empty($projectTimeData['roles_time'])) {
                testPrintInfo("Детализация по ролям:");
                foreach ($projectTimeData['roles_time'] as $role => $timeData) {
                    $rate = getRoleRate($role, $testCompany);
                    $cost = $timeData['decimal_hours'] * $rate;
                    testPrintInfo("  • $role: {$timeData['decimal_hours']} ч × $rate руб/ч = " . number_format($cost, 2) . " руб");
                }
            }
        } catch (Exception $e) {
            testPrintError("Ошибка анализа времени: " . $e->getMessage());
        }
    }
    
    // ========================================
    // ТЕСТ UF ПОЛЕЙ CRM
    // ========================================
    testPrintHeader("12. ТЕСТ UF ПОЛЕЙ CRM", TestColors::BLUE);
    
    try {
        testPrintInfo("Проверка UF полей компаний...");
        
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
                testPrintTest("$description ($field)", $exists);
                if ($exists) $ufOk++;
            } catch (Exception $e) {
                testPrintTest("$description ($field)", false, "Ошибка: " . $e->getMessage());
            }
        }
        
        testPrintInfo("UF поля: $ufOk/" . count($ufFields) . " найдены");
    } catch (Exception $e) {
        testPrintError("Ошибка проверки UF полей: " . $e->getMessage());
    }
    
    // ========================================
    // ТЕСТ ГЕНЕРАЦИИ ДОКУМЕНТОВ
    // ========================================
    testPrintHeader("13. ТЕСТ ГЕНЕРАЦИИ ДОКУМЕНТОВ", TestColors::BLUE);
    
    // Проверяем настройки генератора документов
    $docSettings = $documentGenerator->checkDocumentGeneratorSettings();
    
    $docTests = [
        'Генератор включен' => $docSettings['enabled'],
        'Автогенерация' => $docSettings['auto_generate'],
        'Генерация при создании' => $docSettings['generate_on_creation'],
        'Генерация при обновлении' => $docSettings['generate_on_update']
    ];
    
    $docOk = 0;
    foreach ($docTests as $test => $status) {
        testPrintTest($test, $status);
        if ($status) $docOk++;
    }
    
    // Проверяем шаблоны
    $templateTests = [
        'Отчет' => $docSettings['templates']['report'],
        'Счет' => $docSettings['templates']['invoice'],
        'Акт' => $docSettings['templates']['act']
    ];
    
    $templateOk = 0;
    foreach ($templateTests as $template => $id) {
        $isConfigured = $id > 0;
        testPrintTest("Шаблон $template", $isConfigured, "ID: $id");
        if ($isConfigured) $templateOk++;
    }
    
    testPrintInfo("Настроено шаблонов: $templateOk/3");
    testPrintInfo("Готовность генератора: " . ($docSettings['is_ready'] ? 'Да' : 'Нет'));
    
    // ========================================
    // ТЕСТ НАСТРОЕК УВЕДОМЛЕНИЙ
    // ========================================
    testPrintHeader("14. ТЕСТ НАСТРОЕК УВЕДОМЛЕНИЙ", TestColors::BLUE);
    
    $notificationTests = [
        'NOTIFY_ON_DEAL_CREATION' => NOTIFY_ON_DEAL_CREATION,
        'NOTIFY_USERS' => NOTIFY_USERS
    ];
    
    $notifyOk = 0;
    foreach ($notificationTests as $setting => $value) {
        $isValid = false;
        switch ($setting) {
            case 'NOTIFY_ON_DEAL_CREATION':
                $isValid = is_bool($value);
                break;
            case 'NOTIFY_USERS':
                $isValid = is_array($value) && !empty($value);
                break;
        }
        
        testPrintTest("$setting", $isValid, "Значение: " . json_encode($value));
        if ($isValid) $notifyOk++;
    }
    
    testPrintInfo("Настройки уведомлений: $notifyOk/2 корректны");
    
    // ========================================
    // ТЕСТ ПРОИЗВОДИТЕЛЬНОСТИ
    // ========================================
    testPrintHeader("15. ТЕСТ ПРОИЗВОДИТЕЛЬНОСТИ", TestColors::BLUE);
    
    $performanceTests = [
        'API_DELAY_BETWEEN_CALLS' => API_DELAY_BETWEEN_CALLS,
        'API_DELAY_BETWEEN_BATCHES' => API_DELAY_BETWEEN_BATCHES,
        'API_TIMEOUT' => API_TIMEOUT,
        'API_MAX_RETRIES' => API_MAX_RETRIES
    ];
    
    $perfOk = 0;
    foreach ($performanceTests as $setting => $value) {
        $isValid = is_numeric($value) && $value > 0;
        testPrintTest("$setting", $isValid, "Значение: $value");
        if ($isValid) $perfOk++;
    }
    
    testPrintInfo("Настройки производительности: $perfOk/4 корректны");
    
    // ========================================
    // ИТОГОВЫЙ РЕЗУЛЬТАТ
    // ========================================
    testPrintCompactHeader("ИТОГОВЫЙ РЕЗУЛЬТАТ", TestColors::MAGENTA);
    
    $totalTests = 0;
    $passedTests = 0;
    
    // Подсчитываем результаты
    $results = [
        'Файлы' => $filesOk,
        'Конфигурация' => $configOk,
        'Извлечение ID' => $projectIdOk,
        'Тарифы' => $rateOk,
        'Маппинг должностей' => $positionOk,
        'UF поля' => $ufOk,
        'Документы' => $docOk,
        'Уведомления' => $notifyOk,
        'Производительность' => $perfOk
    ];
    
    foreach ($results as $category => $passed) {
        $total = 0;
        switch ($category) {
            case 'Файлы': $total = $totalFiles; break;
            case 'Конфигурация': $total = count($configTests); break;
            case 'Извлечение ID': $total = count($projectIdTests); break;
            case 'Тарифы': $total = count($rateTests); break;
            case 'Маппинг должностей': $total = count($positionTests); break;
            case 'UF поля': $total = count($ufFields); break;
            case 'Документы': $total = count($documentTests); break;
            case 'Уведомления': $total = count($notificationTests); break;
            case 'Производительность': $total = count($performanceTests); break;
        }
        
        testPrintSummary($category, $passed, $total);
        
        $totalTests += $total;
        $passedTests += $passed;
    }
    
    $overallPercentage = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;
    
    echo "\n" . str_repeat("-", 50) . "\n";
    testPrintSummary("ОБЩИЙ РЕЗУЛЬТАТ", $passedTests, $totalTests);
    
    echo "\n";
    
    if ($overallPercentage >= 90) {
        testPrintSuccess("СИСТЕМА ГОТОВА К РАБОТЕ!");
    } elseif ($overallPercentage >= 70) {
        testPrintWarning("СИСТЕМА РАБОТАЕТ С ПРЕДУПРЕЖДЕНИЯМИ");
    } else {
        testPrintError("СИСТЕМА ТРЕБУЕТ ДОРАБОТКИ");
    }
    
    echo "\nВремя завершения: " . date('Y-m-d H:i:s') . "\n";
    echo "Лог: logs/comprehensive_test.log\n";
    
} catch (Exception $e) {
    testPrintError("КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage());
    echo "Файл: " . $e->getFile() . "\n";
    echo "Строка: " . $e->getLine() . "\n";
    echo "Трассировка:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>
