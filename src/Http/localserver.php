<?php
require_once __DIR__ . '/../../vendor/autoload.php';

class AmoCRMAuth
{
    private $clientId;
    private $clientSecret;
    private $redirectUri = 'http://localhost:8000/callback.php';
    private $tokenFile = 'token.json';
    private $serverPidFile = 'server.pid';
    private $configFile = 'config.json';
    private $isWindows;

    public function __construct()
    {
        $this->isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';    
    }

    public function startAuth()
    {
        echo "🚀 Запуск процесса авторизации AmoCRM...\n";
        echo "----------------------------------------\n";
        echo "Redirect URI: " . $this->redirectUri . "\n";
        echo "OS: " . ($this->isWindows ? 'Windows' : 'Unix') . "\n\n";

        if ($this->checkExistingToken()) {
            echo "✅ Токен уже существует и валиден\n";
            $this->stopServer();
            return true;
        }

        if (!$this->startLocalServer()) {
            echo "❌ Не удалось запустить локальный сервер\n";
            return false;
        }
        
        echo "⏳ Ожидаем авторизацию...\n";
        echo "Для отмены нажмите Ctrl+C\n\n";
        
        return $this->waitForAuthorization();
    }

    private function waitForAuthorization()
    {
        $timeout = 300;
        $startTime = time();
        
        while (time() - $startTime < $timeout) {
            if ($this->checkExistingToken()) {
                echo "✅ Авторизация успешно завершена!\n";
                $this->stopServer();
                return true;
            }
            
            sleep(2);
            
            if ((time() - $startTime) % 10 === 0) {
                $remaining = $timeout - (time() - $startTime);
                echo "⏰ Ожидание... (" . $remaining . " сек. осталось)\n";
            }
        }
        
        echo "❌ Время ожидания истекло\n";
        $this->stopServer();
        return false;
    }

    private function checkExistingToken()
    {
        if (!file_exists($this->tokenFile)) {
            return false;
        }else{
            return true;
        }
    }

    private function startLocalServer()
    {
        if ($this->isServerRunning()) {
            echo "📡 Локальный сервер уже запущен\n";
            return true;
        }

        echo "🖥️  Запускаем локальный сервер...\n";
        
        if ($this->isWindows) {
            // КОМАНДА ДЛЯ WINDOWS
            $command = 'php -S localhost:8000';
            pclose(popen($command, 'r'));
            
            // Даем время на запуск
            sleep(3);
            
            // Проверяем что сервер запустился
            if ($this->isServerRunning()) {
                echo "✅ Локальный сервер запущен\n";
                return true;
            } else {
                echo "❌ Не удалось запустить сервер автоматически\n";
                echo "💡 Попробуйте запустить сервер вручную в отдельном окне:\n";
                echo "   php -S localhost:8000\n\n";
                return false;
            }
        } else {
            // Команда для Unix
            $command = sprintf('php -S localhost:8000 -t "%s" > /dev/null 2>&1 & echo $!', __DIR__);
            $pid = shell_exec($command);
            
            if ($pid) {
                file_put_contents($this->serverPidFile, trim($pid));
                echo "✅ Локальный сервер запущен (PID: " . trim($pid) . ")\n";
                sleep(2);
                return true;
            }
        }
        
        return false;
    }

    private function isServerRunning()
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 3,
                    'ignore_errors' => true
                ]
            ]);
            $response = @file_get_contents($this->redirectUri, false, $context);
            return $response !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    public function stopServer()
    {
        if (!$this->isWindows) {
            // Останавливаем сервер только на Unix системах
            if (file_exists($this->serverPidFile)) {
                $pid = trim(file_get_contents($this->serverPidFile));
                if ($pid) {
                    exec("kill $pid 2>/dev/null");
                }
                unlink($this->serverPidFile);
                echo "🛑 Локальный сервер остановлен\n";
            }
        } else {
            echo "💡 На Windows сервер нужно остановить вручную (Ctrl+C в окне сервера)\n";
        }
        
        // Удаляем временные файлы
        if (file_exists('auth_state.txt')) {
            unlink('auth_state.txt');
        }
    }
}

// Запуск приложения
if (php_sapi_name() === 'cli') {
    $auth = new AmoCRMAuth();
    try {
        $auth->startAuth(); 
    } catch (Exception $e) {
        echo "\n❌ Критическая ошибка: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Этот скрипт предназначен только для запуска из командной строки\n";
}