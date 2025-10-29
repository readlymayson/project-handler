<?php
# -*- coding: utf-8 -*-

require_once 'config.php';

/**
 * Класс для генерации документов через сторонние библиотеки
 * 
 * Этот класс заменяет встроенную систему Document Generator Bitrix24
 * на внешние решения для генерации Excel отчетов, счетов и актов.
 * 
 * Поддерживаемые форматы:
 * - Excel (.xlsx) через PhpSpreadsheet
 * - PDF через TCPDF или DomPDF
 * - CSV для простых отчетов
 */
class ExternalDocumentGenerator
{
    private $call;
    private $logger;
    private string $outputDir;
    private array $config;

    public function __construct($call, $logger)
    {
        $this->call = $call;
        $this->logger = $logger;
        $this->outputDir = EXTERNAL_DOCUMENTS_OUTPUT_DIR;
        $this->config = $this->loadConfig();
        
        // Создаем директорию для документов если её нет
        $this->ensureOutputDirectory();
    }

    /**
     * Проверяет настройки внешнего генератора документов
     * 
     * @return array Статус настроек
     */
    public function checkExternalGeneratorSettings(): array
    {
        $settings = [
            'enabled' => ENABLE_EXTERNAL_DOCUMENT_GENERATOR,
            'auto_generate' => AUTO_GENERATE_EXTERNAL_DOCUMENTS,
            'generate_on_creation' => GENERATE_ON_DEAL_CREATION,
            'generate_on_update' => GENERATE_ON_DEAL_UPDATE,
            'output_directory' => $this->outputDir,
            'output_directory_writable' => is_writable($this->outputDir),
            'libraries' => [
                'phpspreadsheet' => $this->checkPhpSpreadsheet(),
                'tcpdf' => $this->checkTCPDF(),
                'dompdf' => $this->checkDomPDF()
            ],
            'formats' => [
                'excel' => EXTERNAL_EXCEL_GENERATION,
                'pdf' => EXTERNAL_PDF_GENERATION,
                'csv' => EXTERNAL_CSV_GENERATION
            ],
            'timeout' => EXTERNAL_DOCUMENT_GENERATION_TIMEOUT,
            'retry_attempts' => EXTERNAL_DOCUMENT_RETRY_ATTEMPTS,
            'retry_delay' => EXTERNAL_DOCUMENT_RETRY_DELAY
        ];

        $settings['is_ready'] = $settings['enabled'] && 
                               $settings['output_directory_writable'] && 
                               ($settings['libraries']['phpspreadsheet'] || $settings['libraries']['tcpdf']);

        return $settings;
    }

    /**
     * Генерирует все документы для сделки через внешние библиотеки
     * 
     * @param int $dealId ID сделки
     * @param array $company Данные компании
     * @param array $projectTimeData Данные о времени по проекту
     * @param int $projectId ID проекта
     * @return array Результат генерации документов
     */
    public function generateDocumentsForDeal($dealId, $company, $projectTimeData, $projectId): array
    {
        // Проверяем, включена ли генерация документов
        if (!ENABLE_EXTERNAL_DOCUMENT_GENERATOR) {
            $this->logger->info("Внешняя генерация документов отключена в настройках");
            return [
                'status' => 'disabled',
                'message' => 'Внешняя генерация документов отключена в настройках',
                'documents' => []
            ];
        }
        
        try {
            $documents = [];
            
            // Подготавливаем данные для документов
            $documentData = $this->prepareDocumentData($company, $projectTimeData, $projectId);
            
            // Генерируем Excel отчет
            if (EXTERNAL_EXCEL_GENERATION) {
                $excelReport = $this->generateExcelReport($dealId, $documentData);
                if ($excelReport) {
                    $documents['excel_report'] = $excelReport;
                }
            }
            
            // Генерируем PDF счет
            if (EXTERNAL_PDF_GENERATION) {
                $pdfInvoice = $this->generatePDFInvoice($dealId, $documentData);
                if ($pdfInvoice) {
                    $documents['pdf_invoice'] = $pdfInvoice;
                }
            }
            
            // Генерируем PDF акт
            if (EXTERNAL_PDF_GENERATION) {
                $pdfAct = $this->generatePDFAct($dealId, $documentData);
                if ($pdfAct) {
                    $documents['pdf_act'] = $pdfAct;
                }
            }
            
            // Генерируем CSV отчет (для резервного варианта)
            if (EXTERNAL_CSV_GENERATION) {
                $csvReport = $this->generateCSVReport($dealId, $documentData);
                if ($csvReport) {
                    $documents['csv_report'] = $csvReport;
                }
            }
            
            if (empty($documents)) {
                return [
                    'status' => 'warning',
                    'message' => 'Не удалось сгенерировать ни одного документа. Проверьте настройки и доступность библиотек.'
                ];
            }
            
            return [
                'status' => 'success',
                'documents' => $documents,
                'message' => 'Документы успешно сгенерированы через внешние библиотеки'
            ];
            
        } catch (Exception $e) {
            $this->logger->log("Ошибка при генерации внешних документов для сделки $dealId: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Генерирует Excel отчет через PhpSpreadsheet
     */
    private function generateExcelReport($dealId, $documentData): ?array
    {
        if (!$this->checkPhpSpreadsheet()) {
            $this->logger->log("PhpSpreadsheet не доступен для генерации Excel отчета");
            return null;
        }

        try {
            // Подключаем PhpSpreadsheet
            require_once EXTERNAL_LIBRARIES_PATH . '/vendor/autoload.php';
            
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Заголовок отчета
            $sheet->setTitle('Отчет по задачам');
            $sheet->setCellValue('A1', 'ОТЧЕТ ПО ЗАДАЧАМ ЗА ' . $documentData['MONTH_NAME'] . ' ' . $documentData['YEAR']);
            $sheet->mergeCells('A1:D1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            
            // Заголовки таблицы
            $headers = ['Дата создания', 'Наименование задачи', 'Исполнитель', 'Время за отчетный период'];
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . '3', $header);
                $sheet->getStyle($col . '3')->getFont()->setBold(true);
                $col++;
            }
            
            // Данные задач
            $row = 4;
            foreach ($documentData['TASKS_DATA'] as $task) {
                $sheet->setCellValue('A' . $row, $task['CREATED_DATE']);
                $sheet->setCellValue('B' . $row, $task['TITLE']);
                $sheet->setCellValue('C' . $row, $task['EXECUTOR_POSITION']);
                $sheet->setCellValue('D' . $row, $task['HOURS']);
                $row++;
            }
            
            // Итого
            $sheet->setCellValue('C' . $row, 'ИТОГО ЧАСОВ:');
            $sheet->setCellValue('D' . $row, $documentData['TOTAL_HOURS']);
            $sheet->getStyle('C' . $row . ':D' . $row)->getFont()->setBold(true);
            
            // Автоподбор ширины колонок
            foreach (range('A', 'D') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            
            // Сохраняем файл
            $filename = 'report_deal_' . $dealId . '_' . date('Y-m-d_H-i-s') . '.xlsx';
            $filepath = $this->outputDir . '/' . $filename;
            
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($filepath);
            
            $this->logger->log([
                'success' => 'Excel отчет успешно сгенерирован',
                'deal_id' => $dealId,
                'filename' => $filename,
                'filepath' => $filepath
            ]);
            
            return [
                'type' => 'excel',
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => filesize($filepath),
                'url' => $this->getDocumentUrl($filename)
            ];
            
        } catch (Exception $e) {
            $this->logger->log("Ошибка при генерации Excel отчета для сделки $dealId: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Генерирует PDF счет через TCPDF
     */
    private function generatePDFInvoice($dealId, $documentData): ?array
    {
        if (!$this->checkTCPDF()) {
            $this->logger->log("TCPDF не доступен для генерации PDF счета");
            return null;
        }

        try {
            // Подключаем TCPDF
            require_once EXTERNAL_LIBRARIES_PATH . '/tcpdf/tcpdf.php';
            
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Настройки документа
            $pdf->SetCreator('Project Handler');
            $pdf->SetTitle('Счет № ' . $documentData['INVOICE_NUMBER']);
            $pdf->SetSubject('Счет за услуги');
            
            // Устанавливаем шрифт
            $pdf->SetFont('dejavusans', '', 10);
            
            // Добавляем страницу
            $pdf->AddPage();
            
            // Заголовок счета
            $pdf->SetFont('dejavusans', 'B', 16);
            $pdf->Cell(0, 10, 'СЧЕТ № ' . $documentData['INVOICE_NUMBER'], 0, 1, 'C');
            $pdf->Ln(5);
            
            // Информация о сторонах
            $pdf->SetFont('dejavusans', '', 10);
            $pdf->Cell(0, 6, 'Исполнитель: ' . $documentData['COMPANY_NAME'], 0, 1);
            $pdf->Cell(0, 6, 'Заказчик: ' . $documentData['COMPANY_TITLE'], 0, 1);
            $pdf->Ln(10);
            
            // Таблица услуг
            $pdf->SetFont('dejavusans', 'B', 9);
            $pdf->Cell(10, 8, '№', 1, 0, 'C');
            $pdf->Cell(80, 8, 'Наименование', 1, 0, 'C');
            $pdf->Cell(20, 8, 'Кол-во', 1, 0, 'C');
            $pdf->Cell(15, 8, 'Ед.', 1, 0, 'C');
            $pdf->Cell(25, 8, 'Цена', 1, 0, 'C');
            $pdf->Cell(20, 8, 'НДС', 1, 0, 'C');
            $pdf->Cell(25, 8, 'Сумма', 1, 1, 'C');
            
            $pdf->SetFont('dejavusans', '', 8);
            foreach ($documentData['ROLES_DATA'] as $role) {
                $pdf->Cell(10, 6, $role['NUMBER'], 1, 0, 'C');
                $pdf->Cell(80, 6, $role['SERVICE_NAME_INVOICE'], 1, 0);
                $pdf->Cell(20, 6, $role['HOURS'], 1, 0, 'C');
                $pdf->Cell(15, 6, 'час', 1, 0, 'C');
                $pdf->Cell(25, 6, number_format($role['RATE'], 2), 1, 0, 'R');
                $pdf->Cell(20, 6, $documentData['VAT_RATE'], 1, 0, 'C');
                $pdf->Cell(25, 6, number_format($role['AMOUNT'], 2), 1, 1, 'R');
            }
            
            // Итого
            $pdf->SetFont('dejavusans', 'B', 10);
            $pdf->Cell(170, 8, 'ИТОГО:', 1, 0, 'R');
            $pdf->Cell(25, 8, number_format($documentData['TOTAL_COST'], 2) . ' руб.', 1, 1, 'R');
            
            // Сохраняем файл
            $filename = 'invoice_deal_' . $dealId . '_' . date('Y-m-d_H-i-s') . '.pdf';
            $filepath = $this->outputDir . '/' . $filename;
            
            $pdf->Output($filepath, 'F');
            
            $this->logger->log([
                'success' => 'PDF счет успешно сгенерирован',
                'deal_id' => $dealId,
                'filename' => $filename,
                'filepath' => $filepath
            ]);
            
            return [
                'type' => 'pdf',
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => filesize($filepath),
                'url' => $this->getDocumentUrl($filename)
            ];
            
        } catch (Exception $e) {
            $this->logger->log("Ошибка при генерации PDF счета для сделки $dealId: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Генерирует PDF акт через TCPDF
     */
    private function generatePDFAct($dealId, $documentData): ?array
    {
        if (!$this->checkTCPDF()) {
            $this->logger->log("TCPDF не доступен для генерации PDF акта");
            return null;
        }

        try {
            // Подключаем TCPDF
            require_once EXTERNAL_LIBRARIES_PATH . '/tcpdf/tcpdf.php';
            
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Настройки документа
            $pdf->SetCreator('Project Handler');
            $pdf->SetTitle('Акт № ' . $documentData['ACT_NUMBER']);
            $pdf->SetSubject('Акт выполненных работ');
            
            // Устанавливаем шрифт
            $pdf->SetFont('dejavusans', '', 10);
            
            // Добавляем страницу
            $pdf->AddPage();
            
            // Заголовок акта
            $pdf->SetFont('dejavusans', 'B', 16);
            $pdf->Cell(0, 10, 'АКТ № ' . $documentData['ACT_NUMBER'], 0, 1, 'C');
            $pdf->Cell(0, 6, 'выполненных работ от ' . date('d.m.Y'), 0, 1, 'C');
            $pdf->Ln(10);
            
            // Информация о сторонах
            $pdf->SetFont('dejavusans', '', 10);
            $pdf->Cell(0, 6, 'Исполнитель: ' . $documentData['COMPANY_NAME'], 0, 1);
            $pdf->Cell(0, 6, 'Заказчик: ' . $documentData['COMPANY_TITLE'], 0, 1);
            $pdf->Ln(10);
            
            // Таблица услуг
            $pdf->SetFont('dejavusans', 'B', 9);
            $pdf->Cell(10, 8, '№', 1, 0, 'C');
            $pdf->Cell(80, 8, 'Наименование', 1, 0, 'C');
            $pdf->Cell(20, 8, 'Кол-во', 1, 0, 'C');
            $pdf->Cell(15, 8, 'Ед.', 1, 0, 'C');
            $pdf->Cell(25, 8, 'Цена', 1, 0, 'C');
            $pdf->Cell(20, 8, 'НДС', 1, 0, 'C');
            $pdf->Cell(25, 8, 'Сумма', 1, 1, 'C');
            
            $pdf->SetFont('dejavusans', '', 8);
            foreach ($documentData['ROLES_DATA'] as $role) {
                $pdf->Cell(10, 6, $role['NUMBER'], 1, 0, 'C');
                $pdf->Cell(80, 6, $role['SERVICE_NAME_ACT'], 1, 0);
                $pdf->Cell(20, 6, $role['HOURS'], 1, 0, 'C');
                $pdf->Cell(15, 6, 'час', 1, 0, 'C');
                $pdf->Cell(25, 6, number_format($role['RATE'], 2), 1, 0, 'R');
                $pdf->Cell(20, 6, $documentData['VAT_RATE'], 1, 0, 'C');
                $pdf->Cell(25, 6, number_format($role['AMOUNT'], 2), 1, 1, 'R');
            }
            
            // Итого
            $pdf->SetFont('dejavusans', 'B', 10);
            $pdf->Cell(170, 8, 'ИТОГО:', 1, 0, 'R');
            $pdf->Cell(25, 8, number_format($documentData['TOTAL_COST'], 2) . ' руб.', 1, 1, 'R');
            
            // Сохраняем файл
            $filename = 'act_deal_' . $dealId . '_' . date('Y-m-d_H-i-s') . '.pdf';
            $filepath = $this->outputDir . '/' . $filename;
            
            $pdf->Output($filepath, 'F');
            
            $this->logger->log([
                'success' => 'PDF акт успешно сгенерирован',
                'deal_id' => $dealId,
                'filename' => $filename,
                'filepath' => $filepath
            ]);
            
            return [
                'type' => 'pdf',
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => filesize($filepath),
                'url' => $this->getDocumentUrl($filename)
            ];
            
        } catch (Exception $e) {
            $this->logger->log("Ошибка при генерации PDF акта для сделки $dealId: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Генерирует CSV отчет (резервный вариант)
     */
    private function generateCSVReport($dealId, $documentData): ?array
    {
        try {
            $filename = 'report_deal_' . $dealId . '_' . date('Y-m-d_H-i-s') . '.csv';
            $filepath = $this->outputDir . '/' . $filename;
            
            $file = fopen($filepath, 'w');
            if (!$file) {
                throw new Exception("Не удалось создать CSV файл: $filepath");
            }
            
            // Записываем BOM для корректного отображения кириллицы в Excel
            fwrite($file, "\xEF\xBB\xBF");
            
            // Заголовки
            fputcsv($file, ['Дата создания', 'Наименование задачи', 'Исполнитель', 'Время за отчетный период'], ';');
            
            // Данные задач
            foreach ($documentData['TASKS_DATA'] as $task) {
                fputcsv($file, [
                    $task['CREATED_DATE'],
                    $task['TITLE'],
                    $task['EXECUTOR_POSITION'],
                    $task['HOURS']
                ], ';');
            }
            
            // Итого
            fputcsv($file, ['', '', 'ИТОГО ЧАСОВ:', $documentData['TOTAL_HOURS']], ';');
            
            fclose($file);
            
            $this->logger->log([
                'success' => 'CSV отчет успешно сгенерирован',
                'deal_id' => $dealId,
                'filename' => $filename,
                'filepath' => $filepath
            ]);
            
            return [
                'type' => 'csv',
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => filesize($filepath),
                'url' => $this->getDocumentUrl($filename)
            ];
            
        } catch (Exception $e) {
            $this->logger->log("Ошибка при генерации CSV отчета для сделки $dealId: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Подготавливает данные для документов
     */
    private function prepareDocumentData($company, $projectTimeData, $projectId): array
    {
        $lastMonth = new DateTime('first day of last month');
        $monthName = $this->getMonthNameInGenitive($lastMonth->format('n'));
        $year = $lastMonth->format('Y');
        
        // Получаем информацию о договоре
        $contractInfo = $this->getContractInfo($company);
        
        // Формируем таблицу с данными по ролям
        $rolesData = [];
        $itemNumber = 1;
        
        foreach ($projectTimeData['roles_time'] as $role => $timeData) {
            $rate = getRoleRate($role, $company);
            $hours = $timeData['decimal_hours'];
            $amount = $hours * $rate;
            
            // Убираем номер из роли для наименования услуги
            $cleanRole = preg_replace('/ #\d+$/', '', $role);
            $cleanRole = mb_strtolower($cleanRole);
            
            $rolesData[] = [
                'NUMBER' => $itemNumber,
                'ROLE' => $cleanRole,
                'SERVICE_NAME_INVOICE' => "Оплата услуг {$cleanRole} в {$monthName} {$year} года по приложению {$contractInfo['appendix_number']} от {$contractInfo['appendix_date']} к Договору {$contractInfo['contract_number']} от {$contractInfo['contract_date']}",
                'SERVICE_NAME_ACT' => "Услуги {$cleanRole} в {$monthName} {$year} года по приложению {$contractInfo['appendix_number']} от {$contractInfo['appendix_date']} к Договору {$contractInfo['contract_number']} от {$contractInfo['contract_date']}",
                'HOURS' => $hours,
                'RATE' => $rate,
                'AMOUNT' => $amount
            ];
            
            $itemNumber++;
        }
        
        // Получаем данные задач для отчета
        $tasksData = $this->getTasksDataForReport($projectId, $lastMonth);
        
        return [
            'MONTH_NAME' => $monthName,
            'YEAR' => $year,
            'CONTRACT_NUMBER' => $contractInfo['contract_number'],
            'CONTRACT_DATE' => $contractInfo['contract_date'],
            'APPENDIX_NUMBER' => $contractInfo['appendix_number'],
            'APPENDIX_DATE' => $contractInfo['appendix_date'],
            'TOTAL_HOURS' => $projectTimeData['total_hours'],
            'TOTAL_COST' => $projectTimeData['total_cost'],
            'ROLES_DATA' => $rolesData,
            'TASKS_DATA' => $tasksData,
            'INVOICE_NUMBER' => $this->generateDocumentNumber('invoice', $company['ID']),
            'ACT_NUMBER' => $this->generateDocumentNumber('act', $company['ID']),
            'VAT_RATE' => VAT_RATE,
            'COMPANY_NAME' => COMPANY_NAME,
            'COMPANY_TITLE' => $company['TITLE'] ?? 'Не указано'
        ];
    }

    /**
     * Получает данные задач для отчета
     */
    private function getTasksDataForReport($projectId, $lastMonth): array
    {
        $tasks = [];
        
        try {
            $firstDay = clone $lastMonth;
            $firstDay->setTime(0, 0, 0);
            
            $lastDay = new DateTime('last day of ' . $lastMonth->format('Y-m'));
            $lastDay->setTime(23, 59, 59);
            
            // Получаем список задач
            $method = 'tasks.task.list';
            $params = [
                'filter' => [
                    'GROUP_ID' => $projectId,
                ],
                'select' => ['ID', 'TITLE', 'CREATED_DATE', 'CREATED_BY', 'TIME_SPENT_IN_LOGS']
            ];
            
            apiDelay();
            $response = $this->call->callBitrix24API($method, $params);
            $tasksList = $response['result']['tasks'] ?? [];
            
            foreach ($tasksList as $task) {
                $taskId = $task['id'] ?? $task['ID'];
                
                // Получаем информацию о затраченном времени за прошлый месяц
                $taskTime = $this->getTaskTimeForPeriod($taskId, $firstDay, $lastDay);
                
                if ($taskTime['total_hours'] > 0) {
                    $createdDate = new DateTime($task['createdDate'] ?? $task['CREATED_DATE']);
                    
                    $tasks[] = [
                        'CREATED_DATE' => $createdDate->format('d.m.Y'),
                        'TITLE' => $task['title'] ?? $task['TITLE'],
                        'EXECUTOR_POSITION' => $taskTime['positions'],
                        'HOURS' => round($taskTime['total_hours'], 2)
                    ];
                }
            }
            
        } catch (Exception $e) {
            $this->logger->log("Ошибка при получении данных задач для отчета: " . $e->getMessage());
        }
        
        return $tasks;
    }

    /**
     * Получает информацию о затраченном времени за период
     */
    private function getTaskTimeForPeriod($taskId, $firstDay, $lastDay): array
    {
        $totalHours = 0;
        $positions = [];
        
        try {
            apiDelay();
            $response = $this->call->callBitrix24API('task.elapseditem.getlist', [
                'TASKID' => $taskId
            ]);
            
            $elapsedItems = $response['result'] ?? [];
            
            foreach ($elapsedItems as $item) {
                if (empty($item['CREATED_DATE'])) {
                    continue;
                }
                
                $createdDate = new DateTime($item['CREATED_DATE']);
                
                if ($createdDate >= $firstDay && $createdDate <= $lastDay) {
                    $seconds = (int)($item['SECONDS'] ?? 0);
                    $hours = $seconds / 3600;
                    $totalHours += $hours;
                    
                    // Получаем должность пользователя
                    $userId = $item['USER_ID'] ?? 0;
                    if ($userId > 0) {
                        $position = $this->getUserPosition($userId);
                        if (!in_array($position, $positions)) {
                            $positions[] = $position;
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            $this->logger->log("Ошибка при получении времени задачи $taskId: " . $e->getMessage());
        }
        
        return [
            'total_hours' => $totalHours,
            'positions' => implode(', ', $positions)
        ];
    }

    /**
     * Получает должность пользователя
     */
    private function getUserPosition($userId): string
    {
        try {
            apiDelay();
            $result = $this->call->callBitrix24API('user.get', [
                'filter' => ['ID' => $userId]
            ]);
            
            $user = $result['result'][0] ?? [];
            return $user['WORK_POSITION'] ?? 'Не указано';
            
        } catch (Exception $e) {
            $this->logger->log("Ошибка при получении должности пользователя $userId: " . $e->getMessage());
            return 'Не указано';
        }
    }
    
    /**
     * Получает информацию о договоре и приложении из компании
     */
    private function getContractInfo($company): array
    {
        return [
            'contract_number' => $company['UF_CRM_CONTRACT_NUMBER'] ?? DEFAULT_CONTRACT_NUMBER,
            'contract_date' => $company['UF_CRM_CONTRACT_DATE'] ?? DEFAULT_CONTRACT_DATE,
            'appendix_number' => $company['UF_CRM_APPENDIX_NUMBER'] ?? DEFAULT_APPENDIX_NUMBER,
            'appendix_date' => $company['UF_CRM_APPENDIX_DATE'] ?? DEFAULT_APPENDIX_DATE
        ];
    }

    /**
     * Генерирует номер документа
     */
    private function generateDocumentNumber($type, $companyId): string
    {
        $prefix = ($type === 'invoice') ? INVOICE_NUMBER_PREFIX : ACT_NUMBER_PREFIX;
        $date = date('Ymd');
        return $prefix . '-' . $date . '-' . $companyId;
    }

    /**
     * Получает название месяца в родительном падеже
     */
    private function getMonthNameInGenitive($monthNumber): string
    {
        $months = [
            1 => 'январе', 2 => 'феврале', 3 => 'марте', 4 => 'апреле',
            5 => 'мае', 6 => 'июне', 7 => 'июле', 8 => 'августе',
            9 => 'сентябре', 10 => 'октябре', 11 => 'ноябре', 12 => 'декабре'
        ];
        
        return $months[(int)$monthNumber] ?? '';
    }

    /**
     * Проверяет доступность PhpSpreadsheet
     */
    private function checkPhpSpreadsheet(): bool
    {
        $autoloadPath = EXTERNAL_LIBRARIES_PATH . '/vendor/autoload.php';
        return file_exists($autoloadPath);
    }

    /**
     * Проверяет доступность TCPDF
     */
    private function checkTCPDF(): bool
    {
        $tcpdfPath = EXTERNAL_LIBRARIES_PATH . '/tcpdf/tcpdf.php';
        return file_exists($tcpdfPath);
    }

    /**
     * Проверяет доступность DomPDF
     */
    private function checkDomPDF(): bool
    {
        $dompdfPath = EXTERNAL_LIBRARIES_PATH . '/dompdf/autoload.inc.php';
        return file_exists($dompdfPath);
    }

    /**
     * Создает директорию для документов если её нет
     */
    private function ensureOutputDirectory(): void
    {
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    /**
     * Загружает конфигурацию
     */
    private function loadConfig(): array
    {
        return [
            'phpspreadsheet' => [
                'enabled' => true,
                'path' => EXTERNAL_LIBRARIES_PATH . '/vendor/autoload.php'
            ],
            'tcpdf' => [
                'enabled' => true,
                'path' => EXTERNAL_LIBRARIES_PATH . '/tcpdf/tcpdf.php'
            ],
            'dompdf' => [
                'enabled' => false,
                'path' => EXTERNAL_LIBRARIES_PATH . '/dompdf/autoload.inc.php'
            ]
        ];
    }

    /**
     * Получает URL для доступа к документу
     */
    private function getDocumentUrl($filename): string
    {
        $relativeUrl = EXTERNAL_DOCUMENTS_URL . '/' . $filename;
        
        // Если указан базовый URL сайта, формируем полный URL
        if (!empty(SITE_BASE_URL)) {
            return rtrim(SITE_BASE_URL, '/') . $relativeUrl;
        }
        
        // Если не указан, пытаемся определить автоматически
        if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_SCHEME'])) {
            $scheme = $_SERVER['REQUEST_SCHEME'] ?? 'http';
            $host = $_SERVER['HTTP_HOST'];
            return $scheme . '://' . $host . $relativeUrl;
        }
        
        // Если не удалось определить, возвращаем относительный URL
        return $relativeUrl;
    }
}
