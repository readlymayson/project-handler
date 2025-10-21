<?php
/**
 * Тест сценариев использования системы
 * Проверяет реальные сценарии: создание сделок, генерацию документов, обработку ошибок
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'logs/error.log');
error_reporting(E_ALL);

// Подключаем общие функции тестирования
require_once 'test_helpers.php';

try {
    testPrintHeader("ТЕСТИРОВАНИЕ СЦЕНАРИЕВ ИСПОЛЬЗОВАНИЯ", TestColors::MAGENTA);
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
    $logger = new Logger('scenarios_test.log', __DIR__ . '/logs');
    $check = new ProjectCheck($call, $logger);
    $dealCreator = new DealCreator($call, $logger);
    $documentGenerator = new DocumentGenerator($call, $logger);
    $fieldChecker = new UF_CRM_FieldChecker($call, $logger);

    // ========================================
    // СЦЕНАРИЙ 1: ПОЛНЫЙ ЦИКЛ СОЗДАНИЯ СДЕЛКИ
    // ========================================
    testPrintHeader("СЦЕНАРИЙ 1: ПОЛНЫЙ ЦИКЛ СОЗДАНИЯ СДЕЛКИ", TestColors::BLUE);
    
    try {
        // Шаг 1: Получение компаний
        testPrintInfo("Шаг 1: Получение компаний с проектами...");
        $companies = $check->getCompaniesWithProjectLink();
        
        if (empty($companies)) {
            testPrintWarning("Нет компаний для тестирования");
            $scenario1Success = false;
        } else {
            testPrintSuccess("Найдено компаний: " . count($companies));
            $testCompany = $companies[0];
            testPrintInfo("Тестируем компанию: #{$testCompany['ID']} - {$testCompany['TITLE']}");
            
            // Шаг 2: Извлечение ID проекта
            testPrintInfo("Шаг 2: Извлечение ID проекта...");
            $projectLink = $testCompany['UF_CRM_PROJECT_LINK'] ?? '';
            $projectId = $dealCreator->extractProjectId($projectLink);
            
            if ($projectId == 0) {
                testPrintWarning("Не удалось извлечь ID проекта из: '$projectLink'");
                $scenario1Success = false;
            } else {
                testPrintSuccess("ID проекта: $projectId");
                
                // Шаг 3: Получение задач
                testPrintInfo("Шаг 3: Получение задач проекта...");
                $tasks = $dealCreator->getProjectTasks($projectId);
                testPrintSuccess("Найдено задач: " . count($tasks));
                
                // Шаг 4: Анализ времени
                testPrintInfo("Шаг 4: Анализ времени по проекту...");
                $projectTimeData = $dealCreator->getProjectTimeData($projectId, $testCompany);
                
                testPrintInfo("Общее время: {$projectTimeData['total_hours']} часов");
                testPrintInfo("Общая стоимость: {$projectTimeData['total_cost']} руб.");
                testPrintInfo("Количество ролей: " . count($projectTimeData['roles_time']));
                
                if ($projectTimeData['total_hours'] > 0) {
                    testPrintSuccess("Есть данные для создания сделки");
                    $scenario1Success = true;
                } else {
                    testPrintWarning("Нет времени для создания сделки");
                    $scenario1Success = false;
                }
            }
        }
        
        testPrintScenario("Сценарий 1: Полный цикл", $scenario1Success);
        
    } catch (Exception $e) {
        testPrintError("Ошибка в сценарии 1: " . $e->getMessage());
        $scenario1Success = false;
    }
    
    // ========================================
    // СЦЕНАРИЙ 2: ОБРАБОТКА ОШИБОК
    // ========================================
    testPrintHeader("СЦЕНАРИЙ 2: ОБРАБОТКА ОШИБОК", TestColors::BLUE);
    
    $errorTests = [];
    
    // Тест 2.1: Неверный ID проекта
    try {
        testPrintInfo("Тест 2.1: Неверный ID проекта...");
        $invalidProjectId = 999999;
        $tasks = $dealCreator->getProjectTasks($invalidProjectId);
        $errorTests['Неверный ID проекта'] = count($tasks) == 0;
    } catch (Exception $e) {
        $errorTests['Неверный ID проекта'] = true; // Ошибка ожидаема
    }
    
    // Тест 2.2: Пустая ссылка на проект
    try {
        testPrintInfo("Тест 2.2: Пустая ссылка на проект...");
        $emptyLink = '';
        $projectId = $dealCreator->extractProjectId($emptyLink);
        $errorTests['Пустая ссылка'] = $projectId == 0;
    } catch (Exception $e) {
        $errorTests['Пустая ссылка'] = true;
    }
    
    // Тест 2.3: Неверный формат ссылки
    try {
        testPrintInfo("Тест 2.3: Неверный формат ссылки...");
        $invalidLink = 'not-a-valid-link';
        $projectId = $dealCreator->extractProjectId($invalidLink);
        $errorTests['Неверный формат'] = $projectId == 0;
    } catch (Exception $e) {
        $errorTests['Неверный формат'] = true;
    }
    
    foreach ($errorTests as $test => $result) {
        testPrintScenario("Обработка ошибки: $test", $result);
    }
    
    $scenario2Success = array_sum($errorTests) == count($errorTests);
    testPrintScenario("Сценарий 2: Обработка ошибок", $scenario2Success);
    
    // ========================================
    // СЦЕНАРИЙ 3: РАЗЛИЧНЫЕ ТИПЫ ПРОЕКТОВ
    // ========================================
    testPrintHeader("СЦЕНАРИЙ 3: РАЗЛИЧНЫЕ ТИПЫ ПРОЕКТОВ", TestColors::BLUE);
    
    $projectTypes = [];
    
    if (isset($companies) && count($companies) > 0) {
        testPrintInfo("Анализ различных типов проектов...");
        
        foreach (array_slice($companies, 0, 3) as $index => $company) {
            $projectLink = $company['UF_CRM_PROJECT_LINK'] ?? '';
            $projectId = $dealCreator->extractProjectId($projectLink);
            
            if ($projectId > 0) {
                try {
                    $tasks = $dealCreator->getProjectTasks($projectId);
                    $projectTimeData = $dealCreator->getProjectTimeData($projectId, $company);
                    
                    $projectTypes["Проект #" . ($index + 1)] = [
                        'tasks_count' => count($tasks),
                        'total_hours' => $projectTimeData['total_hours'],
                        'roles_count' => count($projectTimeData['roles_time']),
                        'has_data' => $projectTimeData['total_hours'] > 0
                    ];
                } catch (Exception $e) {
                    $projectTypes["Проект #" . ($index + 1)] = [
                        'error' => $e->getMessage()
                    ];
                }
            }
        }
        
        foreach ($projectTypes as $project => $data) {
            if (isset($data['error'])) {
                testPrintScenario("$project", false, "Ошибка: {$data['error']}");
            } else {
                $success = $data['has_data'];
                $details = "Задач: {$data['tasks_count']}, Время: {$data['total_hours']}ч, Ролей: {$data['roles_count']}";
                testPrintScenario("$project", $success, $details);
            }
        }
    } else {
        testPrintWarning("Нет проектов для анализа");
    }
    
    $scenario3Success = count($projectTypes) > 0;
    testPrintScenario("Сценарий 3: Различные типы проектов", $scenario3Success);
    
    // ========================================
    // СЦЕНАРИЙ 4: ПРОИЗВОДИТЕЛЬНОСТЬ
    // ========================================
    testPrintHeader("СЦЕНАРИЙ 4: ПРОИЗВОДИТЕЛЬНОСТЬ", TestColors::BLUE);
    
    $performanceTests = [];
    
    // Тест 4.1: Время получения компаний
    try {
        testPrintInfo("Тест 4.1: Время получения компаний...");
        $startTime = microtime(true);
        $companies = $check->getCompaniesWithProjectLink();
        $endTime = microtime(true);
        $time = round($endTime - $startTime, 2);
        
        $performanceTests['Получение компаний'] = $time < 10; // Менее 10 секунд
        testPrintInfo("Время: {$time}с, Результат: " . ($performanceTests['Получение компаний'] ? 'OK' : 'SLOW'));
    } catch (Exception $e) {
        $performanceTests['Получение компаний'] = false;
    }
    
    // Тест 4.2: Время получения задач (если есть проект)
    if (isset($projectId) && $projectId > 0) {
        try {
            testPrintInfo("Тест 4.2: Время получения задач...");
            $startTime = microtime(true);
            $tasks = $dealCreator->getProjectTasks($projectId);
            $endTime = microtime(true);
            $time = round($endTime - $startTime, 2);
            
            $performanceTests['Получение задач'] = $time < 30; // Менее 30 секунд
            testPrintInfo("Время: {$time}с, Результат: " . ($performanceTests['Получение задач'] ? 'OK' : 'SLOW'));
        } catch (Exception $e) {
            $performanceTests['Получение задач'] = false;
        }
    }
    
    // Тест 4.3: Время анализа времени
    if (isset($projectId) && $projectId > 0) {
        try {
            testPrintInfo("Тест 4.3: Время анализа времени...");
            $startTime = microtime(true);
            $projectTimeData = $dealCreator->getProjectTimeData($projectId, $testCompany);
            $endTime = microtime(true);
            $time = round($endTime - $startTime, 2);
            
            $performanceTests['Анализ времени'] = $time < 60; // Менее 60 секунд
            testPrintInfo("Время: {$time}с, Результат: " . ($performanceTests['Анализ времени'] ? 'OK' : 'SLOW'));
        } catch (Exception $e) {
            $performanceTests['Анализ времени'] = false;
        }
    }
    
    foreach ($performanceTests as $test => $result) {
        testPrintScenario("Производительность: $test", $result);
    }
    
    $scenario4Success = array_sum($performanceTests) == count($performanceTests);
    testPrintScenario("Сценарий 4: Производительность", $scenario4Success);
    
    // ========================================
    // СЦЕНАРИЙ 5: ГЕНЕРАЦИЯ ДОКУМЕНТОВ
    // ========================================
    testPrintHeader("СЦЕНАРИЙ 5: ГЕНЕРАЦИЯ ДОКУМЕНТОВ", TestColors::BLUE);
    
    $documentTests = [];
    
    // Проверка настроек шаблонов
    $templates = [
        'Отчет' => REPORT_TEMPLATE_ID,
        'Счет' => INVOICE_TEMPLATE_ID,
        'Акт' => ACT_TEMPLATE_ID
    ];
    
    foreach ($templates as $type => $id) {
        $isConfigured = $id > 0;
        $documentTests["Шаблон $type"] = $isConfigured;
        testPrintScenario("Шаблон $type", $isConfigured, "ID: $id");
    }
    
    // Тест генерации документов (если есть данные)
    if (isset($projectTimeData) && $projectTimeData['total_hours'] > 0) {
        try {
            testPrintInfo("Тест генерации документов...");
            
            // Создаем тестовую сделку для генерации документов
            $testDealId = 12345; // Тестовый ID
            
            $result = $documentGenerator->generateDocumentsForDeal(
                $testDealId,
                $testCompany,
                $projectTimeData,
                $projectId
            );
            
            $documentTests['Генерация документов'] = $result['status'] === 'success';
            testPrintScenario("Генерация документов", $documentTests['Генерация документов'], 
                $result['status'] === 'success' ? 'Успешно' : $result['message']);
                
        } catch (Exception $e) {
            $documentTests['Генерация документов'] = false;
            testPrintScenario("Генерация документов", false, "Ошибка: " . $e->getMessage());
        }
    } else {
        testPrintWarning("Нет данных для тестирования генерации документов");
    }
    
    $scenario5Success = array_sum($documentTests) >= count($documentTests) * 0.5; // 50% успеха
    testPrintScenario("Сценарий 5: Генерация документов", $scenario5Success);
    
    // ========================================
    // СЦЕНАРИЙ 6: UF ПОЛЯ И НАСТРОЙКИ
    // ========================================
    testPrintHeader("СЦЕНАРИЙ 6: UF ПОЛЯ И НАСТРОЙКИ", TestColors::BLUE);
    
    $ufTests = [];
    
    // Проверка UF полей
    $ufFields = [
        'UF_CRM_PROJECT_LINK' => 'Ссылка на проект',
        'UF_CRM_FRONTEND_RATE' => 'Ставка Front-end',
        'UF_CRM_BACKEND_RATE' => 'Ставка Back-end',
        'UF_CRM_DESIGNER_RATE' => 'Ставка Дизайнера',
        'UF_CRM_PM_RATE' => 'Ставка ПМ',
        'UF_CRM_CONTENT_MANAGER_RATE' => 'Ставка КМ'
    ];
    
    foreach ($ufFields as $field => $description) {
        try {
            $exists = $fieldChecker->checkFieldExists($field);
            $ufTests[$description] = $exists;
            testPrintScenario("UF поле: $description", $exists);
        } catch (Exception $e) {
            $ufTests[$description] = false;
            testPrintScenario("UF поле: $description", false, "Ошибка: " . $e->getMessage());
        }
    }
    
    $scenario6Success = array_sum($ufTests) >= count($ufTests) * 0.7; // 70% успеха
    testPrintScenario("Сценарий 6: UF поля и настройки", $scenario6Success);
    
    // ========================================
    // ИТОГОВЫЙ РЕЗУЛЬТАТ
    // ========================================
    testPrintHeader("ИТОГОВЫЙ РЕЗУЛЬТАТ СЦЕНАРИЕВ", TestColors::MAGENTA);
    
    $scenarios = [
        'Полный цикл создания сделки' => $scenario1Success,
        'Обработка ошибок' => $scenario2Success,
        'Различные типы проектов' => $scenario3Success,
        'Производительность' => $scenario4Success,
        'Генерация документов' => $scenario5Success,
        'UF поля и настройки' => $scenario6Success
    ];
    
    $passedScenarios = 0;
    foreach ($scenarios as $scenario => $success) {
        $status = $success ? '✅' : '❌';
        echo "$status $scenario\n";
        if ($success) $passedScenarios++;
    }
    
    $totalScenarios = count($scenarios);
    $successRate = round(($passedScenarios / $totalScenarios) * 100, 1);
    
    echo "\n" . str_repeat("-", 60) . "\n";
    echo "ПРОЙДЕНО СЦЕНАРИЕВ: $passedScenarios/$totalScenarios ($successRate%)\n";
    
    if ($successRate >= 80) {
        testPrintSuccess("СИСТЕМА ГОТОВА К ПРОДАКШЕНУ!");
    } elseif ($successRate >= 60) {
        testPrintWarning("СИСТЕМА ТРЕБУЕТ ДОРАБОТКИ");
    } else {
        testPrintError("СИСТЕМА НЕ ГОТОВА К ИСПОЛЬЗОВАНИЮ");
    }
    
    echo "\nВремя завершения: " . date('Y-m-d H:i:s') . "\n";
    echo "Лог сохранен в: logs/scenarios_test.log\n";
    
} catch (Exception $e) {
    testPrintError("КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage());
    echo "Файл: " . $e->getFile() . "\n";
    echo "Строка: " . $e->getLine() . "\n";
    echo "Трассировка:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>
