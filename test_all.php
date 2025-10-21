<?php
/**
 * Главный тестовый файл - запускает все тесты системы
 * Включает: комплексные тесты, сценарии, функции
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'logs/error.log');
error_reporting(E_ALL);

// Подключаем общие функции тестирования
require_once 'test_helpers.php';

function runTest($testFile, $testName) {
    $startTime = microtime(true);
    
    // Запускаем тест и захватываем вывод
    ob_start();
    $result = include $testFile;
    $output = ob_get_clean();
    
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);
    
    // Анализируем результат по выводу
    $success = true;
    if (strpos($output, '❌') !== false || strpos($output, 'ОШИБКА') !== false) {
        $success = false;
    }
    
    // Извлекаем краткую сводку из вывода
    $summary = extractTestSummary($output);
    
    return [
        'name' => $testName,
        'success' => $success,
        'time' => $executionTime,
        'output' => $output,
        'summary' => $summary
    ];
}

function extractTestSummary($output) {
    $lines = explode("\n", $output);
    $summary = [];
    
    // Ищем строки с результатами
    foreach ($lines as $line) {
        if (strpos($line, 'ОБЩИЙ РЕЗУЛЬТАТ:') !== false || 
            strpos($line, 'ПРОЙДЕНО СЦЕНАРИЕВ:') !== false ||
            strpos($line, 'ИТОГОВЫЙ РЕЗУЛЬТАТ:') !== false) {
            $summary[] = trim($line);
        }
    }
    
    return $summary;
}

try {
    testPrintHeader("ПОЛНОЕ ТЕСТИРОВАНИЕ СИСТЕМЫ", TestColors::MAGENTA);
    echo "Время запуска: " . date('Y-m-d H:i:s') . "\n";
    echo "Версия PHP: " . PHP_VERSION . "\n";
    echo "Рабочая директория: " . getcwd() . "\n\n";
    
    // Проверяем наличие тестовых файлов
    $testFiles = [
        'test_comprehensive.php' => 'Комплексное тестирование',
        'test_scenarios.php' => 'Тестирование сценариев',
        'test_functions.php' => 'Тестирование функций',
        'test_document_generator.php' => 'Тестирование генератора документов',
        'test_deal_creation.php' => 'Тестирование создания сделок',
        'test_combined_roles.php' => 'Тестирование ролей'
    ];
    
    $availableTests = [];
    foreach ($testFiles as $file => $name) {
        if (file_exists($file)) {
            $availableTests[$file] = $name;
        } else {
            testPrintWarning("Файл $file не найден");
        }
    }
    
    if (empty($availableTests)) {
        testPrintError("Нет доступных тестов для запуска!");
        exit(1);
    }
    
    testPrintInfo("Найдено тестов: " . count($availableTests));
    echo "\n";
    
    // Запускаем все доступные тесты
    $testResults = [];
    $totalTime = 0;
    
    echo "\n" . TestColors::BLUE . "ЗАПУСК ТЕСТОВ:" . TestColors::RESET . "\n";
    echo str_repeat("-", 60) . "\n\n";
    
    foreach ($availableTests as $file => $name) {
        try {
            $result = runTest($file, $name);
            $testResults[] = $result;
            $totalTime += $result['time'];
            
            // Компактный вывод результата
            $status = $result['success'] ? '✅' : '❌';
            $color = $result['success'] ? TestColors::GREEN : TestColors::RED;
            echo $color . "$status $name" . TestColors::RESET . " ({$result['time']}с)\n";
            
            // Показываем краткую сводку
            if (!empty($result['summary'])) {
                foreach ($result['summary'] as $summaryLine) {
                    echo "  " . $summaryLine . "\n";
                }
            }
            echo "\n";
            
        } catch (Exception $e) {
            testPrintError("Ошибка при запуске $name: " . $e->getMessage());
            $testResults[] = [
                'name' => $name,
                'success' => false,
                'time' => 0,
                'output' => "Ошибка: " . $e->getMessage(),
                'summary' => []
            ];
        }
    }
    
    // ========================================
    // ИТОГОВЫЙ АНАЛИЗ
    // ========================================
    testPrintCompactHeader("ИТОГОВЫЙ РЕЗУЛЬТАТ", TestColors::MAGENTA);
    
    $successfulTests = 0;
    $totalTests = count($testResults);
    $totalExecutionTime = 0;
    
    foreach ($testResults as $result) {
        $totalExecutionTime += $result['time'];
        if ($result['success']) {
            $successfulTests++;
        }
    }
    
    $successRate = $totalTests > 0 ? round(($successfulTests / $totalTests) * 100, 1) : 0;
    
    // Компактная сводка
    testPrintSummary("Общий результат", $successfulTests, $totalTests, "время: " . round($totalExecutionTime, 1) . "с");
    
    echo "\n";
    
    // ========================================
    // СТАТУС СИСТЕМЫ
    // ========================================
    testPrintCompactHeader("СТАТУС СИСТЕМЫ", TestColors::BLUE);
    
    if ($successRate >= 90) {
        testPrintSuccess("СИСТЕМА ГОТОВА К ПРОДАКШЕНУ!");
    } elseif ($successRate >= 70) {
        testPrintWarning("СИСТЕМА РАБОТАЕТ С ПРЕДУПРЕЖДЕНИЯМИ");
    } elseif ($successRate >= 50) {
        testPrintWarning("СИСТЕМА ТРЕБУЕТ ДОРАБОТКИ");
    } else {
        testPrintError("СИСТЕМА НЕ ГОТОВА К ИСПОЛЬЗОВАНИЮ");
    }
    
    echo "\n";
    
    // ========================================
    // ФИНАЛЬНАЯ ИНФОРМАЦИЯ
    // ========================================
    testPrintCompactHeader("ЗАВЕРШЕНИЕ", TestColors::MAGENTA);
    
    echo "Время завершения: " . date('Y-m-d H:i:s') . "\n";
    echo "Общее время: " . round($totalExecutionTime, 1) . "с\n";
    echo "Логи: logs/*.log\n";
    
    if ($successRate >= 90) {
        echo "\n🎉 СИСТЕМА ГОТОВА К РАБОТЕ! 🎉\n";
    } elseif ($successRate >= 70) {
        echo "\n⚠️  СИСТЕМА ТРЕБУЕТ ВНИМАНИЯ ⚠️\n";
    } else {
        echo "\n❌ СИСТЕМА ТРЕБУЕТ ИСПРАВЛЕНИЙ ❌\n";
    }
    
} catch (Exception $e) {
    testPrintError("КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage());
    echo "Файл: " . $e->getFile() . "\n";
    echo "Строка: " . $e->getLine() . "\n";
    echo "Трассировка:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>