<?php

/**
 * Пример локальной конфигурации вебхука Bitrix24.
 *
 * Инструкция:
 *   1. Скопируйте этот файл в set.local.php (он в .gitignore и не попадёт в git).
 *   2. Впишите URL своего вебхука в define ниже.
 *   3. (Рекомендуется) Держите права вебхука минимально необходимыми.
 *
 * Как получить вебхук: Bitrix24 → Приложения → Другие → Вебхуки → Добавить.
 * Адрес страницы: https://<ваш-домен>.bitrix24.ru/devops/ (или /marketplace/)
 */

define('BITRIX24_WEBHOOK_URL', 'https://your-domain.bitrix24.ru/rest/USER_ID/WEBHOOK_CODE/');