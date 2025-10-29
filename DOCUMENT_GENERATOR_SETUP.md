# Настройка генератора документов

## Обзор

Система поддерживает два варианта генерации документов:

1. **ExternalDocumentGenerator** (рекомендуется) - через внешние библиотеки (PhpSpreadsheet, TCPDF)
2. **DocumentGenerator** (устаревший) - через Bitrix24 Document Generator API

Система автоматически использует ExternalDocumentGenerator, если он включен. DocumentGenerator используется как резервный вариант.

## Настройка ExternalDocumentGenerator (рекомендуется)

### 1. Включение генератора

В файле `config.php` установите:

```php
// Включить внешнюю генерацию документов (рекомендуется)
define('ENABLE_EXTERNAL_DOCUMENT_GENERATOR', true);

// Автоматически генерировать документы при создании сделки
define('AUTO_GENERATE_EXTERNAL_DOCUMENTS', true);
```

### 2. Установка библиотек

```bash
chmod +x install_external_libraries.sh
./install_external_libraries.sh
```

### 3. Настройка прав доступа

```bash
chmod 755 generated_documents
chown www-data:www-data generated_documents
```

### 4. Настройка форматов

```php
// В config.php
define('EXTERNAL_EXCEL_GENERATION', true); // Генерировать Excel отчеты
define('EXTERNAL_PDF_GENERATION', false);  // Генерировать PDF документы (требует TCPDF)
define('EXTERNAL_CSV_GENERATION', true);   // Генерировать CSV отчеты (резервный вариант)
```

Подробнее: [docs/EXTERNAL_GENERATOR_MIGRATION.md](docs/EXTERNAL_GENERATOR_MIGRATION.md)

## Настройка DocumentGenerator (устаревший)

### 1. Включение/выключение генератора

В файле `config.php` установите:

```php
// Включить/выключить генерацию документов через Bitrix24 (устаревший)
define('ENABLE_DOCUMENT_GENERATOR', false); // Рекомендуется отключить

// Автоматически генерировать документы при создании сделки
define('AUTO_GENERATE_DOCUMENTS', true);

// Генерировать документы сразу при создании сделки
define('GENERATE_ON_DEAL_CREATION', true);

// Генерировать документы при обновлении сделки
define('GENERATE_ON_DEAL_UPDATE', false);
```

### 2. Создание шаблонов в Bitrix24

1. Перейдите в **CRM → Настройки → Шаблоны документов**
2. Создайте шаблоны для:
   - **Счет/Платежное поручение** (PDF документ для выставления счета)
   - **Акт** (PDF документ выполненных работ)

**Примечание:** Excel отчеты теперь генерируются через ExternalDocumentGenerator, шаблон отчета не нужен.

3. Запомните ID созданных шаблонов

### 3. Настройка ID шаблонов

В файле `config.php` укажите ID шаблонов:

```php
// ID шаблонов документов (замените на реальные ID)
define('INVOICE_TEMPLATE_ID', 124); // ID шаблона счета/платежного поручения
define('ACT_TEMPLATE_ID', 125);     // ID шаблона акта
```

### 4. Настройка производительности

```php
// Настройки генерации документов
define('DOCUMENT_GENERATION_TIMEOUT', 60); // Таймаут генерации документа в секундах
define('DOCUMENT_RETRY_ATTEMPTS', 3); // Количество попыток генерации документа
define('DOCUMENT_RETRY_DELAY', 5); // Задержка между попытками в секундах
```

## Переменные для шаблонов Bitrix24

В шаблонах документов Bitrix24 доступны следующие переменные:

### Основные данные
- `{=MONTH_NAME}` - Название месяца (в родительном падеже)
- `{=YEAR}` - Год
- `{=TOTAL_HOURS}` - Общее количество часов
- `{=TOTAL_COST}` - Общая стоимость
- `{=COMPANY_NAME}` - Название компании-исполнителя
- `{COMPANY_TITLE}` - Название компании-заказчика (стандартное поле)

### Данные для счета/платежного поручения
- `{=INVOICE_NUMBER}` - Номер счета
- `{=ROLES_DATA}` - Массив товаров/услуг
  - `{=ROLES_DATA.NUMBER}` - Порядковый номер
  - `{=ROLES_DATA.SERVICE_NAME_INVOICE}` - Наименование услуги для счета
  - `{=ROLES_DATA.HOURS}` - Количество часов
  - `{=ROLES_DATA.RATE}` - Ставка в час
  - `{=ROLES_DATA.AMOUNT}` - Сумма
  - `{=VAT_RATE}` - Ставка НДС

### Данные для акта
- `{=ACT_NUMBER}` - Номер акта
- `{=ROLES_DATA.SERVICE_NAME_ACT}` - Наименование услуги для акта (без слова "Оплата")
- Остальные поля аналогично счету

Подробнее: [docs/DocumentGeneration_README.md](docs/DocumentGeneration_README.md)

## Статусы генерации

- `success` - Документы успешно сгенерированы
- `disabled` - Генератор отключен в настройках
- `error` - Ошибка при генерации
- `partial` - Частично сгенерированы (некоторые шаблоны не настроены)

## Логирование

Все операции генерации документов логируются:
- ExternalDocumentGenerator: `logs/akvilon_check.log` (тип `external_documents_generation`)
- DocumentGenerator: `logs/akvilon_check.log` (тип `document_generation`)

## Проверка настроек

### ExternalDocumentGenerator

```bash
php test_external_generator.php
```

### DocumentGenerator

```bash
php test_document_generator.php
```

## Устранение неполадок

### Генератор отключен
```
Генерация документов отключена в настройках
```
**Решение:** Установите `ENABLE_EXTERNAL_DOCUMENT_GENERATOR = true` или `ENABLE_DOCUMENT_GENERATOR = true`

### Шаблоны не настроены (DocumentGenerator)
```
Шаблоны документов не настроены (ID = 0)
```
**Решение:** Создайте шаблоны в Bitrix24 и укажите их ID в config.php

### Библиотеки не найдены (ExternalDocumentGenerator)
```
Библиотеки не найдены
```
**Решение:** Запустите `install_external_libraries.sh`

### Ошибка генерации
```
Ошибка при генерации документа: [детали ошибки]
```
**Решение:** Проверьте логи в `logs/akvilon_check.log`

## Приоритет генерации

Система работает по принципу приоритета:

1. **ExternalDocumentGenerator** (приоритет) - если `ENABLE_EXTERNAL_DOCUMENT_GENERATOR = true`
2. **DocumentGenerator** (резерв) - если внешние библиотеки недоступны или отключены

## Примеры использования

### Включение ExternalDocumentGenerator (рекомендуется)

```php
// В config.php
define('ENABLE_EXTERNAL_DOCUMENT_GENERATOR', true);
define('ENABLE_DOCUMENT_GENERATOR', false);
```

### Отключение генерации документов

```php
// В config.php
define('ENABLE_EXTERNAL_DOCUMENT_GENERATOR', false);
define('ENABLE_DOCUMENT_GENERATOR', false);
```

## Дополнительная документация

- [Полная документация по генерации документов](docs/DocumentGeneration_README.md)
- [Быстрый старт](docs/QuickStart.md)
- [Миграция на внешние библиотеки](docs/EXTERNAL_GENERATOR_MIGRATION.md)
- [Основная документация проекта](docs/README.md)
