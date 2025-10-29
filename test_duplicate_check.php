<?php
# -*- coding: utf-8 -*-

require_once 'config.php';
require_once 'DealCreator.php';

/**
 * Тест проверки дубликатов сделок в воронке
 */

// Мок-классы для тестирования
class MockUsual {
    private $calls = [];
    
    public function callBitrix24API($method, $params) {
        $this->calls[] = ['method' => $method, 'params' => $params];
        
        if ($method === 'crm.deal.list') {
            // Симулируем проверку дубликатов
            $filter = $params['filter'] ?? [];
            
            // Проверяем, что фильтр содержит CATEGORY_ID
            if (isset($filter['CATEGORY_ID'])) {
                echo "✅ Фильтр по воронке CATEGORY_ID: " . $filter['CATEGORY_ID'] . "\n";
            } else {
                echo "❌ ОШИБКА: Фильтр не содержит CATEGORY_ID!\n";
            }
            
            // Симулируем нахождение дубликата
            return [
                'result' => [
                    [
                        'ID' => 74,
                        'TITLE' => 'Тестовый проект',
                        'BEGINDATE' => date('Y-m-d'),
                        'CATEGORY_ID' => MONTHLY_WORK_FUNNEL_ID
                    ]
                ]
            ];
        }
        
        if ($method === 'tasks.task.list') {
            return [
                'result' => [
                    'tasks' => []
                ]
            ];
        }
        
        return ['result' => []];
    }
    
    public function getCalls() {
        return $this->calls;
    }
}

class MockLogger {
    public function log($message) {
        if (is_array($message)) {
            if (isset($message['type']) && $message['type'] === 'duplicate_check') {
                echo "📋 Лог проверки дубликатов:\n";
                echo "   Статус: " . $message['status'] . "\n";
                echo "   Проект ID: " . ($message['project_id'] ?? 'N/A') . "\n";
                echo "   Воронка ID: " . ($message['funnel_id'] ?? 'N/A') . "\n";
                if (isset($message['duplicates_count'])) {
                    echo "   Найдено дубликатов: " . $message['duplicates_count'] . "\n";
                }
                if (isset($message['message'])) {
                    echo "   Сообщение: " . $message['message'] . "\n";
                }
                echo "\n";
            }
        }
    }
}

echo "=== ТЕСТ ПРОВЕРКИ ДУБЛИКАТОВ СДЕЛОК ===\n\n";

$mockCall = new MockUsual();
$mockLogger = new MockLogger();
$dealCreator = new DealCreator($mockCall, $mockLogger);

// Тест 1: Проверка включена
echo "Тест 1: Проверка дубликатов (CHECK_DUPLICATES = " . (CHECK_DUPLICATES ? 'true' : 'false') . ")\n";
echo "Проект ID: 168\n";
echo "Воронка ID: " . MONTHLY_WORK_FUNNEL_ID . "\n\n";

// Используем рефлексию для вызова приватного метода
$reflection = new ReflectionClass($dealCreator);
$method = $reflection->getMethod('checkDuplicateDeal');
$method->setAccessible(true);

$hasDuplicate = $method->invoke($dealCreator, 168);

echo "\nРезультат проверки: " . ($hasDuplicate ? "✅ Найден дубликат" : "❌ Дубликат не найден") . "\n";

// Проверяем, что был вызван правильный API
$calls = $mockCall->getCalls();
foreach ($calls as $call) {
    if ($call['method'] === 'crm.deal.list') {
        echo "\n=== ДЕТАЛИ API ВЫЗОВА ===\n";
        echo "Метод: " . $call['method'] . "\n";
        echo "Фильтры:\n";
        foreach ($call['params']['filter'] as $key => $value) {
            echo "  - $key: " . (is_array($value) ? json_encode($value) : $value) . "\n";
        }
        echo "\n";
        
        // Проверяем наличие CATEGORY_ID
        if (isset($call['params']['filter']['CATEGORY_ID'])) {
            echo "✅ ФИЛЬТР ПО ВОРОНКЕ ПРИСУТСТВУЕТ\n";
            if ($call['params']['filter']['CATEGORY_ID'] == MONTHLY_WORK_FUNNEL_ID) {
                echo "✅ ФИЛЬТР ПО ПРАВИЛЬНОЙ ВОРОНКЕ (" . MONTHLY_WORK_FUNNEL_ID . ")\n";
            } else {
                echo "❌ ОШИБКА: Фильтр по другой воронке: " . $call['params']['filter']['CATEGORY_ID'] . "\n";
            }
        } else {
            echo "❌ ОШИБКА: ФИЛЬТР ПО ВОРОНКЕ ОТСУТСТВУЕТ!\n";
        }
    }
}

echo "\n=== ИТОГОВЫЙ РЕЗУЛЬТАТ ===\n";
if ($hasDuplicate && isset($calls[0]['params']['filter']['CATEGORY_ID'])) {
    echo "✅ Проверка дубликатов работает корректно:\n";
    echo "   - Проверяет только сделки в воронке " . MONTHLY_WORK_FUNNEL_ID . "\n";
    echo "   - Правильно определяет дубликаты\n";
} else {
    echo "❌ Проверка дубликатов работает неправильно\n";
}

echo "\n";
