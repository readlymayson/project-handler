<?php
# -*- coding: utf-8 -*-

/**
 * Тестовый скрипт для проверки работы ExternalDocumentGenerator
 * 
 * Этот скрипт проверяет:
 * - Доступность внешних библиотек
 * - Корректность генерации документов
 * - Работу всех форматов (Excel, PDF, CSV)
 */

require_once 'config.php';
require_once 'ExternalDocumentGenerator.php';

// Мок-классы для тестирования
class MockUsual {
    public function callBitrix24API($method, $params) {
        // Возвращаем тестовые данные
        return [
            'result' => [
                'tasks' => [
                    [
                        'id' => 1,
                        'title' => 'Тестовая задача 1',
                        'createdDate' => '2024-01-15 10:00:00',
                        'CREATED_BY' => 1
                    ],
                    [
                        'id' => 2,
                        'title' => 'Тестовая задача 2',
                        'createdDate' => '2024-01-20 14:30:00',
                        'CREATED_BY' => 2
                    ]
                ]
            ]
        ];
    }
}

class MockLogger {
    public function log($message) {
        if (is_array($message)) {
            echo "LOG: " . json_encode($message, JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "LOG: $message\n";
        }
    }
    
    public function info($message) {
        echo "INFO: $message\n";
    }
}

echo "=== ТЕСТ ВНЕШНЕГО ГЕНЕРАТОРА ДОКУМЕНТОВ ===\n\n";

// Создаем экземпляры
$mockCall = new MockUsual();
$mockLogger = new MockLogger();
$generator = new ExternalDocumentGenerator($mockCall, $mockLogger);

// Проверяем настройки
echo "1. Проверка настроек генератора:\n";
$settings = $generator->checkExternalGeneratorSettings();
foreach ($settings as $key => $value) {
    if (is_bool($value)) {
        $status = $value ? '✅' : '❌';
        echo "   $key: $status " . ($value ? 'Включено' : 'Отключено') . "\n";
    } elseif (is_array($value)) {
        echo "   $key:\n";
        foreach ($value as $subKey => $subValue) {
            if (is_bool($subValue)) {
                $status = $subValue ? '✅' : '❌';
                echo "     $subKey: $status " . ($subValue ? 'Доступно' : 'Недоступно') . "\n";
            } else {
                echo "     $subKey: $subValue\n";
            }
        }
    } else {
        echo "   $key: $value\n";
    }
}

echo "\n2. Проверка готовности системы:\n";
if ($settings['is_ready']) {
    echo "   ✅ Система готова к работе\n";
} else {
    echo "   ❌ Система не готова к работе\n";
    echo "   Проверьте установку библиотек и настройки\n";
    exit(1);
}

// Тестовые данные
echo "\n3. Подготовка тестовых данных:\n";
$testCompany = [
    'ID' => 1,
    'TITLE' => 'Тестовая компания ООО',
    'UF_CRM_CONTRACT_NUMBER' => '№ ТЕСТ-2024',
    'UF_CRM_CONTRACT_DATE' => '01.01.2024',
    'UF_CRM_APPENDIX_NUMBER' => '№ 1',
    'UF_CRM_APPENDIX_DATE' => '01.01.2024'
];

$testProjectTimeData = [
    'total_hours' => 40.5,
    'total_cost' => 40500,
    'roles_time' => [
        'Front-end разработчик #1' => [
            'decimal_hours' => 20.0
        ],
        'Back-end разработчик #1' => [
            'decimal_hours' => 20.5
        ]
    ]
];

$testProjectId = 1;
$testDealId = 12345;

echo "   ✅ Тестовые данные подготовлены\n";

// Тестируем генерацию документов
echo "\n4. Тестирование генерации документов:\n";

try {
    $result = $generator->generateDocumentsForDeal(
        $testDealId,
        $testCompany,
        $testProjectTimeData,
        $testProjectId
    );
    
    echo "   Статус: " . $result['status'] . "\n";
    echo "   Сообщение: " . $result['message'] . "\n";
    
    if (isset($result['documents']) && !empty($result['documents'])) {
        echo "   Сгенерированные документы:\n";
        foreach ($result['documents'] as $type => $document) {
            echo "     $type:\n";
            echo "       Файл: " . $document['filename'] . "\n";
            echo "       Размер: " . number_format($document['size'] / 1024, 2) . " KB\n";
            echo "       URL: " . $document['url'] . "\n";
            
            // Проверяем существование файла
            if (file_exists($document['filepath'])) {
                echo "       ✅ Файл создан успешно\n";
            } else {
                echo "       ❌ Файл не найден\n";
            }
        }
    } else {
        echo "   ❌ Документы не были сгенерированы\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Ошибка при генерации: " . $e->getMessage() . "\n";
}

echo "\n5. Проверка директорий:\n";
$outputDir = EXTERNAL_DOCUMENTS_OUTPUT_DIR;
if (is_dir($outputDir)) {
    echo "   ✅ Директория для документов существует: $outputDir\n";
    if (is_writable($outputDir)) {
        echo "   ✅ Директория доступна для записи\n";
    } else {
        echo "   ❌ Директория недоступна для записи\n";
    }
} else {
    echo "   ❌ Директория для документов не существует: $outputDir\n";
}

$librariesDir = EXTERNAL_LIBRARIES_PATH;
if (is_dir($librariesDir)) {
    echo "   ✅ Директория библиотек существует: $librariesDir\n";
} else {
    echo "   ❌ Директория библиотек не существует: $librariesDir\n";
}

echo "\n=== ТЕСТ ЗАВЕРШЕН ===\n";

// Показываем инструкции
echo "\nИнструкции по использованию:\n";
echo "1. Убедитесь, что все библиотеки установлены (запустите install_external_libraries.sh)\n";
echo "2. Проверьте права доступа к директории generated_documents/\n";
echo "3. Настройте веб-сервер для доступа к документам\n";
echo "4. Интегрируйте ExternalDocumentGenerator в DealCreator\n";
echo "\nДля интеграции замените в DealCreator.php:\n";
echo "  DocumentGenerator -> ExternalDocumentGenerator\n";
echo "  ENABLE_DOCUMENT_GENERATOR -> ENABLE_EXTERNAL_DOCUMENT_GENERATOR\n";
