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
            'type' => 'string',
            'entity_type' => 'COMPANY',
            'mandatory' => false,
            'show_in_list' => true,
            'show_filter' => true,
            'is_searchable' => true,
            'help_message' => 'Ссылка на проект в Битрикс24 или ID проекта',
            'settings' => [
                'SIZE' => 50,
                'ROWS' => 1,
                'MIN_LENGTH' => 0,
                'MAX_LENGTH' => 255,
                'REGEXP' => ''
            ]
        ],
        'UF_CRM_EXTRANET_USER' => [
            'name' => 'Пользователь экстранета',
            'type' => 'user',
            'entity_type' => 'COMPANY',
            'mandatory' => false,
            'show_in_list' => true,
            'show_filter' => true,
            'is_searchable' => true,
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
            'type' => 'double',
            'entity_type' => 'COMPANY',
            'mandatory' => false,
            'show_in_list' => true,
            'show_filter' => true,
            'is_searchable' => true,
            'help_message' => 'Максимальное количество часов для проекта',
            'settings' => [
                'PRECISION' => 2,
                'MIN_VALUE' => 0,
                'MAX_VALUE' => 999999
            ]
        ],
        'UF_CRM_NOTIFY_DATE' => [
            'name' => 'Дата уведомления',
            'type' => 'date',
            'entity_type' => 'COMPANY',
            'mandatory' => false,
            'show_in_list' => true,
            'show_filter' => true,
            'is_searchable' => true,
            'help_message' => 'Дата последнего уведомления о превышении лимита',
            'settings' => [
                'DEFAULT_VALUE' => [
                    'TYPE' => 'NONE'
                ]
            ]
        ],
        'UF_CRM_FRONTEND_RATE' => [
            'name' => 'Front-end разработчик (ставка в час)',
            'type' => 'money',
            'entity_type' => 'COMPANY',
            'mandatory' => false,
            'show_in_list' => true,
            'show_filter' => true,
            'is_searchable' => true,
            'help_message' => 'Ставка Front-end разработчика за 1 час работы в рублях',
            'settings' => [
                'DEFAULT_VALUE' => '',
                'CURRENCY' => 'RUB'
            ]
        ],
        'UF_CRM_BACKEND_RATE' => [
            'name' => 'Back-end разработчик (ставка в час)',
            'type' => 'money',
            'entity_type' => 'COMPANY',
            'mandatory' => false,
            'show_in_list' => true,
            'show_filter' => true,
            'is_searchable' => true,
            'help_message' => 'Ставка Back-end разработчика за 1 час работы в рублях',
            'settings' => [
                'DEFAULT_VALUE' => '',
                'CURRENCY' => 'RUB'
            ]
        ],
        'UF_CRM_DESIGNER_RATE' => [
            'name' => 'Дизайнер (ставка в час)',
            'type' => 'money',
            'entity_type' => 'COMPANY',
            'mandatory' => false,
            'show_in_list' => true,
            'show_filter' => true,
            'is_searchable' => true,
            'help_message' => 'Ставка Дизайнера за 1 час работы в рублях',
            'settings' => [
                'DEFAULT_VALUE' => '',
                'CURRENCY' => 'RUB'
            ]
        ],
        'UF_CRM_PM_RATE' => [
            'name' => 'Проект-менеджер (ставка в час)',
            'type' => 'money',
            'entity_type' => 'COMPANY',
            'mandatory' => false,
            'show_in_list' => true,
            'show_filter' => true,
            'is_searchable' => true,
            'help_message' => 'Ставка Проект-менеджера за 1 час работы в рублях',
            'settings' => [
                'DEFAULT_VALUE' => '',
                'CURRENCY' => 'RUB'
            ]
        ],
        'UF_CRM_CONTENT_MANAGER_RATE' => [
            'name' => 'Контент-менеджер (ставка в час)',
            'type' => 'money',
            'entity_type' => 'COMPANY',
            'mandatory' => false,
            'show_in_list' => true,
            'show_filter' => true,
            'is_searchable' => true,
            'help_message' => 'Ставка Контент-менеджера за 1 час работы в рублях',
            'settings' => [
                'DEFAULT_VALUE' => '',
                'CURRENCY' => 'RUB'
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
     * Создает поле на основе конфигурации
     * 
     * @param string $fieldCode Код поля
     * @return array
     */
    public function createFieldFromConfig(string $fieldCode): array
    {
        $config = $this->getFieldConfig($fieldCode);
        if (!$config) {
            return [
                'status' => 'error',
                'message' => "Поле $fieldCode не найдено в конфигурации"
            ];
        }

        // Проверяем, существует ли поле
        if ($this->checkFieldExists($fieldCode, $config['entity_type'])) {
            $fieldInfo = $this->getFieldInfo($fieldCode, $config['entity_type']);
            return [
                'status' => 'exists',
                'message' => "Поле $fieldCode уже существует",
                'field_info' => $fieldInfo
            ];
        }

        // Создаем поле в зависимости от типа
        switch ($config['type']) {
            case 'string':
                return $this->createTextFieldFromConfig($fieldCode, $config);
            case 'double':
                return $this->createDoubleFieldFromConfig($fieldCode, $config);
            case 'date':
                return $this->createDateFieldFromConfig($fieldCode, $config);
            case 'user':
                return $this->createUserFieldFromConfig($fieldCode, $config);
            case 'money':
                return $this->createMoneyFieldFromConfig($fieldCode, $config);
            default:
                return [
                    'status' => 'error',
                    'message' => "Неподдерживаемый тип поля: " . $config['type']
                ];
        }
    }

    /**
     * Создает текстовое поле на основе конфигурации
     * 
     * @param string $fieldCode Код поля
     * @param array $config Конфигурация поля
     * @return array
     */
    private function createTextFieldFromConfig(string $fieldCode, array $config): array
    {
        $params = [
            'FIELD_NAME' => $fieldCode,
            'USER_TYPE_ID' => 'string',
            'XML_ID' => $fieldCode,
            'SORT' => 100,
            'MULTIPLE' => 'N',
            'MANDATORY' => $config['mandatory'] ? 'Y' : 'N',
            'SHOW_FILTER' => $config['show_filter'] ? 'Y' : 'N',
            'SHOW_IN_LIST' => $config['show_in_list'] ? 'Y' : 'N',
            'EDIT_IN_LIST' => 'Y',
            'IS_SEARCHABLE' => $config['is_searchable'] ? 'Y' : 'N',
            'LIST_COLUMN_LABEL' => $config['name'],
            'LIST_FILTER_LABEL' => $config['name'],
            'ERROR_MESSAGE' => '',
            'HELP_MESSAGE' => $config['help_message'],
            'SETTINGS' => array_merge([
                'DEFAULT_VALUE' => '',
                'SIZE' => 20,
                'ROWS' => 1,
                'MIN_LENGTH' => 0,
                'MAX_LENGTH' => 0,
                'REGEXP' => ''
            ], $config['settings'])
        ];

        try {
            $result = $this->call->callBitrix24API('crm.' . strtolower($config['entity_type']) . '.userfield.add', [
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
                'entity_type' => $config['entity_type'],
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
                'entity_type' => $config['entity_type'],
                'message' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Создает числовое поле на основе конфигурации
     * 
     * @param string $fieldCode Код поля
     * @param array $config Конфигурация поля
     * @return array
     */
    private function createDoubleFieldFromConfig(string $fieldCode, array $config): array
    {
        $params = [
            'FIELD_NAME' => $fieldCode,
            'USER_TYPE_ID' => 'double',
            'XML_ID' => $fieldCode,
            'SORT' => 100,
            'MULTIPLE' => 'N',
            'MANDATORY' => $config['mandatory'] ? 'Y' : 'N',
            'SHOW_FILTER' => $config['show_filter'] ? 'Y' : 'N',
            'SHOW_IN_LIST' => $config['show_in_list'] ? 'Y' : 'N',
            'EDIT_IN_LIST' => 'Y',
            'IS_SEARCHABLE' => $config['is_searchable'] ? 'Y' : 'N',
            'LIST_COLUMN_LABEL' => $config['name'],
            'LIST_FILTER_LABEL' => $config['name'],
            'ERROR_MESSAGE' => '',
            'HELP_MESSAGE' => $config['help_message'],
            'SETTINGS' => array_merge([
                'DEFAULT_VALUE' => '',
                'PRECISION' => 2,
                'MIN_VALUE' => 0,
                'MAX_VALUE' => 0
            ], $config['settings'])
        ];

        try {
            $result = $this->call->callBitrix24API('crm.' . strtolower($config['entity_type']) . '.userfield.add', [
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
                'entity_type' => $config['entity_type'],
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
                'entity_type' => $config['entity_type'],
                'message' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Создает поле даты на основе конфигурации
     * 
     * @param string $fieldCode Код поля
     * @param array $config Конфигурация поля
     * @return array
     */
    private function createDateFieldFromConfig(string $fieldCode, array $config): array
    {
        $params = [
            'FIELD_NAME' => $fieldCode,
            'USER_TYPE_ID' => 'date',
            'XML_ID' => $fieldCode,
            'SORT' => 100,
            'MULTIPLE' => 'N',
            'MANDATORY' => $config['mandatory'] ? 'Y' : 'N',
            'SHOW_FILTER' => $config['show_filter'] ? 'Y' : 'N',
            'SHOW_IN_LIST' => $config['show_in_list'] ? 'Y' : 'N',
            'EDIT_IN_LIST' => 'Y',
            'IS_SEARCHABLE' => $config['is_searchable'] ? 'Y' : 'N',
            'LIST_COLUMN_LABEL' => $config['name'],
            'LIST_FILTER_LABEL' => $config['name'],
            'ERROR_MESSAGE' => '',
            'HELP_MESSAGE' => $config['help_message'],
            'SETTINGS' => array_merge([
                'DEFAULT_VALUE' => [
                    'TYPE' => 'NONE'
                ]
            ], $config['settings'])
        ];

        try {
            $result = $this->call->callBitrix24API('crm.' . strtolower($config['entity_type']) . '.userfield.add', [
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
                'entity_type' => $config['entity_type'],
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
                'entity_type' => $config['entity_type'],
                'message' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Создает поле пользователя на основе конфигурации
     * 
     * @param string $fieldCode Код поля
     * @param array $config Конфигурация поля
     * @return array
     */
    private function createUserFieldFromConfig(string $fieldCode, array $config): array
    {
        $params = [
            'FIELD_NAME' => $fieldCode,
            'USER_TYPE_ID' => 'user',
            'XML_ID' => $fieldCode,
            'SORT' => 100,
            'MULTIPLE' => 'N',
            'MANDATORY' => $config['mandatory'] ? 'Y' : 'N',
            'SHOW_FILTER' => $config['show_filter'] ? 'Y' : 'N',
            'SHOW_IN_LIST' => $config['show_in_list'] ? 'Y' : 'N',
            'EDIT_IN_LIST' => 'Y',
            'IS_SEARCHABLE' => $config['is_searchable'] ? 'Y' : 'N',
            'LIST_COLUMN_LABEL' => $config['name'],
            'LIST_FILTER_LABEL' => $config['name'],
            'ERROR_MESSAGE' => '',
            'HELP_MESSAGE' => $config['help_message'],
            'SETTINGS' => array_merge([
                'DEFAULT_VALUE' => '',
                'DISPLAY' => 'LIST',
                'LIST_HEIGHT' => 1,
                'MAX_SHOW_SIZE' => 1,
                'MIN_SHOW_SIZE' => 1
            ], $config['settings'])
        ];

        try {
            $result = $this->call->callBitrix24API('crm.' . strtolower($config['entity_type']) . '.userfield.add', [
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
                'entity_type' => $config['entity_type'],
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
                'entity_type' => $config['entity_type'],
                'message' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Создает денежное поле на основе конфигурации
     * 
     * @param string $fieldCode Код поля
     * @param array $config Конфигурация поля
     * @return array
     */
    private function createMoneyFieldFromConfig(string $fieldCode, array $config): array
    {
        $params = [
            'FIELD_NAME' => $fieldCode,
            'USER_TYPE_ID' => 'money',
            'XML_ID' => $fieldCode,
            'SORT' => 100,
            'MULTIPLE' => 'N',
            'MANDATORY' => $config['mandatory'] ? 'Y' : 'N',
            'SHOW_FILTER' => $config['show_filter'] ? 'Y' : 'N',
            'SHOW_IN_LIST' => $config['show_in_list'] ? 'Y' : 'N',
            'EDIT_IN_LIST' => 'Y',
            'IS_SEARCHABLE' => $config['is_searchable'] ? 'Y' : 'N',
            'LIST_COLUMN_LABEL' => $config['name'],
            'LIST_FILTER_LABEL' => $config['name'],
            'ERROR_MESSAGE' => '',
            'HELP_MESSAGE' => $config['help_message'],
            'SETTINGS' => array_merge([
                'DEFAULT_VALUE' => '',
                'CURRENCY' => 'RUB'
            ], $config['settings'])
        ];

        try {
            $result = $this->call->callBitrix24API('crm.' . strtolower($config['entity_type']) . '.userfield.add', [
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
                'entity_type' => $config['entity_type'],
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
                'entity_type' => $config['entity_type'],
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
     * Создает текстовое пользовательское поле
     * 
     * @param string $fieldCode Код поля
     * @param string $fieldName Название поля
     * @param string $entityType Тип сущности
     * @param array $additionalParams Дополнительные параметры
     * @return array
     */
    public function createTextField(string $fieldCode, string $fieldName, string $entityType = 'DEAL', array $additionalParams = []): array
    {
        try {
            $defaultParams = [
                'FIELD_NAME' => $fieldCode,
                'USER_TYPE_ID' => 'string', // Текстовое поле
                'XML_ID' => $fieldCode,
                'SORT' => 100,
                'MULTIPLE' => 'N',
                'MANDATORY' => 'N',
                'SHOW_FILTER' => 'Y',
                'SHOW_IN_LIST' => 'Y',
                'EDIT_IN_LIST' => 'Y',
                'IS_SEARCHABLE' => 'Y',
                'SETTINGS' => [
                    'DEFAULT_VALUE' => '',
                    'SIZE' => 20,
                    'ROWS' => 1,
                    'MIN_LENGTH' => 0,
                    'MAX_LENGTH' => 0,
                    'REGEXP' => ''
                ]
            ];

            $params = array_merge($defaultParams, $additionalParams);
            $params['LIST_COLUMN_LABEL'] = $fieldName;
            $params['LIST_FILTER_LABEL'] = $fieldName;
            $params['ERROR_MESSAGE'] = '';
            $params['HELP_MESSAGE'] = '';

            $result = $this->call->callBitrix24API('crm.' . strtolower($entityType) . '.userfield.add', [
                'fields' => $params
            ]);

            if (empty($result['result'])) {
                throw new Exception("Ошибка при создании поля: " . json_encode($result));
            }

            $this->logger->log([
                'type' => 'field_creation',
                'status' => 'success',
                'field_code' => $fieldCode,
                'field_name' => $fieldName,
                'entity_type' => $entityType,
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
                'field_name' => $fieldName,
                'entity_type' => $entityType,
                'message' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Создает поле UF_CRM_PROJECT_LINK как текстовое поле
     * 
     * @return array
     */
    public function createProjectLinkField(): array
    {
        $fieldCode = 'UF_CRM_PROJECT_LINK';
        $fieldName = 'Ссылка на проект';
        
        // Проверяем, существует ли поле
        if ($this->checkFieldExists($fieldCode)) {
            $fieldInfo = $this->getFieldInfo($fieldCode);
            return [
                'status' => 'exists',
                'message' => "Поле $fieldCode уже существует",
                'field_info' => $fieldInfo
            ];
        }

        // Создаем поле
        $additionalParams = [
            'HELP_MESSAGE' => 'Ссылка на проект в Битрикс24 или ID проекта',
            'SETTINGS' => [
                'DEFAULT_VALUE' => '',
                'SIZE' => 50,
                'ROWS' => 1,
                'MIN_LENGTH' => 0,
                'MAX_LENGTH' => 255,
                'REGEXP' => ''
            ]
        ];

        return $this->createTextField($fieldCode, $fieldName, 'DEAL', $additionalParams);
    }

    /**
     * Проверяет и создает все необходимые поля UF_CRM на основе конфигурации
     * 
     * @return array
     */
    public function checkAndCreateAllFields(): array
    {
        $results = [];
        
        foreach ($this->fieldsConfig as $fieldCode => $fieldConfig) {
            $this->logger->log("Проверка поля: $fieldCode");
            $results[$fieldCode] = $this->createFieldFromConfig($fieldCode);
        }

        return $results;
    }

    /**
     * Создает числовое поле (double)
     * 
     * @param string $fieldCode Код поля
     * @param string $fieldName Название поля
     * @param string $entityType Тип сущности
     * @param array $additionalParams Дополнительные параметры
     * @return array
     */
    public function createDoubleField(string $fieldCode, string $fieldName, string $entityType = 'DEAL', array $additionalParams = []): array
    {
        try {
            $defaultParams = [
                'FIELD_NAME' => $fieldCode,
                'USER_TYPE_ID' => 'double',
                'XML_ID' => $fieldCode,
                'SORT' => 100,
                'MULTIPLE' => 'N',
                'MANDATORY' => 'N',
                'SHOW_FILTER' => 'Y',
                'SHOW_IN_LIST' => 'Y',
                'EDIT_IN_LIST' => 'Y',
                'IS_SEARCHABLE' => 'Y',
                'SETTINGS' => [
                    'DEFAULT_VALUE' => '',
                    'PRECISION' => 2,
                    'MIN_VALUE' => 0,
                    'MAX_VALUE' => 0
                ]
            ];

            $params = array_merge($defaultParams, $additionalParams);
            $params['LIST_COLUMN_LABEL'] = $fieldName;
            $params['LIST_FILTER_LABEL'] = $fieldName;
            $params['ERROR_MESSAGE'] = '';
            $params['HELP_MESSAGE'] = $additionalParams['HELP_MESSAGE'] ?? '';

            $result = $this->call->callBitrix24API('crm.' . strtolower($entityType) . '.userfield.add', [
                'fields' => $params
            ]);

            if (empty($result['result'])) {
                throw new Exception("Ошибка при создании поля: " . json_encode($result));
            }

            $this->logger->log([
                'type' => 'field_creation',
                'status' => 'success',
                'field_code' => $fieldCode,
                'field_name' => $fieldName,
                'entity_type' => $entityType,
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
                'field_name' => $fieldName,
                'entity_type' => $entityType,
                'message' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Создает поле даты
     * 
     * @param string $fieldCode Код поля
     * @param string $fieldName Название поля
     * @param string $entityType Тип сущности
     * @param array $additionalParams Дополнительные параметры
     * @return array
     */
    public function createDateField(string $fieldCode, string $fieldName, string $entityType = 'DEAL', array $additionalParams = []): array
    {
        try {
            $defaultParams = [
                'FIELD_NAME' => $fieldCode,
                'USER_TYPE_ID' => 'date',
                'XML_ID' => $fieldCode,
                'SORT' => 100,
                'MULTIPLE' => 'N',
                'MANDATORY' => 'N',
                'SHOW_FILTER' => 'Y',
                'SHOW_IN_LIST' => 'Y',
                'EDIT_IN_LIST' => 'Y',
                'IS_SEARCHABLE' => 'Y',
                'SETTINGS' => [
                    'DEFAULT_VALUE' => [
                        'TYPE' => 'NONE'
                    ]
                ]
            ];

            $params = array_merge($defaultParams, $additionalParams);
            $params['LIST_COLUMN_LABEL'] = $fieldName;
            $params['LIST_FILTER_LABEL'] = $fieldName;
            $params['ERROR_MESSAGE'] = '';
            $params['HELP_MESSAGE'] = $additionalParams['HELP_MESSAGE'] ?? '';

            $result = $this->call->callBitrix24API('crm.' . strtolower($entityType) . '.userfield.add', [
                'fields' => $params
            ]);

            if (empty($result['result'])) {
                throw new Exception("Ошибка при создании поля: " . json_encode($result));
            }

            $this->logger->log([
                'type' => 'field_creation',
                'status' => 'success',
                'field_code' => $fieldCode,
                'field_name' => $fieldName,
                'entity_type' => $entityType,
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
                'field_name' => $fieldName,
                'entity_type' => $entityType,
                'message' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
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
