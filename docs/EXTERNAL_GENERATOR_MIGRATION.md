# Миграция с Bitrix24 Document Generator на внешние библиотеки

## Обзор изменений

Система генерации документов была переведена с встроенного Bitrix24 Document Generator на внешние библиотеки для решения проблем с внутренней генерацией Excel отчетов.

## Что изменилось

### 1. Новые файлы

- `ExternalDocumentGenerator.php` - новый класс для генерации документов через внешние библиотеки
- `install_external_libraries.sh` - скрипт установки необходимых библиотек
- `test_external_generator.php` - тестовый скрипт для проверки работы

### 2. Обновленные файлы

- `config.php` - добавлены настройки для внешних библиотек
- `DealCreator.php` - интегрирован новый генератор
- `index.php` - обновлена инициализация генераторов

### 3. Новые константы в config.php

```php
// Настройки для генерации документов через внешние библиотеки
define('ENABLE_EXTERNAL_DOCUMENT_GENERATOR', true);
define('AUTO_GENERATE_EXTERNAL_DOCUMENTS', true);
define('GENERATE_ON_DEAL_CREATION', true);
define('GENERATE_ON_DEAL_UPDATE', false);

// Путь к внешним библиотекам
define('EXTERNAL_LIBRARIES_PATH', __DIR__ . '/external_libraries');

// Директория для сохранения сгенерированных документов
define('EXTERNAL_DOCUMENTS_OUTPUT_DIR', __DIR__ . '/generated_documents');

// URL для доступа к сгенерированным документам
define('EXTERNAL_DOCUMENTS_URL', '/generated_documents');

// Настройки генерации различных форматов
define('EXTERNAL_EXCEL_GENERATION', true);
define('EXTERNAL_PDF_GENERATION', true);
define('EXTERNAL_CSV_GENERATION', true);
```

## Установка и настройка

### Шаг 1: Установка библиотек

```bash
# Запустите скрипт установки
chmod +x install_external_libraries.sh
./install_external_libraries.sh
```

Этот скрипт установит:
- PhpSpreadsheet (для Excel файлов)
- TCPDF (для PDF документов)

### Шаг 2: Настройка прав доступа

```bash
# Убедитесь, что директория доступна для записи
chmod 755 generated_documents
chown www-data:www-data generated_documents
```

### Шаг 3: Настройка веб-сервера

Добавьте в конфигурацию веб-сервера алиас для доступа к документам:

```apache
# Apache
Alias /generated_documents /var/www/efrolov-dev/html/application/akvilon/project-handler/generated_documents
<Directory "/var/www/efrolov-dev/html/application/akvilon/project-handler/generated_documents">
    Options -Indexes
    AllowOverride All
    Require all granted
</Directory>
```

### Шаг 4: Тестирование

```bash
# Запустите тестовый скрипт
php test_external_generator.php
```

## Поддерживаемые форматы

### Excel (.xlsx)
- **Библиотека**: PhpSpreadsheet
- **Функции**: Форматирование, формулы, стили
- **Использование**: Отчеты по задачам

### PDF
- **Библиотека**: TCPDF
- **Функции**: Счета, акты выполненных работ
- **Особенности**: Поддержка кириллицы, таблицы

### CSV
- **Резервный формат**: Простые отчеты
- **Особенности**: BOM для корректного отображения в Excel

## Приоритет генерации

Система работает по принципу приоритета:

1. **Внешние библиотеки** (приоритет) - если `ENABLE_EXTERNAL_DOCUMENT_GENERATOR = true`
2. **Bitrix24 Document Generator** (резерв) - если внешние библиотеки недоступны

## Преимущества нового подхода

### ✅ Решенные проблемы
- Генерация Excel файлов работает стабильно
- Полный контроль над форматированием
- Не зависит от ограничений Bitrix24
- Поддержка всех необходимых форматов

### ✅ Дополнительные возможности
- Настраиваемое форматирование документов
- Поддержка различных форматов (Excel, PDF, CSV)
- Локальное сохранение файлов
- Возможность кастомизации шаблонов

## Обратная совместимость

Старый `DocumentGenerator` остается доступным как резервный вариант. Система автоматически переключается на него, если внешние библиотеки недоступны.

## Мониторинг и логирование

Все операции генерации документов логируются с типом `external_documents_generation`:

```php
[
    'type' => 'external_documents_generation',
    'status' => 'success',
    'deal_id' => 12345,
    'documents' => ['excel_report', 'pdf_invoice', 'pdf_act'],
    'message' => 'Документы успешно сгенерированы через внешние библиотеки'
]
```

## Устранение неполадок

### Проблема: Библиотеки не найдены
```bash
# Проверьте установку
ls -la external_libraries/vendor/autoload.php
ls -la external_libraries/tcpdf/tcpdf.php
```

### Проблема: Нет прав на запись
```bash
# Установите правильные права
chmod 755 generated_documents
chown www-data:www-data generated_documents
```

### Проблема: Документы не генерируются
1. Проверьте логи на ошибки
2. Запустите тестовый скрипт
3. Убедитесь, что все константы настроены правильно

## Миграция данных

Если у вас есть существующие шаблоны в Bitrix24 Document Generator, они больше не используются. Новые документы генерируются по встроенным шаблонам в коде.

## Производительность

Внешние библиотеки могут работать медленнее Bitrix24 API, но обеспечивают:
- Стабильность генерации
- Полный контроль над процессом
- Возможность оптимизации

## Безопасность

- Директория `generated_documents` защищена `.htaccess`
- Разрешены только определенные типы файлов
- PHP файлы заблокированы от выполнения
