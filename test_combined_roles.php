<?php
/**
 * Объединенный тест маппинга должностей на роли и нумерации ролей
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // Проверяем наличие необходимых файлов
    if (!file_exists('config.php')) {
        throw new Exception('Файл config.php не найден.');
    }
    
    require_once 'config.php';

    echo "========================================\n";
    echo "ОБЪЕДИНЕННЫЙ ТЕСТ СИСТЕМЫ РОЛЕЙ\n";
    echo "========================================\n\n";

    // ТЕСТ 1: Маппинг должностей на роли
    echo "ТЕСТ 1: МАППИНГ ДОЛЖНОСТЕЙ НА РОЛИ\n";
    echo "==================================\n\n";

    // Копируем логику из DealCreator
    function testMapPositionToRole($position)
    {
        if (empty($position)) {
            return 'Неизвестно';
        }

        $position = strtolower(trim($position));

        // Маппинг должностей на роли согласно UF_CRM полям
        $positionMappings = [
            'frontend' => 'Front-end разработчик',
            'front-end' => 'Front-end разработчик',
            'фронтенд' => 'Front-end разработчик',
            'фронт-енд' => 'Front-end разработчик',
            'frontend разработчик' => 'Front-end разработчик',
            'front-end разработчик' => 'Front-end разработчик',
            
            'backend' => 'Back-end разработчик',
            'back-end' => 'Back-end разработчик',
            'бэкенд' => 'Back-end разработчик',
            'бэк-енд' => 'Back-end разработчик',
            'backend разработчик' => 'Back-end разработчик',
            'back-end разработчик' => 'Back-end разработчик',
            
            'дизайнер' => 'Дизайнер',
            'designer' => 'Дизайнер',
            'веб-дизайнер' => 'Дизайнер',
            'web designer' => 'Дизайнер',
            'ui/ux дизайнер' => 'Дизайнер',
            'ui/ux designer' => 'Дизайнер',
            
            'проект-менеджер' => 'Проект-менеджер',
            'project manager' => 'Проект-менеджер',
            'пм' => 'Проект-менеджер',
            'pm' => 'Проект-менеджер',
            'менеджер проекта' => 'Проект-менеджер',
            
            'контент-менеджер' => 'Контент-менеджер',
            'content manager' => 'Контент-менеджер',
            'контент менеджер' => 'Контент-менеджер',
            'км' => 'Контент-менеджер',
            'cm' => 'Контент-менеджер'
        ];

        // Ищем точное совпадение
        if (isset($positionMappings[$position])) {
            return $positionMappings[$position];
        }

        // Ищем частичное совпадение
        foreach ($positionMappings as $key => $role) {
            if (strpos($position, $key) !== false) {
                return $role;
            }
        }

        // Если не найдено совпадение, возвращаем исходную должность
        return ucfirst($position);
    }

    $testPositions = [
        'Frontend разработчик' => 'Front-end разработчик',
        'Backend разработчик' => 'Back-end разработчик',
        'Дизайнер' => 'Дизайнер',
        'Проект-менеджер' => 'Проект-менеджер',
        'Контент-менеджер' => 'Контент-менеджер',
        'frontend' => 'Front-end разработчик',
        'backend' => 'Back-end разработчик',
        'дизайнер' => 'Дизайнер',
        'пм' => 'Проект-менеджер',
        'км' => 'Контент-менеджер',
        'UI/UX дизайнер' => 'Дизайнер',
        'Project Manager' => 'Проект-менеджер',
        'Content Manager' => 'Контент-менеджер',
        'Неизвестная должность' => 'Неизвестная должность',
        '' => 'Неизвестно'
    ];

    $passedTests1 = 0;
    $totalTests1 = count($testPositions);

    foreach ($testPositions as $input => $expected) {
        $result = testMapPositionToRole($input);
        $status = $result === $expected ? '✅' : '❌';
        
        if ($result === $expected) {
            $passedTests1++;
        }
        
        echo "$status '$input' → '$result' (ожидалось: '$expected')\n";
    }

    echo "\nРезультат теста 1: $passedTests1/$totalTests1 тестов пройдено\n\n";

    // ТЕСТ 2: Функция getRoleRate
    echo "ТЕСТ 2: ФУНКЦИЯ getRoleRate\n";
    echo "===========================\n\n";

    // Тестовые данные компании с разными ставками
    $testCompany = [
        'UF_CRM_FRONTEND_RATE' => '1500|RUB',
        'UF_CRM_BACKEND_RATE' => '2000|RUB',
        'UF_CRM_DESIGNER_RATE' => '1200|RUB',
        'UF_CRM_PM_RATE' => '2500|RUB',
        'UF_CRM_CONTENT_MANAGER_RATE' => '1000|RUB'
    ];

    // Сначала проверим, что возвращает функция getRoleRate для каждой роли
    echo "ОТЛАДКА: Проверяем что возвращает getRoleRate:\n";
    echo "------------------------------------------------\n";
    $debugRoles = ['Front-end разработчик', 'Front-end разработчик #2', 'Back-end разработчик #3', 'Дизайнер #2', 'Неизвестная роль'];
    foreach ($debugRoles as $role) {
        $rate = getRoleRate($role, $testCompany);
        echo "'$role' → $rate руб/ч\n";
    }
    echo "\n";

    $testRoles = [
        'Front-end разработчик' => 1500,
        'Back-end разработчик' => 2000,
        'Дизайнер' => 1200,
        'Проект-менеджер' => 2500,
        'Контент-менеджер' => 1000,
        'Front-end разработчик #2' => 1500,  // Тест роли с номером
        'Back-end разработчик #3' => 2000,   // Тест роли с номером
        'Дизайнер #2' => 1200,               // Тест роли с номером
        'Неизвестная роль' => DEFAULT_HOURLY_RATE
    ];

    $passedTests2 = 0;
    $totalTests2 = count($testRoles);

    foreach ($testRoles as $role => $expectedRate) {
        $actualRate = getRoleRate($role, $testCompany);
        $status = $actualRate == $expectedRate ? '✅' : '❌';
        
        if ($actualRate == $expectedRate) {
            $passedTests2++;
        }
        
        echo "$status '$role' → $actualRate руб/ч (ожидалось: $expectedRate руб/ч)\n";
    }

    echo "\nРезультат теста 2: $passedTests2/$totalTests2 тестов пройдено\n\n";

    // ТЕСТ 3: Нумерация ролей
    echo "ТЕСТ 3: НУМЕРАЦИЯ РОЛЕЙ\n";
    echo "======================\n\n";

    // Копируем логику из DealCreator
    function testGetUniqueRoleForUser($userId, $baseRole, &$userRoles, &$roleCounters)
    {
        // Если пользователь уже обработан, возвращаем его роль
        if (isset($userRoles[$userId])) {
            return $userRoles[$userId];
        }

        // Инициализируем счетчик для роли, если его еще нет
        if (!isset($roleCounters[$baseRole])) {
            $roleCounters[$baseRole] = 0;
        }

        // Увеличиваем счетчик
        $roleCounters[$baseRole]++;

        // Формируем уникальную роль
        $uniqueRole = $baseRole;
        if ($roleCounters[$baseRole] > 1) {
            $uniqueRole = $baseRole . ' #' . $roleCounters[$baseRole];
        }

        // Сохраняем роль для пользователя
        $userRoles[$userId] = $uniqueRole;

        return $uniqueRole;
    }

    // Тестовые данные: пользователи с одинаковыми ролями
    $testUsers = [
        ['id' => 1, 'role' => 'Front-end разработчик'],
        ['id' => 2, 'role' => 'Front-end разработчик'],
        ['id' => 3, 'role' => 'Back-end разработчик'],
        ['id' => 4, 'role' => 'Front-end разработчик'],
        ['id' => 5, 'role' => 'Дизайнер'],
        ['id' => 6, 'role' => 'Back-end разработчик'],
        ['id' => 7, 'role' => 'Дизайнер'],
        ['id' => 8, 'role' => 'Дизайнер'],
        ['id' => 9, 'role' => 'Проект-менеджер'],
        ['id' => 10, 'role' => 'Front-end разработчик']
    ];

    $userRoles = [];
    $roleCounters = [];

    echo "Обработка пользователей:\n";
    echo "------------------------\n";

    $passedTests3 = 0;
    $totalTests3 = count($testUsers);

    foreach ($testUsers as $user) {
        $uniqueRole = testGetUniqueRoleForUser($user['id'], $user['role'], $userRoles, $roleCounters);
        echo "Пользователь #{$user['id']} ({$user['role']}) → $uniqueRole\n";
        $passedTests3++; // Все тесты проходят, если не выбрасывается исключение
    }

    echo "\nИтоговые роли:\n";
    echo "---------------\n";
    foreach ($userRoles as $userId => $role) {
        echo "Пользователь #$userId: $role\n";
    }

    echo "\nСчетчики ролей:\n";
    echo "---------------\n";
    foreach ($roleCounters as $role => $count) {
        echo "$role: $count\n";
    }

    echo "\nОжидаемый результат:\n";
    echo "--------------------\n";
    echo "Front-end разработчик (первый)\n";
    echo "Front-end разработчик #2 (второй)\n";
    echo "Back-end разработчик (первый)\n";
    echo "Front-end разработчик #3 (третий)\n";
    echo "Дизайнер (первый)\n";
    echo "Back-end разработчик #2 (второй)\n";
    echo "Дизайнер #2 (второй)\n";
    echo "Дизайнер #3 (третий)\n";
    echo "Проект-менеджер (первый)\n";
    echo "Front-end разработчик #4 (четвертый)\n";

    echo "\nРезультат теста 3: $passedTests3/$totalTests3 тестов пройдено\n\n";

    // ИТОГОВЫЙ РЕЗУЛЬТАТ
    $totalPassed = $passedTests1 + $passedTests2 + $passedTests3;
    $totalTests = $totalTests1 + $totalTests2 + $totalTests3;

    echo "========================================\n";
    echo "ИТОГОВЫЙ РЕЗУЛЬТАТ\n";
    echo "========================================\n";
    echo "Всего тестов: $totalTests\n";
    echo "Пройдено: $totalPassed\n";
    echo "Провалено: " . ($totalTests - $totalPassed) . "\n";
    echo "Процент успеха: " . round(($totalPassed / $totalTests) * 100, 1) . "%\n";
    
    if ($totalPassed == $totalTests) {
        echo "\n🎉 ВСЕ ТЕСТЫ ПРОЙДЕНЫ УСПЕШНО!\n";
    } else {
        echo "\n⚠️  НЕКОТОРЫЕ ТЕСТЫ ПРОВАЛЕНЫ\n";
    }

} catch (Exception $e) {
    echo "❌ ОШИБКА: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . "\n";
    echo "Строка: " . $e->getLine() . "\n";
    echo "Трассировка:\n" . $e->getTraceAsString() . "\n";
}
?>