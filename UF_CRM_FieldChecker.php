<?php

require_once 'config.php';

/**
 * Класс для проверки и создания пользовательских полей UF_CRM в Битрикс24
 */
class UF_CRM_FieldChecker
{
    private Usual $call;
    private Logger $logger;

    /**
     * Конфигурация полей UF_CRM для автоматической проверки и создания
     */
    private array $fieldsConfig = [
        'UF_CRM_PROJECT_LINK' => [
            'name' => 'Ссылка на проект',
            'userTypeId' => 'string',
            'entityId' => 'COMPANY',
            'xmlId' => 'UF_CRM_PROJECT_LINK',
            'sort' => 100,
            'multiple' => 'N',
            'mandatory' => 'N',
            'showFilter' => 'Y',
            'showInList' => 'Y',
            'editInList' => 'Y',
            'isSearchable' => 'Y',
            'editFormLabel' => [
                'en' => '',
                'ru' => 'Ссылка на проект'
            ],
            'help_message' => 'Ссылка на проект в Битрикс24 или ID проекта',
            'settings' => [
                'DEFAULT_VALUE' => '',
                'SIZE' => 50,
                'ROWS' => 1,
                'MIN_LENGTH' => 0,
                'MAX_LENGTH' => 255,
                'REGEXP' => ''
            ]
        ],
        'UF_CRM_EXTRANET_USER' => [
            'name' => 'Пользователь экстранета',
            'userTypeId' => 'employee',
            'entityId' => 'COMPANY',
            'xmlId' => 'UF_CRM_EXTRANET_USER',
            'sort' => 100,
            'multiple' => 'N',
            'mandatory' => 'N',
            'showFilter' => 'Y',
            'showInList' => 'Y',
            'editInList' => 'Y',
            'isSearchable' => 'Y',
            'editFormLabel' => [
                'en' => '',
                'ru' => 'Пользователь экстранета'
            ],
            'help_message' => 'Пользователь для уведомлений',
            'settings' => [
                'DEFAULT_VALUE' => '',
                'DISPLAY' => 'LIST',
                'LIST_HEIGHT' => 1,
                'MAX_SHOW_SIZE' => 1,
                'MIN_SHOW_SIZE' => 1
            ]
        ],
        'UF_CRM_HOURS_LIMIT' => [
            'name' => 'Лимит часов',
            'userTypeId' => 'double',
            'entityId' => 'COMPANY',
            'xmlId' => 'UF_CRM_HOURS_LIMIT',
            'sort' => 100,
            'multiple' => 'N',
            'mandatory' => 'N',
            'showFilter' => 'Y',
            'showInList' => 'Y',
            'editInList' => 'Y',
            'isSearchable' => 'Y',
            'editFormLabel' => [
                'en' => '',
                'ru' => 'Лимит часов'
            ],
            'help_message' => 'Максимальное количество часов для проекта',
            'settings' => [
                'DEFAULT_VALUE' => '',
                'PRECISION' => 2,
                'MIN_VALUE' => 0,
                'MAX_VALUE' => 999999
            ]
        ],
        'UF_CRM_NOTIFY_DATE' => [
            'name' => 'Дата уведомления',
            'userTypeId' => 'date',
            'entityId' => 'COMPANY',
            'xmlId' => 'UF_CRM_NOTIFY_DATE',
            'sort' => 100,
            'multiple' => 'N',
            'mandatory' => 'N',
            'showFilter' => 'Y',
            'showInList' => 'Y',
            'editInList' => 'Y',
            'isSearchable' => 'Y',
            'editFormLabel' => [
                'en' => '',
                'ru' => 'Дата уведомления'
            ],
            'help_message' => 'Дата последнего уведомления о превышении лимита',
            'settings' => [
                'DEFAULT_VALUE' => [
                    'TYPE' => 'NONE'
                ]
            ]
        ],
        'UF_CRM_FRONTEND_RATE' => [
            'name' => 'Front-end разработчик (ставка в час)',
            'userTypeId' => 'money',
            'entityId' => 'COMPANY',
            'xmlId' => 'UF_CRM_FRONTEND_RATE',
            'sort' => 100,
            'multiple' => 'N',
            'mandatory' => 'N',
            'showFilter' => 'Y',
            'showInList' => 'Y',
            'editInList' => 'Y',
            'isSearchable' => 'Y',
            'editFormLabel' => [
                'en' => '',
                'ru' => 'Front-end разработчик (ставка в час)'
            ],
            'help_message' => 'Ставка Front-end разработчика за 1 час работы в рублях',
            'settings' => [
                'DEFAULT_VALUE' => '',
                'CURRENCY' => 'RUB'
            ]
        ],
        'UF_CRM_BACKEND_RATE' => [
            'name' => 'Back-end разработчик (ставка в час)',
            'userTypeId' => 'money',
            'entityId' => 'COMPANY',
            'xmlId' => 'UF_CRM_BACKEND_RATE',
            'sort' => 100,
            'multiple' => 'N',
            'mandatory' => 'N',
            'showFilter' => 'Y',
            'showInList' => 'Y',
            'editInList' => 'Y',
            'isSearchable' => 'Y',
            'editFormLabel' => [
                'en' => '',
                'ru' => 'Back-end разработчик (ставка в час)'
            ],
            'help_message' => 'Ставка Back-end разработчика за 1 час работы в рублях',
            'settings' => [
                'DEFAULT_VALUE' => '',
                'CURRENCY' => 'RUB'
            ]
        ],
        'UF_CRM_DESIGNER_RATE' => [
            'name' => 'Дизайнер (ставка в час)',
            'userTypeId' => 'money',
            'entityId' => 'COMPANY',
            'xmlId' => 'UF_CRM_DESIGNER_RATE',
            'sort' => 100,
            'multiple' => 'N',
            'mandatory' => 'N',
            'showFilter' => 'Y',
            'showInList' => 'Y',
            'editInList' => 'Y',
            'isSearchable' => 'Y',
            'editFormLabel' => [
                'en' => '',
                'ru' => 'Дизайнер (ставка в час)'
            ],
            'help_message' => 'Ставка Дизайнера за 1 час работы в рублях',
            'settings' => [
                'DEFAULT_VALUE' => '',
                'CURRENCY' => 'RUB'
            ]
        ],
        'UF_CRM_PM_RATE' => [
            'name' => 'Проект-менеджер (ставка в час)',
            'userTypeId' => 'money',
            'entityId' => 'COMPANY',
            'xmlId' => 'UF_CRM_PM_RATE',
            'sort' => 100,
            'multiple' => 'N',
            'mandatory' => 'N',
            'showFilter' => 'Y',
            'showInList' => 'Y',
            'editInList' => 'Y',
            'isSearchable' => 'Y',
            'editFormLabel' => [
                'en' => '',
                'ru' => 'Проект-менеджер (ставка в час)'
            ],
            'help_message' => 'Ставка Проект-менеджера за 1 час работы в рублях',
            'settings' => [
                'DEFAULT_VALUE' => '',
                'CURRENCY' => 'RUB'
            ]
        ],
        'UF_CRM_CONTENT_MANAGER_RATE' => [
            'name' => 'Контент-менеджер (ставка в час)',
            'userTypeId' => 'money',
            'entityId' => 'COMPANY',
            'xmlId' => 'UF_CRM_CONTENT_MANAGER_RATE',
            'sort' => 100,
            'multiple' => 'N',
            'mandatory' => 'N',
            'showFilter' => 'Y',
            'showInList' => 'Y',
            'editInList' => 'Y',
            'isSearchable' => 'Y',
            'editFormLabel' => [
                'en' => '',
                'ru' => 'Контент-менеджер (ставка в час)'
            ],
            'help_message' => 'Ставка Контент-менеджера за 1 час работы в рублях',
            'settings' => [
                'DEFAULT_VALUE' => '',
                'CURRENCY' => 'RUB'
            ]
        ],
        'UF_CRM_DEFAULT_RATE' => [
            'name' => 'Базовая ставка по умолчанию',
            'userTypeId' => 'money',
            'entityId' => 'COMPANY',
            'xmlId' => 'UF_CRM_DEFAULT_RATE',
            'sort' => 100,
            'multiple' => 'N',
            'mandatory' => 'N',
            'showFilter' => 'Y',
            'showInList' => 'Y',
            'editInList' => 'Y',
            'isSearchable' => 'Y',
            'editFormLabel' => [
                'en' => '',
                'ru' => 'Базовая ставка по умолчанию'
            ],
            'help_message' => 'Базовая ставка за час работы в рублях (используется если не заданы специфичные тарифы)',
            'settings' => [
                'DEFAULT_VALUE' => '',
                'CURRENCY' => 'RUB'
            ]
        ],
        'UF_CRM_ORIGINAL_COMPANY' => [
            'name' => 'Исходная компания',
            'userTypeId' => 'string',
            'entityId' => 'DEAL',
            'xmlId' => 'UF_CRM_ORIGINAL_COMPANY',
            'sort' => 100,
            'multiple' => 'N',
            'mandatory' => 'N',
            'showFilter' => 'Y',
            'showInList' => 'Y',
            'editInList' => 'Y',
            'isSearchable' => 'Y',
            'editFormLabel' => [
                'en' => '',
                'ru' => 'Исходная компания'
            ],
            'help_message' => 'ID исходной компании для отслеживания связи',
            'settings' => [
                'DEFAULT_VALUE' => '',
                'SIZE' => 50,
                'ROWS' => 1,
                'MIN_LENGTH' => 0,
                'MAX_LENGTH' => 255,
                'REGEXP' => ''
            ]
        ],
        'UF_CRM_ORIGINAL_DEAL' => [
            'name' => 'Исходная сделка',
            'userTypeId' => 'string',
            'entityId' => 'DEAL',
            'xmlId' => 'UF_CRM_ORIGINAL_DEAL',
            'sort' => 100,
            'multiple' => 'N',
            'mandatory' => 'N',
            'showFilter' => 'Y',
            'showInList' => 'Y',
            'editInList' => 'Y',
            'isSearchable' => 'Y',
            'editFormLabel' => [
                'en' => '',
                'ru' => 'Исходная сделка'
            ],
            'help_message' => 'ID исходной сделки для отслеживания связи',
            'settings' => [
                'DEFAULT_VALUE' => '',
                'SIZE' => 50,
                'ROWS' => 1,
                'MIN_LENGTH' => 0,
                'MAX_LENGTH' => 255,
                'REGEXP' => ''
            ]
        ],
        'UF_CRM_CONTRACT_NUMBER' => [
            'name' => 'Номер договора',
            'userTypeId' => 'string',
            'entityId' => 'COMPANY',
            'xmlId' => 'UF_CRM_CONTRACT_NUMBER',
            'sort' => 100,
            'multiple' => 'N',
            'mandatory' => 'N',
            'showFilter' => 'Y',
            'showInList' => 'Y',
            'editInList' => 'Y',
            'isSearchable' => 'Y',
            'editFormLabel' => [
                'en' => '',
                'ru' => 'Номер договора'
            ],
            'help_message' => 'Номер договора для генерации документов',
            'settings' => [
                'DEFAULT_VALUE' => '',
                'SIZE' => 50,
                'ROWS' => 1,
                'MIN_LENGTH' => 0,
                'MAX_LENGTH' => 255,
                'REGEXP' => ''
            ]
        ],
        'UF_CRM_CONTRACT_DATE' => [
            'name' => 'Дата договора',
            'userTypeId' => 'string',
            'entityId' => 'COMPANY',
            'xmlId' => 'UF_CRM_CONTRACT_DATE',
            'sort' => 100,
            'multiple' => 'N',
            'mandatory' => 'N',
            'showFilter' => 'Y',
            'showInList' => 'Y',
            'editInList' => 'Y',
            'isSearchable' => 'Y',
            'editFormLabel' => [
                'en' => '',
                'ru' => 'Дата договора'
            ],
            'help_message' => 'Дата договора для генерации документов',
            'settings' => [
                'DEFAULT_VALUE' => '',
                'SIZE' => 50,
                'ROWS' => 1,
                'MIN_LENGTH' => 0,
                'MAX_LENGTH' => 255,
                'REGEXP' => ''
            ]
        ],
        'UF_CRM_APPENDIX_NUMBER' => [
            'name' => 'Номер приложения',
            'userTypeId' => 'string',
            'entityId' => 'COMPANY',
            'xmlId' => 'UF_CRM_APPENDIX_NUMBER',
            'sort' => 100,
            'multiple' => 'N',
            'mandatory' => 'N',
            'showFilter' => 'Y',
            'showInList' => 'Y',
            'editInList' => 'Y',
            'isSearchable' => 'Y',
            'editFormLabel' => [
                'en' => '',
                'ru' => 'Номер приложения'
            ],
            'help_message' => 'Номер приложения к договору для генерации документов',
            'settings' => [
                'DEFAULT_VALUE' => '',
                'SIZE' => 50,
                'ROWS' => 1,
                'MIN_LENGTH' => 0,
                'MAX_LENGTH' => 255,
                'REGEXP' => ''
            ]
        ],
        'UF_CRM_APPENDIX_DATE' => [
            'name' => 'Дата приложения',
            'userTypeId' => 'string',
            'entityId' => 'COMPANY',
            'xmlId' => 'UF_CRM_APPENDIX_DATE',
            'sort' => 100,
            'multiple' => 'N',
            'mandatory' => 'N',
            'showFilter' => 'Y',
            'showInList' => 'Y',
            'editInList' => 'Y',
            'isSearchable' => 'Y',
            'editFormLabel' => [
                'en' => '',
                'ru' => 'Дата приложения'
            ],
            'help_message' => 'Дата приложения к договору для генерации документов',
            'settings' => [
                'DEFAULT_VALUE' => '',
                'SIZE' => 50,
                'ROWS' => 1,
                'MIN_LENGTH' => 0,
                'MAX_LENGTH' => 255,
                'REGEXP' => ''
            ]
        ],
        'UF_CRM_TASK' => [
            'name' => 'Привязка к задаче',
            'userTypeId' => 'crm',
            'entityId' => 'TASK',
            'xmlId' => 'UF_CRM_TASK',
            'sort' => 100,
            'multiple' => 'Y',
            'mandatory' => 'N',
            'showFilter' => 'Y',
            'showInList' => 'Y',
            'editInList' => 'Y',
            'isSearchable' => 'Y',
            'editFormLabel' => [
                'en' => '',
                'ru' => 'Привязка к задаче'
            ],
            'help_message' => 'Привязка к задаче в CRM',
            'settings' => [
                'DEFAULT_VALUE' => '',
                'CRM_FIELDS' => ['C_']
            ]
        ]
    ];

    public function __construct($call, Logger $logger)
    {
        $this->call = $call;
        $this->logger = $logger;
    }

    /**
     * Получает конфигурацию поля по коду
     * 
     * @param string $fieldCode Код поля
     * @return array|null
     */
    public function getFieldConfig(string $fieldCode): ?array
    {
        return $this->fieldsConfig[$fieldCode] ?? null;
    }

    /**
     * Получает все конфигурации полей
     * 
     * @return array
     */
    public function getAllFieldsConfig(): array
    {
        return $this->fieldsConfig;
    }

    /**
     * Проверяет, есть ли поле в конфигурации
     * 
     * @param string $fieldCode Код поля
     * @return bool
     */
    public function isFieldConfigured(string $fieldCode): bool
    {
        return isset($this->fieldsConfig[$fieldCode]);
    }

    /**
     * Создает или обновляет поле на основе конфигурации
     * 
     * @param string $fieldCode Код поля
     * @return array
     */
    public function createOrUpdateFieldFromConfig(string $fieldCode): array
    {
        $config = $this->getFieldConfig($fieldCode);
        if (!$config) {
            return [
                'status' => 'error',
                'message' => "Поле $fieldCode не найдено в конфигурации"
            ];
        }

        // Проверяем, существует ли поле
        $fieldExists = $this->checkFieldExists($fieldCode, $config['entityId']);
        
        if ($fieldExists) {
            // Обновляем существующее поле
            return $this->updateFieldFromConfig($fieldCode, $config);
        } else {
            // Создаем новое поле
            return $this->createFieldFromConfig($fieldCode, $config);
        }
    }

    /**
     * Создает новое поле на основе конфигурации
     * 
     * @param string $fieldCode Код поля
     * @param array $config Конфигурация поля
     * @return array
     */
    private function createFieldFromConfig(string $fieldCode, array $config): array
    {
        try {
            $params = [
                'fieldName' => $fieldCode,
                'userTypeId' => $config['userTypeId'],
                'xmlId' => $config['xmlId'],
                'sort' => $config['sort'],
                'multiple' => $config['multiple'],
                'mandatory' => $config['mandatory'],
                'showFilter' => $config['showFilter'],
                'showInList' => $config['showInList'],
                'editInList' => $config['editInList'],
                'isSearchable' => $config['isSearchable'],
                'editFormLabel' => $config['editFormLabel'],
                'errorMessage' => '',
                'helpMessage' => $config['help_message'],
                'settings' => $config['settings']
            ];

            $result = $this->call->callBitrix24API('crm.' . strtolower($config['entityId']) . '.userfield.add', [
                'fields' => $params
            ]);

            if (empty($result['result'])) {
                throw new Exception("Ошибка при создании поля: " . json_encode($result));
            }

            $this->logger->log([
                'type' => 'field_creation',
                'status' => 'success',
                'field_code' => $fieldCode,
                'field_name' => $config['name'],
                'entity_type' => $config['entityId'],
                'field_id' => $result['result'],
                'message' => "Поле $fieldCode успешно создано"
            ]);

            return [
                'status' => 'success',
                'field_id' => $result['result'],
                'message' => "Поле $fieldCode успешно создано"
            ];

        } catch (Exception $e) {
            $this->logger->log([
                'type' => 'field_creation',
                'status' => 'error',
                'field_code' => $fieldCode,
                'field_name' => $config['name'],
                'entity_type' => $config['entityId'],
                'message' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Обновляет существующее поле на основе конфигурации
     * 
     * @param string $fieldCode Код поля
     * @param array $config Конфигурация поля
     * @return array
     */
    private function updateFieldFromConfig(string $fieldCode, array $config): array
    {
        try {
            // Получаем информацию о существующем поле
            $fieldInfo = $this->getFieldInfo($fieldCode, $config['entityId']);
            if (!$fieldInfo) {
                throw new Exception("Не удалось получить информацию о поле $fieldCode");
            }

            $fieldId = $fieldInfo['ID'];

            $params = [
                'editFormLabel' => $config['editFormLabel'],
                'helpMessage' => $config['help_message'],
                'settings' => $config['settings']
            ];

            $result = $this->call->callBitrix24API('crm.' . strtolower($config['entityId']) . '.userfield.update', [
                'id' => $fieldId,
                'fields' => $params
            ]);

            if (empty($result['result'])) {
                throw new Exception("Ошибка при обновлении поля: " . json_encode($result));
            }

            $this->logger->log([
                'type' => 'field_update',
                'status' => 'success',
                'field_code' => $fieldCode,
                'field_name' => $config['name'],
                'entity_type' => $config['entityId'],
                'field_id' => $fieldId,
                'message' => "Поле $fieldCode успешно обновлено"
            ]);

            return [
                'status' => 'updated',
                'field_id' => $fieldId,
                'message' => "Поле $fieldCode успешно обновлено"
            ];

        } catch (Exception $e) {
            $this->logger->log([
                'type' => 'field_update',
                'status' => 'error',
                'field_code' => $fieldCode,
                'field_name' => $config['name'],
                'entity_type' => $config['entityId'],
                'message' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }





    /**
     * Проверяет существование пользовательского поля в CRM
     * 
     * @param string $fieldCode Код поля (например, UF_CRM_PROJECT_LINK)
     * @param string $entityType Тип сущности (DEAL, CONTACT, COMPANY, LEAD)
     * @return bool
     */
    public function checkFieldExists(string $fieldCode, string $entityType = 'DEAL'): bool
    {
        try {
            $result = $this->call->callBitrix24API('crm.' . strtolower($entityType) . '.userfield.list', [
                'filter' => ['FIELD_NAME' => $fieldCode]
            ]);

            $fields = $result['result'] ?? [];
            return !empty($fields);

        } catch (Exception $e) {
            $this->logger->log("Ошибка при проверке существования поля $fieldCode: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Получает информацию о пользовательском поле
     * 
     * @param string $fieldCode Код поля
     * @param string $entityType Тип сущности
     * @return array|null
     */
    public function getFieldInfo(string $fieldCode, string $entityType = 'DEAL'): ?array
    {
        try {
            $result = $this->call->callBitrix24API('crm.' . strtolower($entityType) . '.userfield.list', [
                'filter' => ['FIELD_NAME' => $fieldCode]
            ]);

            $fields = $result['result'] ?? [];
            return !empty($fields) ? $fields[0] : null;

        } catch (Exception $e) {
            $this->logger->log("Ошибка при получении информации о поле $fieldCode: " . $e->getMessage());
            return null;
        }
    }



    /**
     * Проверяет и создает/обновляет все необходимые поля UF_CRM на основе конфигурации
     * 
     * @return array
     */
    public function checkAndCreateAllFields(): array
    {
        $results = [];
        
        foreach ($this->fieldsConfig as $fieldCode => $fieldConfig) {
            $this->logger->log("Проверка поля: $fieldCode");
            $results[$fieldCode] = $this->createOrUpdateFieldFromConfig($fieldCode);
        }

        return $results;
    }

    /**
     * Получает список всех пользовательских полей для компаний
     * 
     * @return array
     */
    public function getAllCompanyFields(): array
    {
        try {
            $result = $this->call->callBitrix24API('crm.company.userfield.list', []);
            return $result['result'] ?? [];

        } catch (Exception $e) {
            $this->logger->log("Ошибка при получении списка полей компаний: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Выводит отчет о проверке полей
     * 
     * @param array $results Результаты проверки
     * @return void
     */
    public function printReport(array $results): void
    {
        echo "=== ОТЧЕТ О ПРОВЕРКЕ ПОЛЕЙ UF_CRM ===\n\n";
        
        foreach ($results as $fieldCode => $result) {
            echo "Поле: $fieldCode\n";
            echo "Статус: " . $result['status'] . "\n";
            echo "Сообщение: " . $result['message'] . "\n";
            
            if (isset($result['field_id'])) {
                echo "ID поля: " . $result['field_id'] . "\n";
            }
            
            if (isset($result['field_info'])) {
                echo "Информация о поле: " . json_encode($result['field_info'], JSON_UNESCAPED_UNICODE) . "\n";
            }
            
            echo "---\n";
        }
        
        echo "\n=== КОНЕЦ ОТЧЕТА ===\n";
    }
}
