<?php
# -*- coding: utf-8 -*-

require_once 'config.php';
require_once 'set.php';
require_once '../require/usualClass.php';
require_once '../logger/class.php';
require_once 'DocumentGenerator.php';

/**
 * Тестовый файл для проверки генерации документов в сделке 80
 */

// Настройка логирования
$logger = new Logger('test_document_generation.log', __DIR__ . '/logs');

// Инициализация API
$call = new Usual(BITRIX24_WEBHOOK_URL);

echo "=== Тест генерации документов для сделки 80 ===\n\n";

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
    echo "Компания ID: {$deal['COMPANY_ID']}\n";
    echo "Проект ID: {$deal['UF_CRM_PROJECT_ID']}\n\n";
    
    // Получаем данные компании
    echo "2. Получаем данные компании...\n";
    apiDelay();
    $companyResult = $call->callBitrix24API('crm.company.get', ['id' => $deal['COMPANY_ID']]);
    
    if (isset($companyResult['error'])) {
        echo "Ошибка получения компании: " . $companyResult['error_description'] . "\n";
        exit(1);
    }
    
    $company = $companyResult['result'];
    echo "Компания найдена: {$company['TITLE']}\n\n";
    
    // Используем проект 168
    $projectId = 168;
    echo "Используем проект 168 для тестирования...\n";
    
    echo "3. Получаем данные проекта {$projectId}...\n";
    apiDelay();
    $projectResult = $call->callBitrix24API('sonet_group.get', ['id' => $projectId]);
    
    if (isset($projectResult['error'])) {
        echo "Ошибка получения проекта: " . $projectResult['error_description'] . "\n";
        exit(1);
    }
    
    $project = $projectResult['result'];
    echo "Проект найден: {$project['NAME']}\n\n";
    
    // Получаем данные о времени по проекту
    echo "4. Получаем данные о времени по проекту...\n";
    $projectTimeData = getProjectTimeData($projectId, $logger);
    
    if (empty($projectTimeData['roles_time'])) {
        echo "Ошибка: Нет данных о времени по проекту!\n";
        echo "Данные проекта: " . json_encode($projectTimeData, JSON_UNESCAPED_UNICODE) . "\n";
        exit(1);
    }
    
    echo "Найдено ролей: " . count($projectTimeData['roles_time']) . "\n";
    echo "Общее время: {$projectTimeData['total_hours']} часов\n";
    echo "Общая стоимость: {$projectTimeData['total_cost']} руб.\n\n";
    
    // Выводим детали по ролям
    echo "Детали по ролям:\n";
    foreach ($projectTimeData['roles_time'] as $role => $timeData) {
        $rate = getRoleRate($role, $company);
        $amount = $timeData['decimal_hours'] * $rate;
        echo "- {$role}: {$timeData['decimal_hours']} ч. × {$rate} руб. = {$amount} руб.\n";
    }
    echo "\n";
    
    // Создаем генератор документов
    echo "5. Создаем генератор документов...\n";
    $documentGenerator = new DocumentGenerator($call, $logger);
    
    // Проверяем настройки генератора
    $settings = $documentGenerator->checkDocumentGeneratorSettings();
    echo "Настройки генератора:\n";
    echo "- Включен: " . ($settings['enabled'] ? 'Да' : 'Нет') . "\n";
    echo "- Готов к работе: " . ($settings['is_ready'] ? 'Да' : 'Нет') . "\n";
    echo "- Настроенных шаблонов: {$settings['configured_templates']}/{$settings['total_templates']}\n";
    echo "- Шаблоны: " . json_encode($settings['templates'], JSON_UNESCAPED_UNICODE) . "\n\n";
    
    if (!$settings['is_ready']) {
        echo "Ошибка: Генератор не готов к работе!\n";
        echo "Проверьте настройки в config.php\n";
        exit(1);
    }
    
    // Генерируем документы
    echo "6. Генерируем документы...\n";
    $result = $documentGenerator->generateDocumentsForDeal(
        $deal['ID'],
        $company,
        $projectTimeData,
        $projectId
    );
    
    echo "Результат генерации:\n";
    echo "Статус: {$result['status']}\n";
    echo "Сообщение: {$result['message']}\n";
    
    if (isset($result['documents']) && !empty($result['documents'])) {
        echo "Созданные документы:\n";
        foreach ($result['documents'] as $type => $doc) {
            echo "- {$type}: ID = {$doc['id']}, Template ID = {$doc['templateId']}\n";
        }
    }
    
    echo "\n=== Тест завершен ===\n";
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    echo "Трассировка: " . $e->getTraceAsString() . "\n";
}

/**
 * Получает данные о времени по проекту
 */
function getProjectTimeData($projectId, $logger): array
{
    global $call;
    
    $lastMonth = new DateTime('first day of last month');
    $firstDay = clone $lastMonth;
    $firstDay->setTime(0, 0, 0);
    
    $lastDay = new DateTime('last day of ' . $lastMonth->format('Y-m'));
    $lastDay->setTime(23, 59, 59);
    
    $rolesTime = [];
    $totalHours = 0;
    $totalCost = 0;
    
    try {
        // Получаем список задач
        apiDelay();
        $response = $call->callBitrix24API('tasks.task.list', [
            'filter' => [
                'GROUP_ID' => $projectId,
            ],
            'select' => ['ID', 'TITLE', 'RESPONSIBLE_ID']
        ]);
        
        $tasksList = $response['result']['tasks'] ?? [];
        $logger->log("Найдено задач: " . count($tasksList));
        
        foreach ($tasksList as $task) {
            $taskId = $task['id'] ?? $task['ID'];
            $responsibleId = $task['responsibleId'] ?? $task['RESPONSIBLE_ID'];
            
            // Получаем время по задаче за период
            apiDelay();
            $timeResponse = $call->callBitrix24API('task.elapseditem.getlist', [
                'TASKID' => $taskId
            ]);
            
            $elapsedItems = $timeResponse['result'] ?? [];
            
            foreach ($elapsedItems as $item) {
                if (empty($item['CREATED_DATE'])) {
                    continue;
                }
                
                $createdDate = new DateTime($item['CREATED_DATE']);
                
                if ($createdDate >= $firstDay && $createdDate <= $lastDay) {
                    $seconds = (int)($item['SECONDS'] ?? 0);
                    $hours = $seconds / 3600;
                    
                    // Получаем роль пользователя
                    $role = getUserRole($responsibleId);
                    
                    if (!isset($rolesTime[$role])) {
                        $rolesTime[$role] = [
                            'decimal_hours' => 0,
                            'formatted_hours' => '0:00'
                        ];
                    }
                    
                    $rolesTime[$role]['decimal_hours'] += $hours;
                    $totalHours += $hours;
                }
            }
        }
        
        // Форматируем время
        foreach ($rolesTime as $role => &$timeData) {
            $timeData['formatted_hours'] = formatHours($timeData['decimal_hours']);
        }
        
        return [
            'roles_time' => $rolesTime,
            'total_hours' => $totalHours,
            'total_cost' => $totalCost // Будет рассчитано позже с учетом ставок
        ];
        
    } catch (Exception $e) {
        $logger->log("Ошибка при получении данных времени: " . $e->getMessage());
        return [
            'roles_time' => [],
            'total_hours' => 0,
            'total_cost' => 0
        ];
    }
}

/**
 * Получает роль пользователя
 */
function getUserRole($userId): string
{
    global $call;
    
    try {
        apiDelay();
        $result = $call->callBitrix24API('user.get', [
            'filter' => ['ID' => $userId]
        ]);
        
        $user = $result['result'][0] ?? [];
        $position = $user['WORK_POSITION'] ?? 'Не указано';
        
        // Маппинг должностей на роли
        $roleMapping = [
            'Front-end разработчик' => 'Front-end разработчик',
            'Back-end разработчик' => 'Back-end разработчик',
            'Дизайнер' => 'Дизайнер',
            'Проект-менеджер' => 'Проект-менеджер',
            'Контент-менеджер' => 'Контент-менеджер',
            'Директолог' => 'Директолог',
            'SEO-специалист' => 'SEO-специалист',
            'Юрист' => 'Юрист',
            'Битрикс24 разработчик' => 'Битрикс24 разработчик'
        ];
        
        return $roleMapping[$position] ?? $position;
        
    } catch (Exception $e) {
        return 'Не указано';
    }
}

/**
 * Форматирует часы в формат ЧЧ:ММ
 */
function formatHours($decimalHours): string
{
    $hours = floor($decimalHours);
    $minutes = round(($decimalHours - $hours) * 60);
    
    if ($minutes >= 60) {
        $hours++;
        $minutes = 0;
    }
    
    return sprintf('%d:%02d', $hours, $minutes);
}
