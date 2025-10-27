# Быстрый старт: Внешний генератор документов

## За 5 минут до работы

### 1. Установите библиотеки
```bash
chmod +x install_external_libraries.sh
./install_external_libraries.sh
```

### 2. Проверьте настройки
```bash
php test_external_generator.php
```

### 3. Настройте права доступа
```bash
chmod 755 generated_documents
chown www-data:www-data generated_documents
```

## Что изменилось

✅ **Вместо Bitrix24 Document Generator** → **Внешние библиотеки**
- PhpSpreadsheet для Excel файлов
- TCPDF для PDF документов
- CSV как резервный формат

✅ **Стабильная генерация Excel** → **Больше никаких проблем с внутренней генерацией**

✅ **Полный контроль** → **Настраиваемое форматирование и шаблоны**

## Настройки в config.php

```php
// Включить внешнюю генерацию (по умолчанию включено)
define('ENABLE_EXTERNAL_DOCUMENT_GENERATOR', true);

// Отключить старую генерацию через Bitrix24
define('ENABLE_DOCUMENT_GENERATOR', false);
```

## Проверка работы

После установки система автоматически:
1. Использует внешние библиотеки для генерации документов
2. Сохраняет файлы в директории `generated_documents/`
3. Логирует все операции с типом `external_documents_generation`

## Если что-то не работает

1. **Проверьте установку библиотек:**
   ```bash
   ls -la external_libraries/vendor/autoload.php
   ```

2. **Проверьте права доступа:**
   ```bash
   ls -la generated_documents/
   ```

3. **Запустите тест:**
   ```bash
   php test_external_generator.php
   ```

4. **Проверьте логи** на наличие ошибок

## Резервный режим

Если внешние библиотеки недоступны, система автоматически переключится на старый Bitrix24 Document Generator.

## Поддержка

Все вопросы по миграции описаны в `docs/EXTERNAL_GENERATOR_MIGRATION.md`
