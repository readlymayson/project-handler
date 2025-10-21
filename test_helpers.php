<?php
/**
 * Общие функции для тестирования
 * Предотвращает конфликты имен функций между тестовыми файлами
 */

// Цвета для вывода
class TestColors {
    const RED = "\033[31m";
    const GREEN = "\033[32m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const MAGENTA = "\033[35m";
    const CYAN = "\033[36m";
    const WHITE = "\033[37m";
    const RESET = "\033[0m";
    const BOLD = "\033[1m";
}

function testPrintHeader($title, $color = TestColors::CYAN) {
    echo "\n" . $color . TestColors::BOLD . str_repeat("=", 60) . "\n";
    echo $title . "\n";
    echo str_repeat("=", 60) . TestColors::RESET . "\n\n";
}

function testPrintTest($name, $status, $details = '') {
    $icon = $status ? '✅' : '❌';
    $color = $status ? TestColors::GREEN : TestColors::RED;
    echo $color . "$icon $name" . TestColors::RESET;
    if ($details) {
        echo " - $details";
    }
    echo "\n";
}

function testPrintSummary($title, $passed, $total, $details = '') {
    $percentage = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
    $status = $percentage >= 80 ? '✅' : ($percentage >= 60 ? '⚠️' : '❌');
    $color = $percentage >= 80 ? TestColors::GREEN : ($percentage >= 60 ? TestColors::YELLOW : TestColors::RED);
    
    echo $color . "$status $title: $passed/$total ($percentage%)" . TestColors::RESET;
    if ($details) {
        echo " - $details";
    }
    echo "\n";
}

function testPrintCompactHeader($title, $color = TestColors::CYAN) {
    echo "\n" . $color . "▶ $title" . TestColors::RESET . "\n";
    echo str_repeat("-", 50) . "\n";
}

function testPrintInfo($message, $color = TestColors::YELLOW) {
    echo $color . "ℹ️  $message" . TestColors::RESET . "\n";
}

function testPrintWarning($message) {
    echo TestColors::YELLOW . "⚠️  $message" . TestColors::RESET . "\n";
}

function testPrintError($message) {
    echo TestColors::RED . "❌ $message" . TestColors::RESET . "\n";
}

function testPrintSuccess($message) {
    echo TestColors::GREEN . "✅ $message" . TestColors::RESET . "\n";
}

function testPrintScenario($name, $status, $details = '') {
    $icon = $status ? '✅' : '❌';
    $color = $status ? TestColors::GREEN : TestColors::RED;
    echo $color . "$icon $name" . TestColors::RESET;
    if ($details) {
        echo " - $details";
    }
    echo "\n";
}

function testRunTest($testFile, $testName) {
    testPrintInfo("Запуск $testName...");
    
    $startTime = microtime(true);
    $output = [];
    $returnCode = 0;
    
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
    
    return [
        'name' => $testName,
        'success' => $success,
        'time' => $executionTime,
        'output' => $output
    ];
}
?>
