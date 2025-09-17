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

    echo "<h2>Тестирование создания сделок 'Работы за предыдущий месяц'</h2>\n";
    
    // Показываем настройки оптимизации
    echo "<div style='background-color: #f0f8ff; padding: 10px; margin: 10px 0; border-left: 4px solid #007cba;'>\n";
    echo "<h4>⚙️ Настройки оптимизации API:</h4>\n";
    echo "<ul>\n";
    echo "<li><strong>Задержка между API вызовами:</strong> " . API_DELAY_BETWEEN_CALLS . " сек</li>\n";
    echo "<li><strong>Задержка между батчами:</strong> " . API_DELAY_BETWEEN_BATCHES . " сек</li>\n";
    echo "<li><strong>Таймаут API:</strong> " . API_TIMEOUT . " сек</li>\n";
    echo "<li><strong>Максимум повторов:</strong> " . API_MAX_RETRIES . "</li>\n";
    echo "<li><strong>Задержка перед повтором:</strong> " . API_RETRY_DELAY . " сек</li>\n";
    echo "</ul>\n";
    echo "<p><em>Эти настройки помогают избежать ошибки 504 (Gateway Timeout)</em></p>\n";
    echo "</div>\n";
    
    // Тестируем функцию извлечения ID проекта
    echo "<h3>Тестирование функции извлечения ID проекта</h3>\n";
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
    
    echo "<ul>\n";
    foreach ($testLinks as $link => $expectedId) {
        $extractedId = $dealCreator->extractProjectId($link);
        $status = $extractedId === $expectedId ? '✅' : '❌';
        echo "<li>$status '$link' → $extractedId (ожидалось: $expectedId)</li>\n";
    }
    echo "</ul>\n";
    
    // Получаем компании с проектами
    $companies = $check->getCompaniesWithProjectLink();
    echo "<p>Найдено компаний с проектами: " . count($companies) . "</p>\n";
    
    if (empty($companies)) {
        echo "<p>Нет компаний с привязанными проектами для тестирования.</p>\n";
        exit;
    }
    
    // Берем первую компанию для тестирования
    $testCompany = $companies[0];
    $projectLink = $testCompany['UF_CRM_PROJECT_LINK'] ?? '';
    $projectId = $dealCreator->extractProjectId($projectLink);
    
    echo "<h3>Тестирование для компании #{$testCompany['ID']} - {$testCompany['TITLE']}</h3>\n";
    echo "<p>Ссылка на проект: $projectLink</p>\n";
    echo "<p>Извлеченный ID проекта: $projectId</p>\n";
    
    if ($projectId == 0) {
        echo "<p>У компании не указана ссылка на проект или не удалось извлечь ID.</p>\n";
        exit;
    }
    
    // Получаем данные о времени по проекту
    echo "<h4>Получение данных о времени по проекту...</h4>\n";
    $defaultPrice = $testCompany['UF_CRM_DEFAULT_RATE'] ?? null;
    echo "<p>Дефолтная цена из компании: " . ($defaultPrice ?? 'не задана') . " руб/ч</p>\n";
    
    // Получаем детальную информацию о задачах
    echo "<p>⏳ Получение задач проекта (это может занять некоторое время)...</p>\n";
    $tasks = $dealCreator->getProjectTasks($projectId);
    $tasksCount = count($tasks);
    
    echo "<h5>📊 Статистика задач:</h5>\n";
    echo "<p><strong>Всего найдено задач:</strong> $tasksCount</p>\n";
    
    // Логируем информацию о задачах
    $logger->log([
        'action' => 'project_tasks_analysis',
        'project_id' => $projectId,
        'company_id' => $testCompany['ID'],
        'tasks_count' => $tasksCount,
        'default_price' => $defaultPrice
    ]);
    
    if ($tasksCount > 0) {
        echo "<h5>📋 Детали задач:</h5>\n";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>ID</th><th>Название</th><th>Время в логах</th><th>Оценка времени</th><th>Дата закрытия</th></tr>\n";
        
        $totalTimeInLogs = 0;
        $totalTimeEstimate = 0;
        
        foreach ($tasks as $task) {
            $timeSpent = $task['TIME_SPENT_IN_LOGS'] ?? 0;
            $timeEstimate = $task['TIME_ESTIMATE'] ?? 0;
            $closedDate = $task['CLOSED_DATE'] ?? 'не закрыта';
            
            $totalTimeInLogs += $timeSpent;
            $totalTimeEstimate += $timeEstimate;
            
            echo "<tr>";
            echo "<td>{$task['ID']}</td>";
            echo "<td>" . htmlspecialchars($task['TITLE']) . "</td>";
            echo "<td>{$timeSpent} сек</td>";
            echo "<td>{$timeEstimate} сек</td>";
            echo "<td>{$closedDate}</td>";
            echo "</tr>\n";
            
            // Логируем детали каждой задачи
            $logger->log([
                'action' => 'task_details',
                'task_id' => $task['ID'],
                'task_title' => $task['TITLE'],
                'time_spent_seconds' => $timeSpent,
                'time_estimate_seconds' => $timeEstimate,
                'closed_date' => $closedDate
            ]);
        }
        
        echo "</table>\n";
        
        // Конвертируем секунды в часы для отображения
        $totalHoursInLogs = round($totalTimeInLogs / 3600, 2);
        $totalHoursEstimate = round($totalTimeEstimate / 3600, 2);
        
        echo "<p><strong>Общее время в логах:</strong> {$totalTimeInLogs} сек ({$totalHoursInLogs} ч)</p>\n";
        echo "<p><strong>Общая оценка времени:</strong> {$totalTimeEstimate} сек ({$totalHoursEstimate} ч)</p>\n";
        
        // Логируем общую статистику по задачам
        $logger->log([
            'action' => 'tasks_summary',
            'total_time_spent_seconds' => $totalTimeInLogs,
            'total_time_spent_hours' => $totalHoursInLogs,
            'total_time_estimate_seconds' => $totalTimeEstimate,
            'total_time_estimate_hours' => $totalHoursEstimate
        ]);
    } else {
        echo "<p>❌ Задач не найдено</p>\n";
        $logger->log([
            'action' => 'no_tasks_found',
            'project_id' => $projectId
        ]);
    }
    
    echo "<p>⏳ Анализ времени по задачам (это может занять некоторое время)...</p>\n";
    $projectTimeData = $dealCreator->getProjectTimeData($projectId, $defaultPrice);
    
    echo "<h5>💰 Итоговая статистика:</h5>\n";
    echo "<p><strong>Общее время:</strong> {$projectTimeData['total_hours']} часов</p>\n";
    echo "<p><strong>Общая стоимость:</strong> {$projectTimeData['total_cost']} руб.</p>\n";
    
    // Проверяем условия и логируем соответствие
    echo "<h5>🔍 Проверка условий:</h5>\n";
    
    $conditionsCheck = [
        'min_hour_rounding' => MIN_HOUR_ROUNDING,
        'rounding_threshold' => ROUNDING_THRESHOLD,
        'total_hours' => $projectTimeData['total_hours'],
        'meets_minimum_time' => $projectTimeData['total_hours'] >= MIN_HOUR_ROUNDING,
        'has_time_data' => !empty($projectTimeData['roles_time'])
    ];
    
    echo "<ul>\n";
    echo "<li><strong>Минимальное округление:</strong> " . MIN_HOUR_ROUNDING . " час</li>\n";
    echo "<li><strong>Порог округления:</strong> " . ROUNDING_THRESHOLD . " часа (10 минут)</li>\n";
    echo "<li><strong>Общее время:</strong> {$projectTimeData['total_hours']} часов</li>\n";
    echo "<li><strong>Соответствует минимальному времени:</strong> " . ($conditionsCheck['meets_minimum_time'] ? '✅ Да' : '❌ Нет') . "</li>\n";
    echo "<li><strong>Есть данные по ролям:</strong> " . ($conditionsCheck['has_time_data'] ? '✅ Да' : '❌ Нет') . "</li>\n";
    echo "</ul>\n";
    
    // Логируем проверку условий
    $logger->log([
        'action' => 'conditions_check',
        'conditions' => $conditionsCheck,
        'project_id' => $projectId
    ]);
    
    if (!empty($projectTimeData['roles_time'])) {
        echo "<h5>👥 Детальная разбивка по ролям:</h5>\n";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>Роль</th><th>Часы</th><th>Минуты</th><th>Десятичные часы</th><th>Ставка (руб/ч)</th><th>Стоимость (руб)</th><th>Округление применено</th></tr>\n";
        
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
            
            echo "<tr>";
            echo "<td><strong>$role</strong></td>";
            echo "<td>{$timeData['hours']}</td>";
            echo "<td>{$timeData['minutes']}</td>";
            echo "<td>{$timeData['decimal_hours']}</td>";
            echo "<td>{$rate}</td>";
            echo "<td><strong>" . number_format($cost, 2) . "</strong></td>";
            echo "<td>{$roundingStatus}</td>";
            echo "</tr>\n";
            
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
        
        echo "</table>\n";
        
        echo "<p><strong>Общая рассчитанная стоимость:</strong> " . number_format($totalCalculatedCost, 2) . " руб.</p>\n";
        echo "<p><strong>Стоимость из системы:</strong> " . number_format($projectTimeData['total_cost'], 2) . " руб.</p>\n";
        
        // Проверяем соответствие расчетов
        $costDifference = abs($totalCalculatedCost - $projectTimeData['total_cost']);
        $costsMatch = $costDifference < 0.01; // Допускаем разницу в 1 копейку
        
        echo "<p><strong>Расчеты соответствуют:</strong> " . ($costsMatch ? '✅ Да' : '❌ Нет') . "</p>\n";
        if (!$costsMatch) {
            echo "<p><strong>Разница:</strong> " . number_format($costDifference, 2) . " руб.</p>\n";
        }
        
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
        echo "<p>❌ Нет данных о времени по ролям.</p>\n";
        $logger->log([
            'action' => 'no_roles_data',
            'project_id' => $projectId
        ]);
    }
    
    // Тестируем создание сделки (только если есть время)
    if ($projectTimeData['total_hours'] > 0) {
        echo "<h4>Создание тестовой сделки...</h4>\n";
        echo "<p><strong>ВНИМАНИЕ:</strong> Это создаст реальную сделку в Bitrix24!</p>\n";
        
        // Раскомментируйте следующую строку для реального создания сделки
        // $result = $dealCreator->createMonthlyWorkDeal($testDeal, $projectTimeData);
        // $logger->log($result);
        
        echo "<p>Для реального создания сделки раскомментируйте соответствующие строки в коде.</p>\n";
        echo "<p>Результат будет записан в лог файл.</p>\n";
    } else {
        echo "<p>Нет затраченного времени - сделка не будет создана.</p>\n";
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
    
    echo "<h4>✅ Тест завершен.</h4>\n";
    echo "<p><strong>Все данные записаны в лог файл:</strong> deal_creation_test.log</p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Ошибка: " . $e->getMessage() . "</p>\n";
    if (isset($logger)) {
        $logger->log(['error' => $e->getMessage()]);
    }
}
?>
