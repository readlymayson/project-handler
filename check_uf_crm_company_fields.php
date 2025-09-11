<?php

/**
 * Скрипт для проверки и создания пользовательских полей UF_CRM в компаниях Битрикс24
 * 
 * Использование:
 * php check_uf_crm_company_fields.php
 * 
 * Или с параметрами:
 * php check_uf_crm_company_fields.php --field=UF_CRM_PROJECT_LINK
 * php check_uf_crm_company_fields.php --all
 * php check_uf_crm_company_fields.php --list
 */

require_once 'config.php';
require_once 'set.php';
require_once 'UF_CRM_FieldChecker.php';
require_once '../require/usualClass.php';
require_once '../logger/class.php';

// Параметры командной строки
$options = getopt('', ['field:', 'all', 'list', 'help']);

// Показываем справку
if (isset($options['help'])) {
    echo "Скрипт проверки и создания пользовательских полей UF_CRM в компаниях Битрикс24\n\n";
    echo "Использование:\n";
    echo "  php check_uf_crm_company_fields.php [опции]\n\n";
    echo "Опции:\n";
    echo "  --field=КОД_ПОЛЯ    Проверить/создать конкретное поле\n";
    echo "  --all              Проверить/создать все необходимые поля\n";
    echo "  --list             Показать список всех полей компаний\n";
    echo "  --help             Показать эту справку\n\n";
    echo "Примеры:\n";
    echo "  php check_uf_crm_company_fields.php --field=UF_CRM_PROJECT_LINK\n";
    echo "  php check_uf_crm_company_fields.php --all\n";
    echo "  php check_uf_crm_company_fields.php --list\n";
    exit(0);
}

try {
    // Инициализация
    $webhook = BITRIX24_WEBHOOK_URL;
    $call = new Usual($webhook);
    $logger = new Logger('uf_crm_company_fields_check.log');
    $fieldChecker = new UF_CRM_FieldChecker($call, $logger);

    echo "=== ПРОВЕРКА ПОЛЕЙ UF_CRM В КОМПАНИЯХ БИТРИКС24 ===\n";
    echo "Время запуска: " . date('Y-m-d H:i:s') . "\n\n";

    // Проверяем конкретное поле
    if (isset($options['field'])) {
        $fieldCode = $options['field'];
        echo "Проверка поля: $fieldCode\n";
        
        if ($fieldChecker->checkFieldExists($fieldCode, 'COMPANY')) {
            $fieldInfo = $fieldChecker->getFieldInfo($fieldCode, 'COMPANY');
            echo "✓ Поле $fieldCode уже существует в компаниях\n";
            echo "Информация о поле:\n";
            echo json_encode($fieldInfo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "✗ Поле $fieldCode не найдено в компаниях\n";
            
            // Проверяем, есть ли поле в конфигурации
            if ($fieldChecker->isFieldConfigured($fieldCode)) {
                echo "Создание поля $fieldCode...\n";
                $result = $fieldChecker->createFieldFromConfig($fieldCode);
                
                if ($result['status'] === 'success') {
                    echo "✓ Поле $fieldCode успешно создано в компаниях (ID: " . $result['field_id'] . ")\n";
                } else {
                    echo "✗ Ошибка при создании поля: " . $result['message'] . "\n";
                }
            } else {
                echo "Поле $fieldCode не найдено в конфигурации. Доступные поля:\n";
                $config = $fieldChecker->getAllFieldsConfig();
                foreach ($config as $code => $fieldConfig) {
                    echo "  - $code: " . $fieldConfig['name'] . "\n";
                }
            }
        }
    }
    // Показываем список всех полей
    elseif (isset($options['list'])) {
        echo "Получение списка всех пользовательских полей компаний...\n";
        $fields = $fieldChecker->getAllCompanyFields();
        
        if (empty($fields)) {
            echo "Пользовательские поля не найдены\n";
        } else {
            echo "Найдено полей: " . count($fields) . "\n\n";
            
            foreach ($fields as $field) {
                echo "Код: " . $field['FIELD_NAME'] . "\n";
                echo "Название: " . $field['LIST_COLUMN_LABEL'] . "\n";
                echo "Тип: " . $field['USER_TYPE_ID'] . "\n";
                echo "Обязательное: " . ($field['MANDATORY'] === 'Y' ? 'Да' : 'Нет') . "\n";
                echo "---\n";
            }
        }
    }
    // Проверяем все необходимые поля
    else {
        echo "Проверка и создание всех необходимых полей UF_CRM в компаниях...\n\n";
        $results = $fieldChecker->checkAndCreateAllFields();
        $fieldChecker->printReport($results);
        
        // Статистика
        $totalFields = count($results);
        $existingFields = count(array_filter($results, fn($r) => $r['status'] === 'exists'));
        $createdFields = count(array_filter($results, fn($r) => $r['status'] === 'success'));
        $errorFields = count(array_filter($results, fn($r) => $r['status'] === 'error'));
        
        echo "\n=== СТАТИСТИКА ===\n";
        echo "Всего полей проверено: $totalFields\n";
        echo "Уже существовало: $existingFields\n";
        echo "Создано новых: $createdFields\n";
        echo "Ошибок: $errorFields\n";
    }

    echo "\n=== ЗАВЕРШЕНО ===\n";
    echo "Время завершения: " . date('Y-m-d H:i:s') . "\n";

} catch (Exception $e) {
    echo "✗ КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage() . "\n";
    echo "Стек вызовов:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
