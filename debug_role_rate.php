<?php
/**
 * Отладочный тест функции getRoleRate
 */

require_once 'config.php';

echo "ОТЛАДОЧНЫЙ ТЕСТ getRoleRate\n";
echo "===========================\n\n";

// Тестовые данные компании
$testCompany = [
    'UF_CRM_FRONTEND_RATE' => '1500|RUB',
    'UF_CRM_BACKEND_RATE' => '2000|RUB',
    'UF_CRM_DESIGNER_RATE' => '1200|RUB',
    'UF_CRM_PM_RATE' => '2500|RUB',
    'UF_CRM_CONTENT_MANAGER_RATE' => '1000|RUB'
];

echo "Тестовые данные компании:\n";
print_r($testCompany);
echo "\n";

// Тестируем функцию getRoleRateField напрямую
echo "ТЕСТ getRoleRateField:\n";
echo "----------------------\n";

$testRoles = [
    'Front-end разработчик',
    'Front-end разработчик #2',
    'Back-end разработчик #3',
    'Дизайнер #2',
    'Неизвестная роль'
];

foreach ($testRoles as $role) {
    $field = getRoleRateField($role);
    echo "'$role' → '$field'\n";
}

echo "\nТЕСТ getRoleRate:\n";
echo "-----------------\n";

foreach ($testRoles as $role) {
    $rate = getRoleRate($role, $testCompany);
    echo "'$role' → $rate руб/ч\n";
}

echo "\nПроверка DEFAULT_HOURLY_RATE: " . DEFAULT_HOURLY_RATE . "\n";

// Дополнительная отладка
echo "\nДОПОЛНИТЕЛЬНАЯ ОТЛАДКА:\n";
echo "----------------------\n";

$role = 'Front-end разработчик #2';
echo "Тестируем роль: '$role'\n";

$field = getRoleRateField($role);
echo "getRoleRateField('$role') = '$field'\n";

if ($field && isset($testCompany[$field])) {
    $rate = $testCompany[$field];
    echo "testCompany['$field'] = '$rate'\n";
    
    $cleanRate = is_string($rate) ? str_replace('|RUB', '', $rate) : $rate;
    echo "cleanRate = '$cleanRate'\n";
    
    $finalRate = is_numeric($cleanRate) && $cleanRate > 0 ? (float)$cleanRate : DEFAULT_HOURLY_RATE;
    echo "finalRate = $finalRate\n";
} else {
    echo "Поле не найдено или пустое\n";
}
?>