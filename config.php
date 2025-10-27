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

// Настройки для генерации документов через Bitrix24 Document Generator
define('ENABLE_DOCUMENT_GENERATOR', true); // Включить/выключить генерацию документов
define('AUTO_GENERATE_DOCUMENTS', true); // Автоматически генерировать документы при создании сделки
define('GENERATE_ON_DEAL_CREATION', true); // Генерировать документы сразу при создании сделки
define('GENERATE_ON_DEAL_UPDATE', false); // Генерировать документы при обновлении сделки

// ВАЖНО: Сначала создайте шаблоны документов в Bitrix24 (CRM -> Настройки -> Шаблоны документов)
// Затем укажите их ID здесь. Если ID не указан (0), документ генерироваться не будет.
// ПРИМЕЧАНИЕ: Excel отчеты теперь генерируются через внешние библиотеки, REPORT_TEMPLATE_ID не нужен
define('INVOICE_TEMPLATE_ID', 2); // ID шаблона счета/платежного поручения
define('ACT_TEMPLATE_ID', 4); // ID шаблона акта

// Настройки генерации документов через Bitrix24
define('DOCUMENT_GENERATION_TIMEOUT', 60); // Таймаут генерации документа в секундах
define('DOCUMENT_RETRY_ATTEMPTS', 3); // Количество попыток генерации документа
define('DOCUMENT_RETRY_DELAY', 5); // Задержка между попытками в секундах

// Настройки для генерации документов через внешние библиотеки
define('ENABLE_EXTERNAL_DOCUMENT_GENERATOR', true); // Включить/выключить внешнюю генерацию документов
define('AUTO_GENERATE_EXTERNAL_DOCUMENTS', true); // Автоматически генерировать документы при создании сделки
define('EXTERNAL_GENERATE_ON_DEAL_CREATION', false); // Генерировать документы сразу при создании сделки
define('EXTERNAL_GENERATE_ON_DEAL_UPDATE', false); // Генерировать документы при обновлении сделки

// Путь к внешним библиотекам
define('EXTERNAL_LIBRARIES_PATH', __DIR__ . '/external_libraries');

// Директория для сохранения сгенерированных документов
define('EXTERNAL_DOCUMENTS_OUTPUT_DIR', __DIR__ . '/generated_documents');

// URL для доступа к сгенерированным документам
define('EXTERNAL_DOCUMENTS_URL', '/generated_documents');

// Настройки генерации различных форматов
define('EXTERNAL_EXCEL_GENERATION', true); // Генерировать Excel отчеты через PhpSpreadsheet
define('EXTERNAL_PDF_GENERATION', false); // Генерировать PDF документы через TCPDF
define('EXTERNAL_CSV_GENERATION', true); // Генерировать CSV отчеты (резервный вариант)

// Настройки внешней генерации документов
define('EXTERNAL_DOCUMENT_GENERATION_TIMEOUT', 120); // Таймаут генерации документа в секундах
define('EXTERNAL_DOCUMENT_RETRY_ATTEMPTS', 3); // Количество попыток генерации документа
define('EXTERNAL_DOCUMENT_RETRY_DELAY', 5); // Задержка между попытками в секундах

// Префиксы для номеров документов
define('INVOICE_NUMBER_PREFIX', 'INV'); // Префикс для номеров счетов
define('ACT_NUMBER_PREFIX', 'ACT'); // Префикс для номеров актов

// Настройки компании-исполнителя (по умолчанию)
define('COMPANY_NAME', 'ООО "Техноресурс"');

// Ставка НДС
define('VAT_RATE', 'Без НДС');

// Настройки договоров и приложений по умолчанию
// Эти значения используются если в компании не заполнены соответствующие UF поля:
// UF_CRM_CONTRACT_NUMBER, UF_CRM_CONTRACT_DATE, UF_CRM_APPENDIX_NUMBER, UF_CRM_APPENDIX_DATE
define('DEFAULT_CONTRACT_NUMBER', '№ ТЕХНОРЕСУРС/23');
define('DEFAULT_CONTRACT_DATE', '12.12.23');
define('DEFAULT_APPENDIX_NUMBER', '№ 3');
define('DEFAULT_APPENDIX_DATE', '16.05.24');

/**
 * Получить тариф для роли из соответствующих UF_CRM полей компании
 */
function getRoleRate($role, $companyData = null): float
{
    // Если переданы данные компании, пытаемся получить ставку для конкретной роли
    if ($companyData && is_array($companyData)) {
        $roleRateField = getRoleRateField($role);
        if ($roleRateField && isset($companyData[$roleRateField])) {
            $rate = $companyData[$roleRateField];
            // Убираем |RUB из цены если есть
            $cleanRate = is_string($rate) ? str_replace('|RUB', '', $rate) : $rate;
            if (is_numeric($cleanRate) && $cleanRate > 0) {
                return (float)$cleanRate;
            }
        }
    }
    
    // Если не найдена ставка для роли, используем дефолтную цену
    return DEFAULT_HOURLY_RATE;
}

/**
 * Получить название UF_CRM поля для ставки роли
 */
function getRoleRateField($role): ?string
{
    // Извлекаем базовую роль из роли с номером (например, "Front-end разработчик #2" -> "Front-end разработчик")
    $baseRole = $role;
    
    // Проверяем, есть ли номер в роли
    if (strpos($role, ' #') !== false) {
        $baseRole = trim(substr($role, 0, strpos($role, ' #')));
    }
    
    $roleFields = [
        'Front-end разработчик' => 'UF_CRM_FRONTEND_RATE',
        'Back-end разработчик' => 'UF_CRM_BACKEND_RATE',
        'Дизайнер' => 'UF_CRM_DESIGNER_RATE',
        'Проект-менеджер' => 'UF_CRM_PM_RATE',
        'Контент-менеджер' => 'UF_CRM_CONTENT_MANAGER_RATE',
        // Новые роли с собственными полями тарифов
        'Директолог' => 'UF_CRM_DIRECTOR_RATE',
        'SEO-специалист' => 'UF_CRM_SEO_RATE',
        'Юрист' => 'UF_CRM_LAWYER_RATE',
        'Битрикс24 разработчик' => 'UF_CRM_BITRIX24_RATE'
    ];
    
    return $roleFields[$baseRole] ?? null;
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
