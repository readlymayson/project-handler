<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'logs/error.log');
error_reporting(E_ALL);

try {
    if (!file_exists('set.php')) {
        throw new Exception('Файл set.php не найден.');
    }
    if (!file_exists('class.php')) {
        throw new Exception('Файл class.php не найден.');
    }
    if (!file_exists('../logger/class.php')) {
        throw new Exception('Файл logger/class.php не найден.');
    }
    if (!file_exists('../require/usualClass.php')) {
        throw new Exception('Файл ../require/usualClass.php не найден.');
    }
    if (!file_exists('DealCreator.php')) {
        throw new Exception('Файл DealCreator.php не найден.');
    }
    if (!file_exists('config.php')) {
        throw new Exception('Файл config.php не найден.');
    }
    
    require_once 'set.php';
    require_once '../require/usualClass.php';
    require_once 'class.php';
    require_once 'config.php';
    require_once 'DealCreator.php';
    require_once '../logger/class.php';
    
    $call = new Usual(BITRIX24_WEBHOOK_URL);
    $logger = new Logger('deal_creation_test.log', __DIR__ . '/logs');
    $check = new ProjectCheck($call, $logger);
    $dealCreator = new DealCreator($call, $logger);

    echo "========================================\n";
    echo "ТЕСТИРОВАНИЕ СОЗДАНИЯ СДЕЛОК\n";
    echo "'Работы за предыдущий месяц'\n";
    echo "========================================\n\n";
    
    // Показываем настройки оптимизации
    echo "⚙️ НАСТРОЙКИ ОПТИМИЗАЦИИ API:\n";
    echo "----------------------------------------\n";
    echo "• Задержка между API вызовами: " . API_DELAY_BETWEEN_CALLS . " сек\n";
    echo "• Задержка между батчами: " . API_DELAY_BETWEEN_BATCHES . " сек\n";
    echo "• Таймаут API: " . API_TIMEOUT . " сек\n";
    echo "• Максимум повторов: " . API_MAX_RETRIES . "\n";
    echo "• Задержка перед повтором: " . API_RETRY_DELAY . " сек\n";
    echo "\nЭти настройки помогают избежать ошибки 504 (Gateway Timeout)\n\n";
    
    // Тестируем функцию извлечения ID проекта
    echo "ТЕСТИРОВАНИЕ ФУНКЦИИ ИЗВЛЕЧЕНИЯ ID ПРОЕКТА\n";
    echo "========================================\n";
    $testLinks = [
        '168' => 168,
        'https://akvilon-marketing.bitrix24.ru/workgroups/group/456/' => 456,
        '/workgroups/group/789/' => 789,
        'group_id=101' => 101,
        'id=202' => 202,
        'some text with 303 number' => 303,
        '' => 0,
        'no numbers here' => 0
    ];
    
    foreach ($testLinks as $link => $expectedId) {
        $extractedId = $dealCreator->extractProjectId($link);
        $status = $extractedId === $expectedId ? '✅' : '❌';
        echo "$status '$link' → $extractedId (ожидалось: $expectedId)\n";
    }
    echo "\n";
    
    // Получаем компании с проектами
    $companies = $check->getCompaniesWithProjectLink();
    echo "Найдено компаний с проектами: " . count($companies) . "\n\n";
    
    if (empty($companies)) {
        echo "❌ Нет компаний с привязанными проектами для тестирования.\n";
        exit;
    }
    
    // Берем первую компанию для тестирования
    $testCompany = $companies[0];
    $projectLink = $testCompany['UF_CRM_PROJECT_LINK'] ?? '';
    $projectId = $dealCreator->extractProjectId($projectLink);
    
    echo "ТЕСТИРОВАНИЕ ДЛЯ КОМПАНИИ\n";
    echo "========================\n";
    echo "ID: #{$testCompany['ID']}\n";
    echo "Название: {$testCompany['TITLE']}\n";
    echo "Ссылка на проект: $projectLink\n";
    echo "Извлеченный ID проекта: $projectId\n\n";
    
    if ($projectId == 0) {
        echo "❌ У компании не указана ссылка на проект или не удалось извлечь ID.\n";
        exit;
    }
    
    // Получаем данные о времени по проекту
    echo "ПОЛУЧЕНИЕ ДАННЫХ О ВРЕМЕНИ ПО ПРОЕКТУ\n";
    echo "====================================\n";
    $defaultPrice = $testCompany['UF_CRM_DEFAULT_RATE'] ?? DEFAULT_HOURLY_RATE;
    // Убираем |RUB из цены если есть
    $cleanPrice = is_string($defaultPrice) ? str_replace('|RUB', '', $defaultPrice) : $defaultPrice;
    echo "Дефолтная цена из компании: " . $cleanPrice . " руб/ч\n\n";
    
    // Получаем детальную информацию о задачах
    echo "⏳ Получение задач проекта (это может занять некоторое время)...\n";
    echo "   Проект ID: $projectId\n";
    echo "   Начало: " . date('Y-m-d H:i:s') . "\n";
    
    try {
        $startTime = microtime(true);
        $tasks = $dealCreator->getProjectTasks($projectId);
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        
        $tasksCount = count($tasks);
        echo "✅ Задачи успешно получены за {$executionTime} сек\n";
        echo "   Количество задач: $tasksCount\n\n";
    } catch (Exception $e) {
        echo "❌ Ошибка при получении задач: " . $e->getMessage() . "\n";
        echo "   Файл: " . $e->getFile() . "\n";
        echo "   Строка: " . $e->getLine() . "\n";
        $logger->log([
            'error' => 'get_project_tasks_failed', 
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        exit;
    }
    
    echo "\n📊 СТАТИСТИКА ЗАДАЧ:\n";
    echo "-------------------\n";
    echo "Всего найдено задач: $tasksCount\n\n";
    
    // Логируем информацию о задачах
    $logger->log([
        'action' => 'project_tasks_analysis',
        'project_id' => $projectId,
        'company_id' => $testCompany['ID'],
        'tasks_count' => $tasksCount,
        'default_price' => $defaultPrice
    ]);
    
    if ($tasksCount > 0) {
        echo "📋 ДЕТАЛИ ЗАДАЧ:\n";
        echo "================\n";
        echo sprintf("%-8s %-50s %-15s %-15s %-20s\n", "ID", "Название", "Время в логах", "Оценка времени", "Дата закрытия");
        echo str_repeat("-", 108) . "\n";
        
        $totalTimeInLogs = 0;
        $totalTimeEstimate = 0;
        
        foreach ($tasks as $task) {
            // API возвращает данные в camelCase, приводим к нужному формату
            $timeSpent = $task['timeSpentInLogs'] ?? $task['TIME_SPENT_IN_LOGS'] ?? 0;
            $timeEstimate = $task['timeEstimate'] ?? $task['TIME_ESTIMATE'] ?? 0;
            $closedDate = $task['closedDate'] ?? $task['CLOSED_DATE'] ?? 'не закрыта';
            $taskId = $task['id'] ?? $task['ID'] ?? 0;
            $taskTitle = $task['title'] ?? $task['TITLE'] ?? 'Без названия';
            
            $totalTimeInLogs += $timeSpent;
            $totalTimeEstimate += $timeEstimate;
            
            $title = mb_substr($taskTitle, 0, 47) . (mb_strlen($taskTitle) > 47 ? '...' : '');
            echo sprintf("%-8s %-50s %-15s %-15s %-20s\n", 
                $taskId, 
                $title, 
                $timeSpent . ' сек', 
                $timeEstimate . ' сек', 
                $closedDate
            );
            
            // Логируем детали каждой задачи
            $logger->log([
                'action' => 'task_details',
                'task_id' => $taskId,
                'task_title' => $taskTitle,
                'time_spent_seconds' => $timeSpent,
                'time_estimate_seconds' => $timeEstimate,
                'closed_date' => $closedDate
            ]);
        }
        
        echo "\n";
        
        // Конвертируем секунды в часы для отображения
        $totalHoursInLogs = round($totalTimeInLogs / 3600, 2);
        $totalHoursEstimate = round($totalTimeEstimate / 3600, 2);
        
        echo "Общее время в логах: {$totalTimeInLogs} сек ({$totalHoursInLogs} ч)\n";
        echo "Общая оценка времени: {$totalTimeEstimate} сек ({$totalHoursEstimate} ч)\n\n";
        
        // Логируем общую статистику по задачам
        $logger->log([
            'action' => 'tasks_summary',
            'total_time_spent_seconds' => $totalTimeInLogs,
            'total_time_spent_hours' => $totalHoursInLogs,
            'total_time_estimate_seconds' => $totalTimeEstimate,
            'total_time_estimate_hours' => $totalHoursEstimate
        ]);
    } else {
        echo "❌ Задач не найдено\n\n";
        $logger->log([
            'action' => 'no_tasks_found',
            'project_id' => $projectId
        ]);
    }
    
    echo "⏳ Анализ времени по задачам (это может занять некоторое время)...\n";
    echo "   Начало: " . date('Y-m-d H:i:s') . "\n";
    
    try {
        $startTime = microtime(true);
        $projectTimeData = $dealCreator->getProjectTimeData($projectId, $defaultPrice);
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        
        echo "✅ Анализ времени завершен за {$executionTime} сек\n\n";
    } catch (Exception $e) {
        echo "❌ Ошибка при анализе времени: " . $e->getMessage() . "\n";
        echo "   Файл: " . $e->getFile() . "\n";
        echo "   Строка: " . $e->getLine() . "\n";
        $logger->log([
            'error' => 'get_project_time_data_failed', 
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        exit;
    }
    
    echo "\n💰 ИТОГОВАЯ СТАТИСТИКА:\n";
    echo "======================\n";
    echo "Общее время: {$projectTimeData['total_hours']} часов\n";
    echo "Общая стоимость: {$projectTimeData['total_cost']} руб.\n\n";
    
    // Проверяем условия и логируем соответствие
    echo "🔍 ПРОВЕРКА УСЛОВИЙ:\n";
    echo "===================\n";
    
    $conditionsCheck = [
        'min_hour_rounding' => MIN_HOUR_ROUNDING,
        'rounding_threshold' => ROUNDING_THRESHOLD,
        'total_hours' => $projectTimeData['total_hours'],
        'meets_minimum_time' => $projectTimeData['total_hours'] >= MIN_HOUR_ROUNDING,
        'has_time_data' => !empty($projectTimeData['roles_time'])
    ];
    
    echo "• Минимальное округление: " . MIN_HOUR_ROUNDING . " час\n";
    echo "• Порог округления: " . ROUNDING_THRESHOLD . " часа (10 минут)\n";
    echo "• Общее время: {$projectTimeData['total_hours']} часов\n";
    echo "• Соответствует минимальному времени: " . ($conditionsCheck['meets_minimum_time'] ? '✅ Да' : '❌ Нет') . "\n";
    echo "• Есть данные по ролям: " . ($conditionsCheck['has_time_data'] ? '✅ Да' : '❌ Нет') . "\n\n";
    
    // Логируем проверку условий
    $logger->log([
        'action' => 'conditions_check',
        'conditions' => $conditionsCheck,
        'project_id' => $projectId
    ]);
    
    if (!empty($projectTimeData['roles_time'])) {
        echo "👥 ДЕТАЛЬНАЯ РАЗБИВКА ПО РОЛЯМ:\n";
        echo "===============================\n";
        echo sprintf("%-20s %-8s %-8s %-15s %-12s %-12s %-20s\n", "Роль", "Часы", "Минуты", "Десятичные часы", "Ставка", "Стоимость", "Округление");
        echo str_repeat("-", 95) . "\n";
        
        $totalCalculatedCost = 0;
        
        foreach ($projectTimeData['roles_time'] as $role => $timeData) {
            $rate = getRoleRate($role, $defaultPrice);
            $cost = $timeData['decimal_hours'] * $rate;
            $totalCalculatedCost += $cost;
            
            // Проверяем, было ли применено округление
            $originalHours = $timeData['decimal_hours'];
            $wasRounded = false;
            
            if ($originalHours < MIN_HOUR_ROUNDING && $originalHours > ROUNDING_THRESHOLD) {
                $wasRounded = true;
            }
            
            $roundingStatus = $wasRounded ? '✅ Да' : '❌ Нет';
            
            echo sprintf("%-20s %-8s %-8s %-15s %-12s %-12s %-20s\n",
                $role,
                $timeData['hours'],
                $timeData['minutes'],
                $timeData['decimal_hours'],
                $rate . ' руб/ч',
                number_format($cost, 2) . ' руб',
                $roundingStatus
            );
            
            // Логируем детали каждой роли
            $logger->log([
                'action' => 'role_time_details',
                'role' => $role,
                'hours' => $timeData['hours'],
                'minutes' => $timeData['minutes'],
                'decimal_hours' => $timeData['decimal_hours'],
                'rate' => $rate,
                'cost' => $cost,
                'was_rounded' => $wasRounded,
                'original_hours' => $originalHours
            ]);
        }
        
        echo "\n";
        
        echo "Общая рассчитанная стоимость: " . number_format($totalCalculatedCost, 2) . " руб.\n";
        echo "Стоимость из системы: " . number_format($projectTimeData['total_cost'], 2) . " руб.\n";
        
        // Проверяем соответствие расчетов
        $costDifference = abs($totalCalculatedCost - $projectTimeData['total_cost']);
        $costsMatch = $costDifference < 0.01; // Допускаем разницу в 1 копейку
        
        echo "Расчеты соответствуют: " . ($costsMatch ? '✅ Да' : '❌ Нет') . "\n";
        if (!$costsMatch) {
            echo "Разница: " . number_format($costDifference, 2) . " руб.\n";
        }
        echo "\n";
        
        // Логируем итоговую статистику по ролям
        $logger->log([
            'action' => 'roles_summary',
            'total_calculated_cost' => $totalCalculatedCost,
            'system_total_cost' => $projectTimeData['total_cost'],
            'cost_difference' => $costDifference,
            'costs_match' => $costsMatch,
            'roles_count' => count($projectTimeData['roles_time'])
        ]);
        
    } else {
        echo "❌ Нет данных о времени по ролям.\n\n";
        $logger->log([
            'action' => 'no_roles_data',
            'project_id' => $projectId
        ]);
    }
    
    // Тестируем создание сделки (только если есть время)
    if ($projectTimeData['total_hours'] > 0) {
        echo "СОЗДАНИЕ ТЕСТОВОЙ СДЕЛКИ\n";
        echo "=======================\n";
        echo "⚠️  ВНИМАНИЕ: Это создаст реальную сделку в Bitrix24!\n\n";
        
        // Раскомментируйте следующую строку для реального создания сделки
        $result = $dealCreator->createMonthlyWorkDeal($testCompany, $projectTimeData);
        $logger->log($result);
        
        echo "Для реального создания сделки раскомментируйте соответствующие строки в коде.\n";
        echo "Результат будет записан в лог файл.\n\n";
    } else {
        echo "Нет затраченного времени - сделка не будет создана.\n\n";
    }
    
    // Финальное логирование результатов теста
    $logger->log([
        'action' => 'test_completed',
        'project_id' => $projectId,
        'company_id' => $testCompany['ID'],
        'company_title' => $testCompany['TITLE'],
        'total_tasks' => $tasksCount,
        'total_hours' => $projectTimeData['total_hours'],
        'total_cost' => $projectTimeData['total_cost'],
        'default_price' => $defaultPrice,
        'conditions_met' => $conditionsCheck['meets_minimum_time'] && $conditionsCheck['has_time_data'],
        'test_timestamp' => date('Y-m-d H:i:s')
    ]);
    
    echo "✅ ТЕСТ ЗАВЕРШЕН\n";
    echo "===============\n";
    echo "Все данные записаны в лог файл: deal_creation_test.log\n";
    
} catch (Exception $e) {
    echo "❌ ОШИБКА: " . $e->getMessage() . "\n";
    if (isset($logger)) {
        $logger->log(['error' => $e->getMessage()]);
    }
}
?>
