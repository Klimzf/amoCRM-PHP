<?php
// scripts/amocrm_auth.php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Services/TokenService.php';

class AmoCrmAuthHelper
{
    private $config;
    private $tokenService;
    
    public function __construct()
    {
        $this->config = getConfig();
        $this->tokenService = new TokenService($this->config);
    }
    
    public function getAuthUrl()
    {
        $params = [
            'client_id' => $this->config['amocrm']['client_id'],
            'state' => bin2hex(random_bytes(16)),
            'redirect_uri' => $this->config['amocrm']['redirect_uri'],
            'mode' => 'post_message'
        ];
        
        return "https://www.amocrm.ru/oauth?" . http_build_query($params);
    }
    
    public function checkExistingToken()
    {
        $tokenFile = $this->config['amocrm']['token_file'];
        
        if (file_exists($tokenFile)) {
            $tokenData = json_decode(file_get_contents($tokenFile), true);
            if ($tokenData && isset($tokenData['access_token'])) {
                $currentTime = time();
                if ($currentTime < $tokenData['expires_in']) {
                    return true;
                } 
            }
        }
        
        return false;
    }
}

// Проверяем, запущен ли скрипт из командной строки
if (php_sapi_name() !== 'cli') {
    die("❌ Этот скрипт должен запускаться только из командной строки\n");
}

// Создаем папку для логов если её нет
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0700, true);
}

$config = getConfig();
$authHelper = new AmoCrmAuthHelper();

echo "🔐 amoCRM Authorization Helper\n";
echo str_repeat("=", 50) . "\n";

// Проверяем существующий токен
if ($authHelper->checkExistingToken()) {
    echo "\n💡 Токен уже настроен. Хотите переавторизоваться? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim(strtolower($line)) !== 'y') {
        echo "Выход...\n";
        exit(0);
    }
}

// Получаем URL для авторизации
$authUrl = $authHelper->getAuthUrl();

echo "\n📋 Инструкция по авторизации:\n";
echo "1. Перейдите по ссылке: " . $authUrl . "\n";
echo "2. Авторизуйтесь в amoCRM\n";
echo "3. После авторизации вы будете перенаправлены\n";


