#!/bin/bash
# -*- coding: utf-8 -*-

# Скрипт установки внешних библиотек для генерации документов
# Запускать из корневой директории проекта

echo "Установка внешних библиотек для генерации документов..."

# Создаем директории
mkdir -p external_libraries
mkdir -p generated_documents

# Устанавливаем Composer если его нет
if ! command -v composer &> /dev/null; then
    echo "Composer не найден. Устанавливаем..."
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
fi

# Переходим в директорию библиотек
cd external_libraries

# Создаем composer.json для PhpSpreadsheet
cat > composer.json << 'EOF'
{
    "require": {
        "phpoffice/phpspreadsheet": "^1.29",
        "tecnickcom/tcpdf": "^6.6"
    },
    "config": {
        "optimize-autoloader": true,
        "classmap-authoritative": true
    }
}
EOF

# Устанавливаем библиотеки через Composer
echo "Устанавливаем PhpSpreadsheet и TCPDF..."
composer install --no-dev --optimize-autoloader

# Проверяем установку
if [ -f "vendor/autoload.php" ]; then
    echo "✅ PhpSpreadsheet установлен успешно"
else
    echo "❌ Ошибка установки PhpSpreadsheet"
    exit 1
fi

if [ -f "vendor/tecnickcom/tcpdf/tcpdf.php" ]; then
    echo "✅ TCPDF установлен успешно"
else
    echo "❌ Ошибка установки TCPDF"
    exit 1
fi

# Создаем символическую ссылку для TCPDF
ln -sf vendor/tecnickcom/tcpdf tcpdf

# Устанавливаем права доступа
chmod 755 generated_documents
chmod 644 generated_documents/.htaccess 2>/dev/null || true

# Создаем .htaccess для защиты директории
cat > ../generated_documents/.htaccess << 'EOF'
# Защита директории с документами
Options -Indexes
<Files "*.php">
    Deny from all
</Files>
<Files "*.phtml">
    Deny from all
</Files>
<Files "*.inc">
    Deny from all
</Files>

# Разрешаем только определенные типы файлов
<FilesMatch "\.(xlsx|xls|pdf|csv)$">
    Allow from all
</FilesMatch>

# Настройки кэширования
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType application/vnd.openxmlformats-officedocument.spreadsheetml.sheet "access plus 1 hour"
    ExpiresByType application/pdf "access plus 1 hour"
    ExpiresByType text/csv "access plus 1 hour"
</IfModule>
EOF

echo ""
echo "🎉 Установка завершена успешно!"
echo ""
echo "Установленные библиотеки:"
echo "  - PhpSpreadsheet (для Excel файлов)"
echo "  - TCPDF (для PDF документов)"
echo ""
echo "Директории:"
echo "  - external_libraries/ - библиотеки"
echo "  - generated_documents/ - сгенерированные документы"
echo ""
echo "Следующие шаги:"
echo "  1. Проверьте права доступа к директории generated_documents/"
echo "  2. Настройте веб-сервер для доступа к документам"
echo "  3. Запустите тест генерации документов"
echo ""
echo "Для тестирования запустите:"
echo "  php test_external_generator.php"
