<?php
/**
 * Тест настроек генератора документов
 * Проверяет конфигурацию и готовность системы генерации документов
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'logs/error.log');
error_reporting(E_ALL);

// Подключаем общие функции тестирования
require_once 'test_helpers.php';

try {
    testPrintHeader("ТЕСТ НАСТРОЕК ГЕНЕРАТОРА ДОКУМЕНТОВ", TestColors::MAGENTA);
    echo "Время запуска: " . date('Y-m-d H:i:s') . "\n\n";

    // Подключение классов
    require_once 'set.php';
    require_once '../require/usualClass.php';
    require_once 'class.php';
    require_once 'config.php';
    require_once 'DocumentGenerator.php';
    require_once '../logger/class.php';
    
    $call = new Usual(BITRIX24_WEBHOOK_URL);
    $logger = new Logger('document_generator_test.log', __DIR__ . '/logs');
    $documentGenerator = new DocumentGenerator($call, $logger);

    // ========================================
    // ПРОВЕРКА КОНФИГУРАЦИИ
    // ========================================
    testPrintCompactHeader("1. КОНФИГУРАЦИЯ", TestColors::BLUE);
    
    $configTests = [
        'ENABLE_DOCUMENT_GENERATOR' => ENABLE_DOCUMENT_GENERATOR,
        'AUTO_GENERATE_DOCUMENTS' => AUTO_GENERATE_DOCUMENTS,
        'GENERATE_ON_DEAL_CREATION' => GENERATE_ON_DEAL_CREATION,
        'GENERATE_ON_DEAL_UPDATE' => GENERATE_ON_DEAL_UPDATE
    ];
    
    $configOk = 0;
    foreach ($configTests as $setting => $value) {
        $isValid = is_bool($value);
        testPrintTest("$setting", $isValid, "Значение: " . ($value ? 'true' : 'false'));
        if ($isValid) $configOk++;
    }
    
    testPrintSummary("Конфигурация", $configOk, count($configTests));
    
    echo "\n";
    
    // ========================================
    // ПРОВЕРКА ШАБЛОНОВ
    // ========================================
    testPrintCompactHeader("2. ШАБЛОНЫ ДОКУМЕНТОВ", TestColors::BLUE);
    
    $templateTests = [
        'REPORT_TEMPLATE_ID' => REPORT_TEMPLATE_ID,
        'INVOICE_TEMPLATE_ID' => INVOICE_TEMPLATE_ID,
        'ACT_TEMPLATE_ID' => ACT_TEMPLATE_ID
    ];
    
    $templateOk = 0;
    foreach ($templateTests as $template => $id) {
        $isConfigured = $id > 0;
        $description = str_replace('_TEMPLATE_ID', '', $template);
        testPrintTest("Шаблон $description", $isConfigured, "ID: $id");
        if ($isConfigured) $templateOk++;
    }
    
    testPrintSummary("Шаблоны", $templateOk, count($templateTests));
    
    echo "\n";
    
    // ========================================
    // ПРОВЕРКА НАСТРОЕК ПРОИЗВОДИТЕЛЬНОСТИ
    // ========================================
    testPrintCompactHeader("3. НАСТРОЙКИ ПРОИЗВОДИТЕЛЬНОСТИ", TestColors::BLUE);
    
    $performanceTests = [
        'DOCUMENT_GENERATION_TIMEOUT' => DOCUMENT_GENERATION_TIMEOUT,
        'DOCUMENT_RETRY_ATTEMPTS' => DOCUMENT_RETRY_ATTEMPTS,
        'DOCUMENT_RETRY_DELAY' => DOCUMENT_RETRY_DELAY
    ];
    
    $perfOk = 0;
    foreach ($performanceTests as $setting => $value) {
        $isValid = is_numeric($value) && $value > 0;
        testPrintTest("$setting", $isValid, "Значение: $value");
        if ($isValid) $perfOk++;
    }
    
    testPrintSummary("Производительность", $perfOk, count($performanceTests));
    
    echo "\n";
    
    // ========================================
    // ПРОВЕРКА ЧЕРЕЗ КЛАСС
    // ========================================
    testPrintCompactHeader("4. ПРОВЕРКА ЧЕРЕЗ КЛАСС", TestColors::BLUE);
    
    try {
        $settings = $documentGenerator->checkDocumentGeneratorSettings();
        
        $classTests = [
            'Генератор включен' => $settings['enabled'],
            'Автогенерация' => $settings['auto_generate'],
            'Генерация при создании' => $settings['generate_on_creation'],
            'Генерация при обновлении' => $settings['generate_on_update']
        ];
        
        $classOk = 0;
        foreach ($classTests as $test => $status) {
            testPrintTest($test, $status);
            if ($status) $classOk++;
        }
        
        testPrintInfo("Настроено шаблонов: {$settings['configured_templates']}/{$settings['total_templates']}");
        testPrintInfo("Готовность генератора: " . ($settings['is_ready'] ? 'Да' : 'Нет'));
        
        testPrintSummary("Проверка через класс", $classOk, count($classTests));
        
    } catch (Exception $e) {
        testPrintError("Ошибка проверки через класс: " . $e->getMessage());
        $classOk = 0;
    }
    
    echo "\n";
    
    // ========================================
    // РЕКОМЕНДАЦИИ
    // ========================================
    testPrintCompactHeader("5. РЕКОМЕНДАЦИИ", TestColors::BLUE);
    
    if (!ENABLE_DOCUMENT_GENERATOR) {
        testPrintWarning("Генератор документов отключен");
        echo "• Для включения установите ENABLE_DOCUMENT_GENERATOR = true в config.php\n";
    }
    
    if (ENABLE_DOCUMENT_GENERATOR && $templateOk == 0) {
        testPrintWarning("Генератор включен, но шаблоны не настроены");
        echo "• Создайте шаблоны документов в Bitrix24 (CRM -> Настройки -> Шаблоны документов)\n";
        echo "• Укажите ID шаблонов в config.php\n";
    }
    
    if (ENABLE_DOCUMENT_GENERATOR && $templateOk > 0) {
        testPrintSuccess("Генератор документов готов к работе!");
        echo "• Настроено шаблонов: $templateOk/3\n";
        echo "• Генерация документов будет работать при создании сделок\n";
    }
    
    echo "\n";
    
    // ========================================
    // ИТОГОВЫЙ РЕЗУЛЬТАТ
    // ========================================
    testPrintCompactHeader("ИТОГОВЫЙ РЕЗУЛЬТАТ", TestColors::MAGENTA);
    
    $totalTests = $configOk + $templateOk + $perfOk + $classOk;
    $maxTests = count($configTests) + count($templateTests) + count($performanceTests) + count($classTests);
    
    testPrintSummary("Общий результат", $totalTests, $maxTests);
    
    if ($totalTests >= $maxTests * 0.8) {
        testPrintSuccess("ГЕНЕРАТОР ДОКУМЕНТОВ ГОТОВ К РАБОТЕ!");
    } elseif ($totalTests >= $maxTests * 0.6) {
        testPrintWarning("ГЕНЕРАТОР ДОКУМЕНТОВ ТРЕБУЕТ НАСТРОЙКИ");
    } else {
        testPrintError("ГЕНЕРАТОР ДОКУМЕНТОВ НЕ НАСТРОЕН");
    }
    
    echo "\nВремя завершения: " . date('Y-m-d H:i:s') . "\n";
    echo "Лог: logs/document_generator_test.log\n";
    
} catch (Exception $e) {
    testPrintError("КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage());
    echo "Файл: " . $e->getFile() . "\n";
    echo "Строка: " . $e->getLine() . "\n";
    echo "Трассировка:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>
