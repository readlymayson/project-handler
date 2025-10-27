<?php
# -*- coding: utf-8 -*-

require_once 'config.php';
require_once 'set.php';
require_once '../require/usualClass.php';
require_once '../logger/class.php';

/**
 * Тест различных форматов данных для существующих шаблонов
 */

$logger = new Logger('data_format_test.log', __DIR__ . '/logs');
$call = new Usual(BITRIX24_WEBHOOK_URL);

echo "=== Тест различных форматов данных ===\n\n";

try {
    // Тестируем разные форматы данных
    $dataFormats = [
        'format_1' => [
            'name' => 'Стандартный формат',
            'data' => [
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
            ]
        ],
        'format_2' => [
            'name' => 'Формат с дополнительными полями',
            'data' => [
                'ROLES_DATA' => [
                    [
                        'NUMBER' => 1,
                        'ROLE' => 'front-end разработчик',
                        'SERVICE_NAME_INVOICE' => 'Оплата услуг front-end разработчика в сентябре 2025 года',
                        'SERVICE_NAME_ACT' => 'Услуги front-end разработчика в сентябре 2025 года',
                        'HOURS' => 40,
                        'RATE' => 1500,
                        'AMOUNT' => 60000
                    ],
                    [
                        'NUMBER' => 2,
                        'ROLE' => 'back-end разработчик',
                        'SERVICE_NAME_INVOICE' => 'Оплата услуг back-end разработчика в сентябре 2025 года',
                        'SERVICE_NAME_ACT' => 'Услуги back-end разработчика в сентябре 2025 года',
                        'HOURS' => 30,
                        'RATE' => 2000,
                        'AMOUNT' => 60000
                    ]
                ],
                'TOTAL_COST' => 120000,
                'COMPANY_NAME' => 'ООО Техноресурс',
                'MONTH_NAME' => 'сентябре',
                'YEAR' => '2025'
            ]
        ],
        'format_3' => [
            'name' => 'Формат с числовыми индексами',
            'data' => [
                'ROLES_DATA' => [
                    '0' => [
                        'NUMBER' => 1,
                        'SERVICE_NAME_INVOICE' => 'Front-end разработка',
                        'HOURS' => 40,
                        'RATE' => 1500,
                        'AMOUNT' => 60000
                    ],
                    '1' => [
                        'NUMBER' => 2,
                        'SERVICE_NAME_INVOICE' => 'Back-end разработка',
                        'HOURS' => 30,
                        'RATE' => 2000,
                        'AMOUNT' => 60000
                    ]
                ],
                'TOTAL_COST' => 120000,
                'COMPANY_NAME' => 'ООО Техноресурс'
            ]
        ],
        'format_4' => [
            'name' => 'Формат с объектами вместо массивов',
            'data' => [
                'ROLES_DATA' => (object)[
                    '0' => (object)[
                        'NUMBER' => 1,
                        'SERVICE_NAME_INVOICE' => 'Front-end разработка',
                        'HOURS' => 40,
                        'RATE' => 1500,
                        'AMOUNT' => 60000
                    ],
                    '1' => (object)[
                        'NUMBER' => 2,
                        'SERVICE_NAME_INVOICE' => 'Back-end разработка',
                        'HOURS' => 30,
                        'RATE' => 2000,
                        'AMOUNT' => 60000
                    ]
                ],
                'TOTAL_COST' => 120000,
                'COMPANY_NAME' => 'ООО Техноресурс'
            ]
        ],
        'format_5' => [
            'name' => 'Формат с плоской структурой',
            'data' => [
                'ROLE_1_NUMBER' => 1,
                'ROLE_1_SERVICE_NAME_INVOICE' => 'Front-end разработка',
                'ROLE_1_HOURS' => 40,
                'ROLE_1_RATE' => 1500,
                'ROLE_1_AMOUNT' => 60000,
                'ROLE_2_NUMBER' => 2,
                'ROLE_2_SERVICE_NAME_INVOICE' => 'Back-end разработка',
                'ROLE_2_HOURS' => 30,
                'ROLE_2_RATE' => 2000,
                'ROLE_2_AMOUNT' => 60000,
                'TOTAL_COST' => 120000,
                'COMPANY_NAME' => 'ООО Техноресурс'
            ]
        ]
    ];
    
    // Тестируем каждый формат с существующими шаблонами
    $templates = [
        40 => 'Счет/Платежное поручение',
        42 => 'Акт'
    ];
    
    foreach ($dataFormats as $formatKey => $format) {
        echo "Тестируем: {$format['name']}\n";
        echo "Структура данных:\n";
        echo json_encode($format['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        echo "---\n";
        
        foreach ($templates as $templateId => $templateName) {
            echo "Тестируем шаблон: $templateName (ID: $templateId)\n";
            
            $params = [
                'templateId' => $templateId,
                'entityTypeId' => 2, // Deal
                'entityId' => 80,
                'values' => $format['data']
            ];
            
            $logger->log([
                'test_format' => $formatKey,
                'format_name' => $format['name'],
                'template_id' => $templateId,
                'template_name' => $templateName,
                'test_data' => $format['data']
            ]);
            
            apiDelay();
            $result = $call->callBitrix24API('crm.documentgenerator.document.add', $params);
            
            if (isset($result['error'])) {
                echo "Ошибка: " . $result['error_description'] . "\n";
                $logger->log([
                    'error' => "Ошибка шаблона $templateId с форматом $formatKey",
                    'error_message' => $result['error_description']
                ]);
            } else {
                $documentId = $result['result']['document']['id'] ?? 'Неизвестно';
                echo "Успешно! Документ ID: $documentId\n";
                
                $logger->log([
                    'success' => "Формат $formatKey успешен с шаблоном $templateId",
                    'document_id' => $documentId
                ]);
            }
            
            echo "\n";
        }
        
        echo str_repeat("=", 50) . "\n\n";
    }
    
    echo "=== Тест завершен ===\n";
    
} catch (Exception $e) {
    echo "Общая ошибка: " . $e->getMessage() . "\n";
    $logger->log([
        'error' => 'Общая ошибка теста',
        'message' => $e->getMessage()
    ]);
}
