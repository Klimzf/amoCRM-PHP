<?php

use phpseclib3\Math\BinaryField\Integer;
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Services/GoogleSheetsService.php';
require_once __DIR__ . '/../src/Services/AmoCrmService.php';
require_once __DIR__ . '/../src/Models/Contact.php';
require_once __DIR__ . '/../src/Validators/DataValidator.php';
require_once __DIR__ . '/../src/Services/LogService.php';

class SyncService
{
    private $config;
    private $googleSheetsService;
    private $amoCrmService;
    private $validator;
    private $logger;
    
    public function __construct()
    {
        $this->config = getConfig();
        $this->validator = new DataValidator();
        $this->setupServices();
        $this->logger = new LogService();
    }
    
    private function setupServices()
    {
        try {
            //Создаем объект GoogleSheets
            $this->googleSheetsService = new GoogleSheetsService($this->config);
            
            // Настраиваем amoCRM
            $this->amoCrmService = new AmoCrmService($this->config);
            
        } catch (Exception $e) {
            $this->logger->logError("Setup Error: " . $e->getMessage(), '/../../logs/error.log');
            throw $e;
        }
    }
    
    public function sync()
    {
        try {
            $this->logger->logInfo("Начало синхронизации", '/../../logs/info.log');
            
            // Получаем данные из Google Sheets
            $contacts = $this->googleSheetsService->getRows();
            $this->logger->logInfo("Найдено контактов для добавления: " .count($contacts['contacts']), '/../../logs/info.log');
            $this->logger->logInfo("Найдено контактов с неполными данными: " . $contacts['failedContacts'], '/../../logs/info.log');
            
            $processed = 0;
            $errors = 0;
            
            foreach ($contacts['contacts'] as $contact) {
                // Валидируем данные
                $validation = $this->validator->validateContact($contact);
                
                if (!$validation['is_valid']) {
                    $this->logger->logError("Ошибки валидации для {$contact->name}: " . implode(', ', $validation['errors']), '/../../logs/error.log');
                    $errors++;
                    continue;
                }
                
                // Создаем сделку в amoCRM
                if ($this->amoCrmService->createLead($contact)) {
                    $processed++;
                } else {
                    $errors++;
                }
            }
            
            $this->logger->logInfo("Синхронизация завершена. Обработано: {$processed}, Ошибок: {$errors}", '/../../logs/info.log');
            
        } catch (Exception $e) {
            $this->logger->logError("Sync Error: " . $e->getMessage(), '/../../logs/error.log');
        }
    }
    
    private function shouldSync()
    {
        if (!file_exists($this->config['sync']['last_sync_file'])) {
            return true;
        }
        
        $lastSync = file_get_contents($this->config['sync']['last_sync_file']);
        $currentTime = time();
        
        return ($currentTime - $lastSync) >= $this->config['sync']['check_interval'];
    }
    
    public function run()
    {
        if ($this->shouldSync()) {
            $this->sync();
        } else {
            $this->logger->logInfo("Синхронизация не требуется". PHP_EOL, '/../../logs/info.log');
        }
    }
}
$syncService = new SyncService();
$config = getConfig();
if($argv[1] == 'windows'){
    while(true){     
        $startTime = microtime(true);
        echo "\n--- Запустилось в " . date('Y-m-d H:i:s') . " ---\n"; 
        $syncService->run();
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        echo "Завершилось в " . number_format($duration, 2) . " seconds.\n";

        // Ждем 10 секунд минус время выполнения (если оно было меньше 10)
        $sleepTime = (int)max(0, $config['amocrm']['refresh_script'] - $duration);
        echo "Приостановка на {$sleepTime} секунд...\n";
        sleep($sleepTime);
    }
}elseif($argv == 'linux'){
    $syncService->run();
}