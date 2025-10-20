<?php
/**
 * Главная страница для управления полями UF_CRM
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление полями UF_CRM - Главная</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
            background: white; 
            padding: 40px; 
            border-radius: 12px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        h1 { 
            color: #333; 
            text-align: center; 
            margin-bottom: 30px;
            font-size: 2.5em;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 40px;
            font-size: 1.2em;
        }
        .actions { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 20px; 
            margin: 30px 0;
        }
        .action-card { 
            background: #f8f9fa; 
            padding: 25px; 
            border-radius: 8px; 
            text-align: center; 
            border: 2px solid transparent;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }
        .action-card:hover { 
            border-color: #007cba; 
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .action-icon { 
            font-size: 3em; 
            margin-bottom: 15px; 
        }
        .action-title { 
            font-size: 1.3em; 
            font-weight: bold; 
            margin-bottom: 10px; 
            color: #333;
        }
        .action-desc { 
            color: #666; 
            font-size: 0.9em; 
        }
        .info-section {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin: 30px 0;
            border-left: 4px solid #2196f3;
        }
        .info-section h3 {
            margin-top: 0;
            color: #1976d2;
        }
        .field-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin: 15px 0;
        }
        .field-item {
            background: white;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.9em;
            border: 1px solid #ddd;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Управление полями UF_CRM</h1>
        <p class="subtitle">Проверка и создание пользовательских полей в компаниях Битрикс24</p>
        
        <div class="actions">
            <a href="web_check_fields.php?action=all" class="action-card">
                <div class="action-icon">🔍</div>
                <div class="action-title">Проверить все поля</div>
                <div class="action-desc">Проверить и создать все необходимые поля UF_CRM</div>
            </a>
            
            <a href="web_check_fields.php?action=list" class="action-card">
                <div class="action-icon">📋</div>
                <div class="action-title">Список полей</div>
                <div class="action-desc">Показать все существующие поля компаний</div>
            </a>
            
            <a href="web_check_fields.php?action=types" class="action-card">
                <div class="action-icon">🔧</div>
                <div class="action-title">Типы полей</div>
                <div class="action-desc">Показать доступные типы пользовательских полей</div>
            </a>
            
            <a href="web_check_fields.php?action=configs" class="action-card">
                <div class="action-icon">⚙️</div>
                <div class="action-title">Конфигурации</div>
                <div class="action-desc">Показать конфигурации пользовательских полей CRM</div>
            </a>
            
            <a href="web_check_fields.php?action=help" class="action-card">
                <div class="action-icon">❓</div>
                <div class="action-title">Справка</div>
                <div class="action-desc">Подробная документация по использованию</div>
            </a>
        </div>
        
        <div class="info-section">
            <h3>📊 Поддерживаемые поля</h3>
            <p>Система автоматически проверяет и создает следующие поля:</p>
            
            <div class="field-list">
                <div class="field-item">UF_CRM_PROJECT_LINK</div>
                <div class="field-item">UF_CRM_EXTRANET_USER</div>
                <div class="field-item">UF_CRM_HOURS_LIMIT</div>
                <div class="field-item">UF_CRM_NOTIFY_DATE</div>
                <div class="field-item">UF_CRM_FRONTEND_RATE</div>
                <div class="field-item">UF_CRM_BACKEND_RATE</div>
                <div class="field-item">UF_CRM_DESIGNER_RATE</div>
                <div class="field-item">UF_CRM_PM_RATE</div>
                <div class="field-item">UF_CRM_CONTENT_MANAGER_RATE</div>
            </div>
        </div>
        
        <div class="info-section">
            <h3>🚀 Быстрые ссылки</h3>
            <p>Проверить конкретные поля:</p>
            <div class="field-list">
                <a href="web_check_fields.php?action=field&field=UF_CRM_PROJECT_LINK" class="field-item">Ссылка на проект</a>
                <a href="web_check_fields.php?action=field&field=UF_CRM_FRONTEND_RATE" class="field-item">Тариф Front-end</a>
                <a href="web_check_fields.php?action=field&field=UF_CRM_BACKEND_RATE" class="field-item">Тариф Back-end</a>
                <a href="web_check_fields.php?action=field&field=UF_CRM_DESIGNER_RATE" class="field-item">Тариф Дизайнера</a>
            </div>
        </div>
        
        <div class="footer">
            <p>Время: <?php echo date('d.m.Y H:i:s'); ?></p>
            <p>Система управления полями UF_CRM для Битрикс24</p>
        </div>
    </div>
</body>
</html>
