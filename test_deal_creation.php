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
    
    // Тестируем функцию извлечения ID проекта
    echo "<h3>Тестирование функции извлечения ID проекта</h3>\n";
    $testLinks = [
        '123' => 123,
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
    $defaultPrice = $testCompany['UF_CRM_PRICE_DEFAULT'] ?? null;
    echo "<p>Дефолтная цена из компании: " . ($defaultPrice ?? 'не задана') . " руб/ч</p>\n";
    
    $projectTimeData = $dealCreator->getProjectTimeData($projectId, $defaultPrice);
    
    echo "<p>Общее время: {$projectTimeData['total_hours']} часов</p>\n";
    echo "<p>Общая стоимость: {$projectTimeData['total_cost']} руб.</p>\n";
    
    if (!empty($projectTimeData['roles_time'])) {
        echo "<h4>Разбивка по ролям:</h4>\n";
        echo "<ul>\n";
        foreach ($projectTimeData['roles_time'] as $role => $timeData) {
            $rate = getRoleRate($role, $defaultPrice);
            $cost = $timeData['decimal_hours'] * $rate;
            echo "<li>$role: {$timeData['hours']} ч {$timeData['minutes']} м ({$timeData['decimal_hours']} ч) - {$rate} руб/ч = {$cost} руб.</li>\n";
        }
        echo "</ul>\n";
    } else {
        echo "<p>Нет данных о времени по ролям.</p>\n";
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
    
    echo "<h4>Тест завершен.</h4>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Ошибка: " . $e->getMessage() . "</p>\n";
    if (isset($logger)) {
        $logger->log(['error' => $e->getMessage()]);
    }
}
?>
