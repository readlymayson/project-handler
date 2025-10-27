<?php
# -*- coding: utf-8 -*-

require_once 'config.php';
require_once 'set.php';
require_once '../require/usualClass.php';
require_once '../logger/class.php';

/**
 * Тест различных вариантов синтаксиса таблиц в Bitrix24 Document Generator
 */

$logger = new Logger('table_syntax_test.log', __DIR__ . '/logs');
$call = new Usual(BITRIX24_WEBHOOK_URL);

echo "=== Тест различных вариантов синтаксиса таблиц ===\n\n";

try {
    // Тестовые данные
    $testData = [
        'ROLES_DATA' => [
            [
                'NUMBER' => 1,
                'SERVICE_NAME_INVOICE' => 'Front-end разработка',
                'HOURS' => 40,
                'RATE' => 1500,
                'AMOUNT' => 60000
            ],
            [
                'NUMBER' => 2,
                'SERVICE_NAME_INVOICE' => 'Back-end разработка',
                'HOURS' => 30,
                'RATE' => 2000,
                'AMOUNT' => 60000
            ]
        ],
        'TOTAL_COST' => 120000,
        'COMPANY_NAME' => 'ООО Техноресурс'
    ];
    
    echo "Тестовые данные:\n";
    echo json_encode($testData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
    
    // Создаем тестовый шаблон с разными вариантами синтаксиса
    $templateVariants = [
        'variant_1' => [
            'name' => 'Вариант 1: Простая таблица с разделителями',
            'content' => '{TABLE=ROLES_DATA}
{=NUMBER} | {=SERVICE_NAME_INVOICE} | {=HOURS} | час | {=RATE} | {=AMOUNT}
{/TABLE}'
        ],
        'variant_2' => [
            'name' => 'Вариант 2: Таблица с заголовками',
            'content' => '№ | Наименование | Кол-во | Ед. | Цена | Сумма
{TABLE=ROLES_DATA}
{=NUMBER} | {=SERVICE_NAME_INVOICE} | {=HOURS} | час | {=RATE} | {=AMOUNT}
{/TABLE}'
        ],
        'variant_3' => [
            'name' => 'Вариант 3: Таблица с явным указанием массива',
            'content' => '{TABLE=ROLES_DATA}
{=ROLES_DATA.NUMBER} | {=ROLES_DATA.SERVICE_NAME_INVOICE} | {=ROLES_DATA.HOURS} | час | {=ROLES_DATA.RATE} | {=ROLES_DATA.AMOUNT}
{/TABLE}'
        ],
        'variant_4' => [
            'name' => 'Вариант 4: Таблица без разделителей',
            'content' => '{TABLE=ROLES_DATA}
{=NUMBER} {=SERVICE_NAME_INVOICE} {=HOURS} час {=RATE} {=AMOUNT}
{/TABLE}'
        ],
        'variant_5' => [
            'name' => 'Вариант 5: Таблица с HTML-разметкой',
            'content' => '<table>
<tr><th>№</th><th>Наименование</th><th>Кол-во</th><th>Ед.</th><th>Цена</th><th>Сумма</th></tr>
{TABLE=ROLES_DATA}
<tr><td>{=NUMBER}</td><td>{=SERVICE_NAME_INVOICE}</td><td>{=HOURS}</td><td>час</td><td>{=RATE}</td><td>{=AMOUNT}</td></tr>
{/TABLE}
</table>'
        ],
        'variant_6' => [
            'name' => 'Вариант 6: Таблица с форматированием',
            'content' => '| № | Наименование | Кол-во | Ед. | Цена | Сумма |
|----|-------------|--------|-----|------|-------|
{TABLE=ROLES_DATA}
| {=NUMBER} | {=SERVICE_NAME_INVOICE} | {=HOURS} | час | {=RATE} | {=AMOUNT} |
{/TABLE}'
        ],
        'variant_7' => [
            'name' => 'Вариант 7: Простой список',
            'content' => '{TABLE=ROLES_DATA}
{=NUMBER}. {=SERVICE_NAME_INVOICE} - {=HOURS} часов по {=RATE} руб. = {=AMOUNT} руб.
{/TABLE}'
        ],
        'variant_8' => [
            'name' => 'Вариант 8: Таблица с дополнительными полями',
            'content' => '{TABLE=ROLES_DATA}
Строка {=NUMBER}: {=SERVICE_NAME_INVOICE}
Количество: {=HOURS} часов
Цена за час: {=RATE} рублей
Итого: {=AMOUNT} рублей
---
{/TABLE}'
        ]
    ];
    
    // Тестируем каждый вариант
    foreach ($templateVariants as $variantKey => $variant) {
        echo "Тестируем: {$variant['name']}\n";
        echo "Содержимое шаблона:\n";
        echo $variant['content'] . "\n";
        echo "---\n";
        
        // Создаем временный шаблон для тестирования
        $templateData = [
            'NAME' => "Тест таблицы - {$variant['name']}",
            'ENTITY_TYPE_ID' => 2, // Deal
            'TEMPLATE' => $variant['content'],
            'ACTIVE' => 'Y'
        ];
        
        $logger->log([
            'test_variant' => $variantKey,
            'variant_name' => $variant['name'],
            'template_content' => $variant['content']
        ]);
        
        // Пробуем создать шаблон (если API поддерживает)
        try {
            apiDelay();
            $createResult = $call->callBitrix24API('crm.documentgenerator.template.add', $templateData);
            
            if (isset($createResult['result']['template']['id'])) {
                $templateId = $createResult['result']['template']['id'];
                echo "Шаблон создан с ID: $templateId\n";
                
                // Тестируем генерацию документа
                $params = [
                    'templateId' => $templateId,
                    'entityTypeId' => 2,
                    'entityId' => 80,
                    'values' => $testData
                ];
                
                apiDelay();
                $docResult = $call->callBitrix24API('crm.documentgenerator.document.add', $params);
                
                if (isset($docResult['result']['document']['id'])) {
                    $documentId = $docResult['result']['document']['id'];
                    echo "Документ создан с ID: $documentId\n";
                    
                    $logger->log([
                        'success' => "Вариант $variantKey успешен",
                        'template_id' => $templateId,
                        'document_id' => $documentId
                    ]);
                } else {
                    echo "Ошибка создания документа: " . json_encode($docResult) . "\n";
                }
                
                // Удаляем тестовый шаблон
                apiDelay();
                $deleteResult = $call->callBitrix24API('crm.documentgenerator.template.delete', ['id' => $templateId]);
                
            } else {
                echo "Не удалось создать шаблон: " . json_encode($createResult) . "\n";
            }
            
        } catch (Exception $e) {
            echo "Ошибка: " . $e->getMessage() . "\n";
        }
        
        echo "\n" . str_repeat("=", 50) . "\n\n";
    }
    
    echo "=== Тест завершен ===\n";
    
} catch (Exception $e) {
    echo "Общая ошибка: " . $e->getMessage() . "\n";
    $logger->log([
        'error' => 'Общая ошибка теста',
        'message' => $e->getMessage()
    ]);
}
