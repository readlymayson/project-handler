<?php

/**
 * Конфигурация вебхука Bitrix24.
 *
 * ВАЖНО: реальный URL вебхука (с токеном) НЕ хранится в репозитории.
 * Значение берётся по приоритету:
 *   1. Переменная окружения BITRIX24_WEBHOOK_URL
 *   2. Локальный файл set.local.php (в .gitignore, в git не попадает)
 *
 * Настройка локально:
 *   cp set.local.example.php set.local.php
 *   и вписать свой вебхук в set.local.php,
 *   либо задать переменную окружения BITRIX24_WEBHOOK_URL.
 */

if (!defined('BITRIX24_WEBHOOK_URL')) {
    $__bitrix24WebhookUrl = getenv('BITRIX24_WEBHOOK_URL') ?: '';

    if ($__bitrix24WebhookUrl === '') {
        $__bitrix24LocalConfig = __DIR__ . '/set.local.php';
        if (file_exists($__bitrix24LocalConfig)) {
            require $__bitrix24LocalConfig; // может определить BITRIX24_WEBHOOK_URL
        }
    }

    if (!defined('BITRIX24_WEBHOOK_URL')) {
        if ($__bitrix24WebhookUrl !== '') {
            define('BITRIX24_WEBHOOK_URL', $__bitrix24WebhookUrl);
        } else {
            throw new RuntimeException(
                'BITRIX24_WEBHOOK_URL не задан. ' .
                'Задайте переменную окружения BITRIX24_WEBHOOK_URL или ' .
                'создайте файл set.local.php (пример: set.local.example.php).'
            );
        }
    }

    unset($__bitrix24WebhookUrl, $__bitrix24LocalConfig);
}
