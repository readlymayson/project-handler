<?php

/**
 * Конфигурация для создания сделок "Работы за предыдущий месяц"
 */

// Базовая ставка по умолчанию (руб/час) - используется если UF_CRM_PRICE_DEFAULT не задан
define('DEFAULT_HOURLY_RATE', 1000);

// ID воронки "Работы за предыдущий месяц"
define('MONTHLY_WORK_FUNNEL_ID', '2');

// ID стадий в воронке "Работы за предыдущий месяц"
define('FUNNEL_STAGES', [
    'NEW' => 'C2:NEW', // Проекты
    'PREPARATION' => 'C2:PREPARATION', // Проверка
    'PREPAYMENT_INVOICE' => 'C2:PREPAYMENT_INVOICE', // Отправка клиенту отчета/счета
    'EXECUTING' => 'C2:EXECUTING', // Контроль оплаты
    'FINAL_INVOICE' => 'C2:FINAL_INVOICE', // Оплата произведена
    'WON' => 'C2:WON', // Сделка успешна
    'LOSE' => 'C2:LOSE', // Сделка провалена
    'APOLOGY' => 'C2:APOLOGY' // Анализ причины провала
]);

// Настройки округления времени
define('MIN_HOUR_ROUNDING', 1); // Минимальное округление до часа
define('ROUNDING_THRESHOLD', 0.1); // Порог для округления (10 минут)

// Настройки дат
define('CLOSE_DATE_OFFSET_DAYS', 5); // Дата завершения - до 6 числа (5 дней от начала месяца)
define('WORK_DAYS_ONLY', true); // Учитывать только рабочие дни для даты начала

// Настройки логирования
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('LOG_DEAL_CREATION', true); // Логировать создание сделок
define('LOG_TIME_CALCULATION', true); // Логировать расчет времени

// Настройки уведомлений
define('NOTIFY_ON_DEAL_CREATION', true); // Уведомлять о создании сделок
define('NOTIFY_USERS', [1]); // ID пользователей для уведомлений

// Настройки проверки дубликатов
define('CHECK_DUPLICATES', true); // Проверять дубликаты сделок
define('DUPLICATE_CHECK_DAYS', 30); // Период проверки дубликатов в днях

// Настройки оптимизации API вызовов
define('API_DELAY_BETWEEN_CALLS', 0.5); // Задержка между API вызовами в секундах
define('API_DELAY_BETWEEN_BATCHES', 1.0); // Задержка между батчами в секундах
define('API_TIMEOUT', 30); // Таймаут для API вызовов в секундах
define('API_MAX_RETRIES', 3); // Максимальное количество повторов при ошибке
define('API_RETRY_DELAY', 2.0); // Задержка перед повтором в секундах

/**
 * Получить тариф для роли из поля UF_CRM_PRICE_DEFAULT сделки
 */
function getRoleRate($role, $defaultPrice = null): float
{
    if ($defaultPrice !== null && is_numeric($defaultPrice) && $defaultPrice > 0) {
        return (float)$defaultPrice;
    }
    
    return DEFAULT_HOURLY_RATE;
}

/**
 * Получить настройки для конкретного проекта (можно расширить в будущем)
 */
function getProjectSettings($projectId): array
{
    // Здесь можно добавить индивидуальные настройки для проектов
    return [
        'custom_rates' => false,
        'special_rounding' => false,
        'exclude_roles' => [],
        'additional_roles' => []
    ];
}

/**
 * Функция задержки между API вызовами
 */
function apiDelay($seconds = null): void
{
    if ($seconds === null) {
        $seconds = API_DELAY_BETWEEN_CALLS;
    }
    
    if ($seconds > 0) {
        usleep($seconds * 1000000); // Конвертируем секунды в микросекунды
    }
}

/**
 * Функция задержки между батчами
 */
function batchDelay(): void
{
    apiDelay(API_DELAY_BETWEEN_BATCHES);
}
?>
