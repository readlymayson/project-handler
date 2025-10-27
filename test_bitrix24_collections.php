<?php
# -*- coding: utf-8 -*-

require_once 'config.php';
require_once 'set.php';
require_once '../require/usualClass.php';
require_once '../logger/class.php';
require_once 'DocumentGenerator.php';

/**
 * Тест нового формата данных для Bitrix24 коллекций
 */

$logger = new Logger('bitrix24_collections_test.log', __DIR__ . '/logs');
$call = new Usual(BITRIX24_WEBHOOK_URL);

echo "=== Тест формата Bitrix24 коллекций ===\n\n";

try {
    // Тестовые данные в формате Bitrix24 коллекций
    $testData = [
        // Данные продуктов в формате Bitrix24
        'ProductsProduct0Index' => 1,
        'ProductsProduct0Name' => 'Front-end разработка',
        'ProductsProduct0Quantity' => 40,
        'ProductsProduct0MeasureName' => 'час',
        'ProductsProduct0PriceRaw' => 1500,
        'ProductsProduct0PriceRawSum' => 60000,
        
        'ProductsProduct1Index' => 2,
        'ProductsProduct1Name' => 'Back-end разработка',
        'ProductsProduct1Quantity' => 30,
        'ProductsProduct1MeasureName' => 'час',
        'ProductsProduct1PriceRaw' => 2000,
        'ProductsProduct1PriceRawSum' => 60000,
        
        // Общие данные
        'TOTAL_COST' => 120000,
        'COMPANY_NAME' => 'ООО Техноресурс'
    ];
    
    echo "Тестовые данные в формате Bitrix24 коллекций:\n";
    echo json_encode($testData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
    
    // Тестируем с существующими шаблонами
    $templates = [
        40 => 'Счет/Платежное поручение',
        42 => 'Акт'
    ];
    
    foreach ($templates as $templateId => $templateName) {
        echo "Тестируем шаблон: $templateName (ID: $templateId)\n";
        
        $params = [
            'templateId' => $templateId,
            'entityTypeId' => 2, // Deal
            'entityId' => 80,
            'values' => $testData
        ];
        
        $logger->log([
            'test' => "Тест Bitrix24 коллекций",
            'template_id' => $templateId,
            'template_name' => $templateName,
            'test_data' => $testData
        ]);
        
        apiDelay();
        $result = $call->callBitrix24API('crm.documentgenerator.document.add', $params);
        
        if (isset($result['error'])) {
            echo "Ошибка: " . $result['error_description'] . "\n";
            $logger->log([
                'error' => "Ошибка шаблона $templateId",
                'error_message' => $result['error_description']
            ]);
        } else {
            $documentId = $result['result']['document']['id'] ?? 'Неизвестно';
            echo "Успешно! Документ ID: $documentId\n";
            
            $logger->log([
                'success' => "Шаблон $templateId успешен с Bitrix24 коллекциями",
                'document_id' => $documentId
            ]);
        }
        
        echo "\n";
    }
    
    echo "=== Тест завершен ===\n";
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    $logger->log([
        'error' => 'Общая ошибка теста',
        'message' => $e->getMessage()
    ]);
}
