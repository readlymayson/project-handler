<?php
# -*- coding: utf-8 -*-

require_once 'config.php';
require_once 'set.php';
require_once '../require/usualClass.php';
require_once '../logger/class.php';

/**
 * Тест простого шаблона для проверки синтаксиса Bitrix24
 */

$logger = new Logger('simple_template_test.log', __DIR__ . '/logs');
$call = new Usual(BITRIX24_WEBHOOK_URL);

echo "=== Тест простого шаблона ===\n\n";

try {
    // Создаем простые тестовые данные
    $testData = [
        'ROLES_DATA' => [
            [
                'NUMBER' => 1,
                'SERVICE_NAME_INVOICE' => 'Тестовая услуга',
                'HOURS' => 10,
                'RATE' => 1000,
                'AMOUNT' => 10000
            ],
            [
                'NUMBER' => 2,
                'SERVICE_NAME_INVOICE' => 'Еще одна услуга',
                'HOURS' => 5,
                'RATE' => 2000,
                'AMOUNT' => 10000
            ]
        ],
        'TOTAL_COST' => 20000,
        'COMPANY_NAME' => 'Тестовая компания'
    ];
    
    echo "Тестовые данные:\n";
    echo json_encode($testData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
    
    // Пробуем разные варианты синтаксиса
    $templates = [
        40 => 'Основной шаблон (ID 40)',
        42 => 'Акт шаблон (ID 42)'
    ];
    
    foreach ($templates as $templateId => $description) {
        echo "Тестируем $description...\n";
        
        $params = [
            'templateId' => $templateId,
            'entityTypeId' => 2, // Deal
            'entityId' => 80,
            'values' => $testData
        ];
        
        $logger->log([
            'test' => "Тест шаблона $templateId",
            'template_id' => $templateId,
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
                'success' => "Шаблон $templateId работает",
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
