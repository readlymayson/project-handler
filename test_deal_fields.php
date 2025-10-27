<?php
# -*- coding: utf-8 -*-

require_once 'config.php';
require_once 'set.php';
require_once '../require/usualClass.php';
require_once '../logger/class.php';

/**
 * Тест с данными из стандартных полей сделки Bitrix24
 */

$logger = new Logger('deal_fields_test.log', __DIR__ . '/logs');
$call = new Usual(BITRIX24_WEBHOOK_URL);

echo "=== Тест с данными из полей сделки ===\n\n";

try {
    // Получаем данные сделки 80
    echo "1. Получаем данные сделки 80...\n";
    apiDelay();
    $dealResult = $call->callBitrix24API('crm.deal.get', ['id' => 80]);
    
    if (isset($dealResult['error'])) {
        echo "Ошибка получения сделки: " . $dealResult['error_description'] . "\n";
        exit(1);
    }
    
    $deal = $dealResult['result'];
    echo "Сделка найдена: {$deal['TITLE']}\n";
    echo "Все поля сделки:\n";
    foreach ($deal as $key => $value) {
        if (is_string($value) && !empty($value)) {
            echo "- $key: $value\n";
        }
    }
    
    echo "\n2. Тестируем генерацию документов с данными сделки...\n";
    
    // Тестируем разные варианты передачи данных
    $testVariants = [
        'variant_1' => [
            'name' => 'Только данные сделки (без дополнительных values)',
            'data' => null
        ],
        'variant_2' => [
            'name' => 'Данные сделки + стандартные плейсхолдеры',
            'data' => [
                'ProductsProductIndex' => 1,
                'ProductsProductName' => 'Тестовая услуга из сделки',
                'ProductsProductQuantity' => 10,
                'ProductsProductMeasureName' => 'час',
                'ProductsProductPriceRaw' => 1000,
                'ProductsProductPriceRawSum' => 10000
            ]
        ],
        'variant_3' => [
            'name' => 'Данные сделки + поля с индексами',
            'data' => [
                'ProductsProduct0Index' => 1,
                'ProductsProduct0Name' => 'Тестовая услуга из сделки',
                'ProductsProduct0Quantity' => 10,
                'ProductsProduct0MeasureName' => 'час',
                'ProductsProduct0PriceRaw' => 1000,
                'ProductsProduct0PriceRawSum' => 10000
            ]
        ]
    ];
    
    $templates = [40, 42];
    
    foreach ($testVariants as $variantKey => $variant) {
        echo "\n--- Тестируем: {$variant['name']} ---\n";
        
        foreach ($templates as $templateId) {
            echo "Шаблон $templateId: ";
            
            $params = [
                'templateId' => $templateId,
                'entityTypeId' => 2,
                'entityId' => 80
            ];
            
            // Добавляем дополнительные данные только если они есть
            if ($variant['data'] !== null) {
                $params['values'] = $variant['data'];
            }
            
            apiDelay();
            $result = $call->callBitrix24API('crm.documentgenerator.document.add', $params);
            
            if (isset($result['error'])) {
                echo "Ошибка: " . $result['error_description'] . "\n";
            } else {
                $documentId = $result['result']['document']['id'] ?? 'Неизвестно';
                echo "Успешно! Документ ID: $documentId\n";
                
                $logger->log([
                    'success' => "Вариант $variantKey успешен с шаблоном $templateId",
                    'document_id' => $documentId,
                    'test_data' => $variant['data']
                ]);
            }
        }
    }
    
    echo "\n3. Проверяем, есть ли в сделке товары...\n";
    
    // Проверяем, есть ли в сделке товары
    apiDelay();
    $productsResult = $call->callBitrix24API('crm.deal.productrows.get', ['id' => 80]);
    
    if (isset($productsResult['result'])) {
        echo "Товары в сделке:\n";
        foreach ($productsResult['result'] as $product) {
            echo "- " . ($product['PRODUCT_NAME'] ?? 'Без названия') . 
                 " (ID: " . ($product['PRODUCT_ID'] ?? 'Нет') . 
                 ", Цена: " . ($product['PRICE'] ?? 'Нет') . 
                 ", Количество: " . ($product['QUANTITY'] ?? 'Нет') . ")\n";
        }
    } else {
        echo "Товары в сделке не найдены или ошибка: " . json_encode($productsResult) . "\n";
    }
    
    echo "\n=== Тест завершен ===\n";
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    $logger->log([
        'error' => 'Общая ошибка теста',
        'message' => $e->getMessage()
    ]);
}
