<?php

require_once __DIR__ . '/TokenService.php';
require_once __DIR__ . '/LogService.php';

class AmoCrmService
{
    private $config;
    private $baseUrl;
    private $auth;
    private $tokenHelper;
    private $logger;
    
    public function __construct($config, $auth = null)
    {
        $this->config = $config;
        $this->baseUrl = "https://{$config['amocrm']['subdomain']}.amocrm.ru";
        $this->auth = $auth;
        $this->tokenHelper = new TokenService($config);
        $this->logger = new LogService();
    }
    
    public function createLead($contact)
    {
        try {
            // Получаем актуальный access token
            $accessToken = $this->tokenHelper->getToken();
            
            // Проверяем дубликаты по email и телефону
            if ($this->contactExists($contact->email, $contact->phone, $accessToken)) {
                $this->logger->logError("Контакт с email {$contact->email} или телефоном {$contact->phone} уже существует", '/../../logs/error.log');
                return false;
            }
            
            // Сначала создаем контакт
            $contactId = $this->createContact($contact, $accessToken);
            
            if (!$contactId) {
                throw new Exception("Не удалось создать контакт");
            }
            
            // Затем создаем сделку
            $leadId = $this->createLeadForContact($contact, $contactId, $accessToken);
            
            if ($leadId) {
                $this->logger->logSuccess("Успешно создана сделка ID: {$leadId} для контакта: {$contact->name}", '/../../logs/success.log');
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logger->logError("AmoCRM Lead Creation Error: " . $e->getMessage(), '/../../logs/error.log');
            return false;
        }
    }
    
    private function createContact($contact, $accessToken)
    {
        $contactData = [
            [
                "name" => $contact->name,
                "custom_fields_values" => [
                    [
                        "field_code" => "EMAIL",
                        "values" => [
                            [
                                "enum_code" => "WORK",
                                "value" => $contact->email
                            ]
                        ]
                    ],
                    [
                        "field_code" => "PHONE",
                        "values" => [
                            [
                                "enum_code" => "WORK",
                                "value" => $this->formatPhone($contact->phone)
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $response = $this->makeRequest('/api/v4/contacts', $contactData, $accessToken);
        return $response['_embedded']['contacts'][0]['id'];
    }
    
    private function createLeadForContact($contact, $contactId, $accessToken)
    {
        $leadData = [
            [
                "name" => "Заявка от " . $contact->name,
                "price" => (int)preg_replace('/[^0-9]/', '', $contact->budget),
                "pipeline_id" => (int)$this->config['amocrm']['pipeline_id'],
                "status_id" => (int)$this->config['amocrm']['stage_id'],
                "_embedded" => [
                    "contacts" => [
                        [
                            "id" => (int)$contactId
                        ]
                    ]
                ]
            ]
        ];
        
        $response = $this->makeRequest('/api/v4/leads', $leadData, $accessToken);
        return $response['_embedded']['leads'][0]['id'];
    }
    
    private function contactExists($email, $phone, $accessToken)
    {
        // Поиск по email
        $emailQuery = urlencode($email);
        $emailResponse = $this->makeRequest("/api/v4/contacts?query={$emailQuery}", [], $accessToken, 'GET');
        
        if (!empty($emailResponse['_embedded']['contacts'])) {
            return true;
        }
        
        // Поиск по телефону
        $phoneQuery = urlencode($this->formatPhone($phone));
        $phoneResponse = $this->makeRequest("/api/v4/contacts?query={$phoneQuery}", [], $accessToken, 'GET');
        
        return !empty($phoneResponse['_embedded']['contacts']);
    }
    
    private function formatPhone($phone)
    {
        // Очищаем номер от всего, кроме цифр
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        
        // Если номер начинается с 8, заменяем на +7
        if (substr($cleaned, 0, 1) === '8') {
            $cleaned = '7' . substr($cleaned, 1);
        }
        
        // Добавляем + если его нет
        if (substr($cleaned, 0, 1) !== '7') {
            $cleaned = '7' . $cleaned;
        }
        
        return '+' . $cleaned;
    }
    
    
    private function makeRequest($endpoint, $data = [], $accessToken = null, $method = 'POST')
    {
        if (!$accessToken) {
            $accessToken = $this->tokenHelper->getToken();
        }
        
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'User-Agent: amoCRM-API-client/1.0'
            ]
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            throw new Exception("cURL Error: " . curl_error($ch));
        }
        
        curl_close($ch);
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode === 401) {
            throw new Exception("Access token expired or invalid");
        }
        
        if ($httpCode >= 400) {
            throw new Exception("HTTP Error: {$httpCode} - " . json_encode($decodedResponse));
        }
        
        return $decodedResponse;
    }
    
}