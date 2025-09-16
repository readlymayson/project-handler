<?php

/**
 * Веб-интерфейс для проверки и создания пользовательских полей UF_CRM в компаниях Битрикс24
 * 
 * Использование через браузер:
 * http://your-domain.com/web_check_fields.php?action=all
 * http://your-domain.com/web_check_fields.php?action=field&field=UF_CRM_PROJECT_LINK
 * http://your-domain.com/web_check_fields.php?action=list
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'logs/error.log');
error_reporting(E_ALL);

require_once 'config.php';
require_once 'set.php';
require_once 'UF_CRM_FieldChecker.php';
require_once '../require/usualClass.php';
require_once '../logger/class.php';

// Устанавливаем кодировку
header('Content-Type: text/html; charset=utf-8');

// Получаем параметры из URL
$action = $_GET['action'] ?? 'help';
$field = $_GET['field'] ?? '';

// Функция для вывода HTML
function outputHtml($title, $content) {
    echo "<!DOCTYPE html>
<html lang='ru'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>$title</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .info { color: #17a2b8; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; border-left: 4px solid #007cba; overflow-x: auto; }
        .field-info { background: #e9ecef; padding: 10px; margin: 10px 0; border-radius: 4px; }
        .stats { display: flex; gap: 20px; margin: 20px 0; }
        .stat-item { background: #f8f9fa; padding: 15px; border-radius: 4px; text-align: center; min-width: 120px; }
        .stat-number { font-size: 24px; font-weight: bold; color: #007cba; }
        .stat-label { color: #666; font-size: 14px; }
        .nav { margin: 20px 0; }
        .nav a { display: inline-block; margin: 5px 10px 5px 0; padding: 8px 16px; background: #007cba; color: white; text-decoration: none; border-radius: 4px; }
        .nav a:hover { background: #0056b3; }
        .help-section { background: #f8f9fa; padding: 20px; border-radius: 4px; margin: 20px 0; }
        .help-section h3 { margin-top: 0; color: #333; }
        .help-section code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Управление полями UF_CRM в компаниях Битрикс24</h1>
        $content
    </div>
</body>
</html>";
}

// Функция для форматирования времени
function formatTime($timestamp) {
    return date('d.m.Y H:i:s', $timestamp);
}

// Функция для вывода результатов
function formatResults($results) {
    $html = '<h2>📊 Результаты проверки полей</h2>';
    
    foreach ($results as $fieldCode => $result) {
        $statusClass = match($result['status']) {
            'success' => 'success',
            'exists' => 'info',
            'error' => 'error',
            default => 'warning'
        };
        
        $statusIcon = match($result['status']) {
            'success' => '✅',
            'exists' => 'ℹ️',
            'error' => '❌',
            default => '⚠️'
        };
        
        $html .= "<div class='field-info'>";
        $html .= "<h3>$statusIcon Поле: <code>$fieldCode</code></h3>";
        $html .= "<p><strong>Статус:</strong> <span class='$statusClass'>{$result['status']}</span></p>";
        $html .= "<p><strong>Сообщение:</strong> {$result['message']}</p>";
        
        if (isset($result['field_id'])) {
            $html .= "<p><strong>ID поля:</strong> {$result['field_id']}</p>";
        }
        
        if (isset($result['field_info'])) {
            $html .= "<details><summary>Информация о поле</summary>";
            $html .= "<pre>" . json_encode($result['field_info'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</pre>";
            $html .= "</details>";
        }
        
        $html .= "</div>";
    }
    
    return $html;
}

// Функция для вывода статистики
function formatStats($results) {
    $totalFields = count($results);
    $existingFields = count(array_filter($results, fn($r) => $r['status'] === 'exists'));
    $createdFields = count(array_filter($results, fn($r) => $r['status'] === 'success'));
    $errorFields = count(array_filter($results, fn($r) => $r['status'] === 'error'));
    
    return "
    <div class='stats'>
        <div class='stat-item'>
            <div class='stat-number'>$totalFields</div>
            <div class='stat-label'>Всего полей</div>
        </div>
        <div class='stat-item'>
            <div class='stat-number'>$existingFields</div>
            <div class='stat-label'>Уже существовало</div>
        </div>
        <div class='stat-item'>
            <div class='stat-number'>$createdFields</div>
            <div class='stat-label'>Создано новых</div>
        </div>
        <div class='stat-item'>
            <div class='stat-number'>$errorFields</div>
            <div class='stat-label'>Ошибок</div>
        </div>
    </div>";
}

try {
    // Инициализация
    $webhook = BITRIX24_WEBHOOK_URL;
    $call = new Usual($webhook);
    $logger = new Logger('web_uf_crm_fields_check.log');
    $fieldChecker = new UF_CRM_FieldChecker($call, $logger);
    
    $content = '';
    $startTime = time();
    
    // Тестовый запрос для проверки webhook
    $content .= '<div style="background: #e3f2fd; padding: 10px; border-radius: 4px; margin: 10px 0;">';
    $content .= '<p><strong>🔗 Webhook URL:</strong> ' . htmlspecialchars($webhook) . '</p>';
    
    try {
        $testResult = $call->callBitrix24API('user.get', ['ID' => 1]);
        if (isset($testResult['result'])) {
            $content .= '<p><strong>✅ Webhook работает:</strong> Успешное подключение к API</p>';
        } else {
            $content .= '<p><strong>⚠️ Webhook ответ:</strong> ' . json_encode($testResult, JSON_UNESCAPED_UNICODE) . '</p>';
        }
    } catch (Exception $e) {
        $content .= '<p><strong>❌ Ошибка webhook:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    $content .= '</div>';

    // Навигация
    $content .= '<div class="nav">
        <a href="?action=all">Проверить все поля</a>
        <a href="?action=list">Список полей</a>
        <a href="?action=types">Типы полей</a>
        <a href="?action=configs">Конфигурации</a>
        <a href="?action=help">Справка</a>
    </div>';

    $content .= "<p><strong>Время запуска:</strong> " . formatTime($startTime) . "</p>";

    switch ($action) {
        case 'all':
            $content .= '<h2>🔍 Проверка и создание всех необходимых полей UF_CRM в компаниях</h2>';
            
            $results = $fieldChecker->checkAndCreateAllFields();
            $content .= formatStats($results);
            $content .= formatResults($results);
            break;

        case 'field':
            if (empty($field)) {
                $content .= '<div class="error">❌ Не указан параметр field</div>';
                $content .= '<p>Используйте: <code>?action=field&field=UF_CRM_PROJECT_LINK</code></p>';
                break;
            }
            
            $content .= "<h2>🔍 Проверка поля: <code>$field</code></h2>";
            
            if ($fieldChecker->checkFieldExists($field, 'COMPANY')) {
                $fieldInfo = $fieldChecker->getFieldInfo($field, 'COMPANY');
                $content .= '<div class="success">✅ Поле уже существует в компаниях</div>';
                $content .= '<div class="field-info">';
                $content .= "<p><strong>ID поля:</strong> {$fieldInfo['ID']}</p>";
                $content .= "<p><strong>Название:</strong> {$fieldInfo['LIST_COLUMN_LABEL']}</p>";
                $content .= "<p><strong>Тип:</strong> {$fieldInfo['USER_TYPE_ID']}</p>";
                $content .= "<p><strong>Обязательное:</strong> " . ($fieldInfo['MANDATORY'] === 'Y' ? 'Да' : 'Нет') . "</p>";
                if (!empty($fieldInfo['HELP_MESSAGE'])) {
                    $content .= "<p><strong>Справка:</strong> {$fieldInfo['HELP_MESSAGE']}</p>";
                }
                $content .= '</div>';
            } else {
                $content .= '<div class="warning">⚠️ Поле не найдено в компаниях</div>';
                
                if ($fieldChecker->isFieldConfigured($field)) {
                    $content .= '<p>Создание поля...</p>';
                    $result = $fieldChecker->createFieldFromConfig($field);
                    
                    if ($result['status'] === 'success') {
                        $content .= '<div class="success">✅ Поле успешно создано в компаниях!</div>';
                        $content .= "<p><strong>ID поля:</strong> {$result['field_id']}</p>";
                    } else {
                        $content .= '<div class="error">❌ Ошибка при создании поля: ' . $result['message'] . '</div>';
                    }
                } else {
                    $content .= '<div class="error">❌ Поле не найдено в конфигурации</div>';
                    $content .= '<p>Доступные поля:</p><ul>';
                    $config = $fieldChecker->getAllFieldsConfig();
                    foreach ($config as $code => $fieldConfig) {
                        $content .= "<li><code>$code</code>: {$fieldConfig['name']}</li>";
                    }
                    $content .= '</ul>';
                }
            }
            break;

        case 'list':
            $content .= '<h2>📋 Список всех пользовательских полей компаний</h2>';
            
            $fields = $fieldChecker->getAllCompanyFields();
            
            if (empty($fields)) {
                $content .= '<div class="warning">⚠️ Пользовательские поля не найдены</div>';
            } else {
                $content .= "<p><strong>Найдено полей:</strong> " . count($fields) . "</p>";
                
                foreach ($fields as $field) {
                    $content .= '<div class="field-info">';
                    $content .= "<p><strong>Код:</strong> <code>{$field['FIELD_NAME']}</code></p>";
                    $content .= "<p><strong>Название:</strong> {$field['LIST_COLUMN_LABEL']}</p>";
                    $content .= "<p><strong>Тип:</strong> {$field['USER_TYPE_ID']}</p>";
                    $content .= "<p><strong>Обязательное:</strong> " . ($field['MANDATORY'] === 'Y' ? 'Да' : 'Нет') . "</p>";
                    $content .= '</div>';
                }
            }
            break;

        case 'types':
            $content .= '<h2>🔧 Доступные типы пользовательских полей</h2>';
            
            try {
                $content .= '<p><strong>Отправляем запрос:</strong> userfieldconfig.getTypes с moduleId: crm</p>';
                
                $result = $call->callBitrix24API('userfieldconfig.getTypes', [
                    'moduleId' => 'crm'
                ]);
                
                $content .= '<details><summary>Полный ответ API</summary>';
                $content .= '<pre>' . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</pre>';
                $content .= '</details>';
                
                $types = $result['result']['types'] ?? [];
                
                if (empty($types)) {
                    $content .= '<div class="warning">⚠️ Типы полей не найдены</div>';
                    $content .= '<p><strong>Возможные причины:</strong></p>';
                    $content .= '<ul>';
                    $content .= '<li>Неправильный moduleId (попробуйте "crm" или "iblock")</li>';
                    $content .= '<li>Нет прав доступа к API</li>';
                    $content .= '<li>Проблемы с webhook</li>';
                    $content .= '</ul>';
                } else {
                    $content .= "<p><strong>Найдено типов:</strong> " . count($types) . "</p>";
                    
                    foreach ($types as $type) {
                        $content .= '<div class="field-info">';
                        $content .= "<p><strong>ID:</strong> <code>{$type['userTypeId']}</code></p>";
                        $content .= "<p><strong>Название:</strong> {$type['description']}</p>";
                        $content .= '</div>';
                    }
                }
            } catch (Exception $e) {
                $content .= '<div class="error">❌ Ошибка при получении типов полей: ' . htmlspecialchars($e->getMessage()) . '</div>';
                $content .= '<details><summary>Детали ошибки</summary>';
                $content .= '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
                $content .= '</details>';
            }
            break;

        case 'configs':
            $content .= '<h2>⚙️ Конфигурации пользовательских полей CRM</h2>';
            
            try {
                $content .= '<p><strong>Отправляем запрос:</strong> userfieldconfig.list с фильтром CRM_COMPANY</p>';
                
                $result = $call->callBitrix24API('userfieldconfig.list', [
                    'moduleId' => 'crm',
                    'filter' => [
                        'ENTITY_ID' => 'CRM_COMPANY'
                    ],
                    'select' => [
                        'ID',
                        'FIELD_NAME',
                        'USER_TYPE_ID',
                        'ENTITY_ID',
                        'SORT',
                        'MULTIPLE',
                        'MANDATORY',
                        'SHOW_FILTER',
                        'SHOW_IN_LIST',
                        'EDIT_IN_LIST',
                        'IS_SEARCHABLE',
                        'LIST_COLUMN_LABEL',
                        'LIST_FILTER_LABEL',
                        'HELP_MESSAGE',
                        'SETTINGS'
                    ],
                    'order' => [
                        'SORT' => 'ASC'
                    ],
                    'start' => 0
                ]);
                
                $content .= '<details><summary>Полный ответ API</summary>';
                $content .= '<pre>' . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</pre>';
                $content .= '</details>';
                
                $configs = $result['result'] ?? [];
                
                if (empty($configs)) {
                    $content .= '<div class="warning">⚠️ Конфигурации полей не найдены</div>';
                    $content .= '<p><strong>Возможные причины:</strong></p>';
                    $content .= '<ul>';
                    $content .= '<li>Нет пользовательских полей в компаниях</li>';
                    $content .= '<li>Неправильный фильтр ENTITY_ID (попробуйте "CRM_DEAL", "CRM_CONTACT")</li>';
                    $content .= '<li>Нет прав доступа к API</li>';
                    $content .= '<li>Проблемы с webhook</li>';
                    $content .= '</ul>';
                    
                    // Попробуем получить все конфигурации без фильтра
                    $content .= '<h3>Попробуем получить все конфигурации без фильтра:</h3>';
                    $resultAll = $call->callBitrix24API('userfieldconfig.list', [
                        'moduleId' => 'crm',
                        'select' => ['ID', 'FIELD_NAME', 'ENTITY_ID'],
                        'start' => 0
                    ]);
                    
                    $content .= '<details><summary>Все конфигурации (без фильтра)</summary>';
                    $content .= '<pre>' . json_encode($resultAll, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</pre>';
                    $content .= '</details>';
                } else {
                    $content .= "<p><strong>Найдено конфигураций:</strong> " . count($configs) . "</p>";
                    
                    foreach ($configs as $config) {
                        $content .= '<div class="field-info">';
                        $content .= "<p><strong>ID:</strong> {$config['ID']}</p>";
                        $content .= "<p><strong>Код поля:</strong> <code>{$config['FIELD_NAME']}</code></p>";
                        $content .= "<p><strong>Название:</strong> {$config['LIST_COLUMN_LABEL']}</p>";
                        $content .= "<p><strong>Тип:</strong> {$config['USER_TYPE_ID']}</p>";
                        $content .= "<p><strong>Сущность:</strong> {$config['ENTITY_ID']}</p>";
                        $content .= "<p><strong>Сортировка:</strong> {$config['SORT']}</p>";
                        $content .= "<p><strong>Множественное:</strong> " . ($config['MULTIPLE'] === 'Y' ? 'Да' : 'Нет') . "</p>";
                        $content .= "<p><strong>Обязательное:</strong> " . ($config['MANDATORY'] === 'Y' ? 'Да' : 'Нет') . "</p>";
                        $content .= "<p><strong>В фильтре:</strong> " . ($config['SHOW_FILTER'] === 'Y' ? 'Да' : 'Нет') . "</p>";
                        $content .= "<p><strong>В списке:</strong> " . ($config['SHOW_IN_LIST'] === 'Y' ? 'Да' : 'Нет') . "</p>";
                        $content .= "<p><strong>Редактирование в списке:</strong> " . ($config['EDIT_IN_LIST'] === 'Y' ? 'Да' : 'Нет') . "</p>";
                        $content .= "<p><strong>Поиск:</strong> " . ($config['IS_SEARCHABLE'] === 'Y' ? 'Да' : 'Нет') . "</p>";
                        
                        if (!empty($config['HELP_MESSAGE'])) {
                            $content .= "<p><strong>Справка:</strong> {$config['HELP_MESSAGE']}</p>";
                        }
                        
                        if (!empty($config['SETTINGS'])) {
                            $content .= "<details><summary>Настройки поля</summary>";
                            $content .= "<pre>" . json_encode($config['SETTINGS'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</pre>";
                            $content .= "</details>";
                        }
                        
                        $content .= '</div>';
                    }
                }
            } catch (Exception $e) {
                $content .= '<div class="error">❌ Ошибка при получении конфигураций полей: ' . htmlspecialchars($e->getMessage()) . '</div>';
                $content .= '<details><summary>Детали ошибки</summary>';
                $content .= '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
                $content .= '</details>';
            }
            break;

        case 'help':
        default:
            $content .= '<div class="help-section">
                <h3>📖 Справка по использованию</h3>
                <p>Этот веб-интерфейс позволяет проверять и создавать пользовательские поля UF_CRM в компаниях Битрикс24.</p>
                
                <h4>Доступные действия:</h4>
                <ul>
                    <li><strong>Проверить все поля:</strong> <code>?action=all</code></li>
                    <li><strong>Проверить конкретное поле:</strong> <code>?action=field&field=UF_CRM_PROJECT_LINK</code></li>
                    <li><strong>Список всех полей:</strong> <code>?action=list</code></li>
                    <li><strong>Типы полей:</strong> <code>?action=types</code></li>
                    <li><strong>Конфигурации полей:</strong> <code>?action=configs</code></li>
                    <li><strong>Справка:</strong> <code>?action=help</code></li>
                </ul>
                
                <h4>Примеры использования:</h4>
                <ul>
                    <li><code>web_check_fields.php?action=all</code> - Проверить все поля</li>
                    <li><code>web_check_fields.php?action=field&field=UF_CRM_FRONTEND_RATE</code> - Проверить поле тарифа</li>
                    <li><code>web_check_fields.php?action=list</code> - Показать все поля</li>
                    <li><code>web_check_fields.php?action=types</code> - Показать доступные типы полей</li>
                    <li><code>web_check_fields.php?action=configs</code> - Показать конфигурации полей</li>
                </ul>
                
                <h4>Поддерживаемые поля:</h4>
                <ul>
                    <li><code>UF_CRM_PROJECT_LINK</code> - Ссылка на проект</li>
                    <li><code>UF_CRM_EXTRANET_USER</code> - Пользователь экстранета</li>
                    <li><code>UF_CRM_HOURS_LIMIT</code> - Лимит часов</li>
                    <li><code>UF_CRM_NOTIFY_DATE</code> - Дата уведомления</li>
                    <li><code>UF_CRM_FRONTEND_RATE</code> - Тариф Front-end разработчика</li>
                    <li><code>UF_CRM_BACKEND_RATE</code> - Тариф Back-end разработчика</li>
                    <li><code>UF_CRM_DESIGNER_RATE</code> - Тариф Дизайнера</li>
                    <li><code>UF_CRM_PM_RATE</code> - Тариф Проект-менеджера</li>
                    <li><code>UF_CRM_CONTENT_MANAGER_RATE</code> - Тариф Контент-менеджера</li>
                </ul>
                
                <h4>API методы Битрикс24:</h4>
                <ul>
                    <li><strong>userfieldconfig.getTypes</strong> - Получить доступные типы пользовательских полей</li>
                    <li><strong>userfieldconfig.list</strong> - Получить список конфигураций пользовательских полей</li>
                </ul>
                
                <h4>Подробная документация API:</h4>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 10px 0;">
                    <h5>userfieldconfig.getTypes({moduleId: string})</h5>
                    <p>Получает список доступных типов пользовательских полей для модуля.</p>
                    <p><strong>Параметры:</strong></p>
                    <ul>
                        <li><code>moduleId</code> (string) - ID модуля (например, "crm", "iblock")</li>
                    </ul>
                    <p><strong>Пример:</strong> <code>?action=types</code></p>
                </div>
                
                <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 10px 0;">
                    <h5>userfieldconfig.list({moduleId: string, select: ?{}, order: ?{}, filter: ?{}, start: number = 0})</h5>
                    <p>Получает список конфигураций пользовательских полей для модуля.</p>
                    <p><strong>Параметры:</strong></p>
                    <ul>
                        <li><code>moduleId</code> (string) - ID модуля (обязательный)</li>
                        <li><code>select</code> (object) - Поля для выборки (опционально)</li>
                        <li><code>order</code> (object) - Сортировка (опционально)</li>
                        <li><code>filter</code> (object) - Фильтрация (опционально)</li>
                        <li><code>start</code> (number) - Начальная позиция (по умолчанию 0)</li>
                    </ul>
                    <p><strong>Пример:</strong> <code>?action=configs</code></p>
                </div>
            </div>';
            break;
    }

    $endTime = time();
    $executionTime = $endTime - $startTime;
    
    $content .= "<hr>";
    $content .= "<p><strong>Время завершения:</strong> " . formatTime($endTime) . "</p>";
    $content .= "<p><strong>Время выполнения:</strong> {$executionTime} сек.</p>";

    outputHtml('Управление полями UF_CRM', $content);

} catch (Exception $e) {
    $errorContent = '<div class="error">❌ КРИТИЧЕСКАЯ ОШИБКА: ' . htmlspecialchars($e->getMessage()) . '</div>';
    $errorContent .= '<details><summary>Стек вызовов</summary>';
    $errorContent .= '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre></details>';
    
    outputHtml('Ошибка - Управление полями UF_CRM', $errorContent);
}
