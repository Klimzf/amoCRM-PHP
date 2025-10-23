<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Services/TokenService.php';
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: text/html; charset=utf-8');

// Функция для вывода HTML страницы
function showPage($title, $message, $isError = false) {
    $color = $isError ? '#f44336' : '#4CAF50';
    $icon = $isError ? '❌' : '✅';
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>' . htmlspecialchars($title) . '</title>
        <meta charset="utf-8">
        <style>
            body { 
                font-family: Arial, sans-serif; 
                text-align: center; 
                padding: 50px; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                margin: 0;
            }
            .container { 
                background: rgba(255,255,255,0.1); 
                padding: 40px; 
                border-radius: 15px; 
                backdrop-filter: blur(10px);
                max-width: 600px;
                margin: 0 auto;
                box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            }
            .icon { 
                font-size: 64px;
                margin-bottom: 20px;
            }
            h1 {
                margin-bottom: 20px;
            }
            .debug {
                background: rgba(0,0,0,0.3);
                padding: 15px;
                border-radius: 8px;
                margin: 20px 0;
                text-align: left;
                font-family: monospace;
                font-size: 12px;
                word-break: break-all;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon">' . $icon . '</div>
            <h1>' . htmlspecialchars($title) . '</h1>
            <div>' . $message . '</div>
        </div>
        <script>
            setTimeout(function() {
                window.close();
            }, 5000);
        </script>
    </body>
    </html>';
}

// Основная логика
try {
    // Логируем полученные параметры для отладки
    $debugInfo = [
        'GET_parameters' => $_GET,
        'POST_parameters' => $_POST,
        'SERVER' => [
            'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? '',
            'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? ''
        ]
    ];

    // Проверяем наличие кода авторизации
    if (!isset($_GET['code'])) {
        $errorMessage = 'Код авторизации не получен в параметрах запроса.';
        $errorMessage .= '<br>Полученные параметры: ' . json_encode($_GET, JSON_UNESCAPED_UNICODE);
        throw new Exception($errorMessage);
    }

    //Получаем config 
    $config = getConfig();
    
    // Получаем код авторизации
    $code = $_GET['code'];
    
    // Получаем access token
    $tokenService = new TokenService($config);
    $accessToken = $tokenService->getToken($code);
    
    // Удаляем временные файлы
    if (file_exists(__DIR__ . '/auth_state.txt')) {
        unlink(__DIR__ . '/auth_state.txt');
    }
    
    // Показываем страницу успеха
    $message = '
        <p>✅ Авторизация успешно завершена!</p>
    ';
    
    showPage('Успешная авторизация', $message);
    
} catch (Exception $e) {
    // Показываем страницу ошибки с детальной информацией
    $debugInfo = [
        'GET_params' => $_GET,
        'config_exists' => file_exists(__DIR__ . '/config.json'),
        'state_file_exists' => file_exists(__DIR__ . '/auth_state.txt'),
        'error_message' => $e->getMessage()
    ];
    
    $message = '
        <p><strong>Ошибка авторизации:</strong></p>
        <div class="debug">' . htmlspecialchars($e->getMessage()) . '</div>
        <p>Пожалуйста, закройте это окно и попробуйте снова.</p>
        <p>Если ошибка повторяется, проверьте:</p>
        <ul style="text-align: left;">
            <li>Правильность Client ID и Client Secret в config.php</li>
            <li>Redirect URI в настройках приложения AmoCRM</li>
            <li>Что приложение активировано в AmoCRM</li>
            <li>Что Redirect URI точно совпадает с указанным в настройках</li>
        </ul>
        <div class="debug">
            <strong>Отладочная информация:</strong><br>
            Получен код: ' . (isset($_GET['code']) ? 'Да (' . substr($_GET['code'], 0, 10) . '...)' : 'Нет') . '<br>
            Получен state: ' . (isset($_GET['state']) ? 'Да (' . substr($_GET['state'], 0, 10) . '...)' : 'Нет') . '<br>
        </div>
    ';
    
    showPage('Ошибка авторизации', $message, true);
    
    // Логируем ошибку в файл
    error_log('AmoCRM Auth Error: ' . $e->getMessage() . ' | GET: ' . json_encode($_GET));
}