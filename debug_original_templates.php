<?php
# -*- coding: utf-8 -*-

require_once 'config.php';
require_once 'set.php';
require_once '../require/usualClass.php';
require_once '../logger/class.php';

/**
 * Тест для выяснения, какие плейсхолдеры ожидают оригинальные шаблоны Bitrix24
 */

$logger = new Logger('template_debug.log', __DIR__ . '/logs');
$call = new Usual(BITRIX24_WEBHOOK_URL);

echo "=== Отладка оригинальных шаблонов Bitrix24 ===\n\n";

try {
    // Получаем информацию о шаблонах
    echo "1. Получаем информацию о шаблонах...\n";
    
    $templates = [40, 42];
    
    foreach ($templates as $templateId) {
        echo "\n--- Шаблон ID: $templateId ---\n";
        
        apiDelay();
        $result = $call->callBitrix24API('crm.documentgenerator.template.get', ['id' => $templateId]);
        
        if (isset($result['result'])) {
            $template = $result['result'];
            echo "Название: " . ($template['NAME'] ?? 'Не указано') . "\n";
            echo "Активен: " . ($template['ACTIVE'] ?? 'Не указано') . "\n";
            echo "Тип сущности: " . ($template['ENTITY_TYPE_ID'] ?? 'Не указано') . "\n";
            
            // Пытаемся получить содержимое шаблона
            if (isset($template['TEMPLATE'])) {
                echo "Содержимое шаблона:\n";
                echo $template['TEMPLATE'] . "\n";
            }
            
            // Получаем поля шаблона
            if (isset($template['FIELDS'])) {
                echo "Поля шаблона:\n";
                foreach ($template['FIELDS'] as $field) {
                    echo "- " . ($field['CODE'] ?? 'Без кода') . ": " . ($field['TITLE'] ?? 'Без названия') . "\n";
                }
            }
            
        } else {
            echo "Ошибка получения шаблона: " . json_encode($result) . "\n";
        }
    }
    
    echo "\n2. Тестируем различные варианты данных...\n";
    
    // Тестируем разные варианты данных
    $testVariants = [
        'variant_1' => [
            'name' => 'Стандартные плейсхолдеры Bitrix24',
            'data' => [
                'ProductsProductIndex' => 1,
                'ProductsProductName' => 'Тестовая услуга',
                'ProductsProductQuantity' => 10,
                'ProductsProductMeasureName' => 'час',
                'ProductsProductPriceRaw' => 1000,
                'ProductsProductPriceRawSum' => 10000
            ]
        ],
        'variant_2' => [
            'name' => 'Плейсхолдеры с индексами',
            'data' => [
                'ProductsProduct0Index' => 1,
                'ProductsProduct0Name' => 'Тестовая услуга',
                'ProductsProduct0Quantity' => 10,
                'ProductsProduct0MeasureName' => 'час',
                'ProductsProduct0PriceRaw' => 1000,
                'ProductsProduct0PriceRawSum' => 10000
            ]
        ],
        'variant_3' => [
            'name' => 'Плейсхолдеры с префиксом Collection',
            'data' => [
                'CollectionProductIndex' => 1,
                'CollectionProductName' => 'Тестовая услуга',
                'CollectionProductQuantity' => 10,
                'CollectionProductMeasureName' => 'час',
                'CollectionProductPriceRaw' => 1000,
                'CollectionProductPriceRawSum' => 10000
            ]
        ],
        'variant_4' => [
            'name' => 'Плейсхолдеры с префиксом Items',
            'data' => [
                'ItemsItemIndex' => 1,
                'ItemsItemName' => 'Тестовая услуга',
                'ItemsItemQuantity' => 10,
                'ItemsItemMeasureName' => 'час',
                'ItemsItemPriceRaw' => 1000,
                'ItemsItemPriceRawSum' => 10000
            ]
        ]
    ];
    
    foreach ($testVariants as $variantKey => $variant) {
        echo "\n--- Тестируем: {$variant['name']} ---\n";
        
        foreach ($templates as $templateId) {
            echo "Шаблон $templateId: ";
            
            $params = [
                'templateId' => $templateId,
                'entityTypeId' => 2,
                'entityId' => 80,
                'values' => $variant['data']
            ];
            
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
    
    echo "\n=== Тест завершен ===\n";
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    $logger->log([
        'error' => 'Общая ошибка теста',
        'message' => $e->getMessage()
    ]);
}
