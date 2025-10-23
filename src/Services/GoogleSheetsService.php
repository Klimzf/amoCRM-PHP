<?php

use Google\Client;
use Google\Service\Sheets;
require_once __DIR__ . '/../Models/Contact.php';
require_once __DIR__ . '/LogService.php';

class GoogleSheetsService
{
    private $service;
    private $spreadsheetId;
    private $range;
    private $logger;

    public function __construct($config)
    {
        $this->spreadsheetId = $config['google']['spreadsheet_id'];
        $this->range = $config['google']['range'];

        $client = new Client();
        $client->setAuthConfig($config['google']['credentials_file']);
        $client->addScope(Sheets::SPREADSHEETS_READONLY);

        $this->service = new Sheets($client);
        $this->logger = new LogService();
    }

    public function getRows()
    {
        try {
            $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $this->range);
            $values = $response->getValues();

            if (empty($values)) {
                return [];
            }
            $contacts = [];
            $failedContacts = 0;
            foreach ($values as $index => $row) {
                $name = isset($row[0]) ? trim($row[0]) : '';
                $phone = isset($row[1]) ? trim($row[1]) : '';
                $email = isset($row[2]) ? trim($row[2]) : '';
                $budget = isset($row[3]) ? trim($row[3]) : '';

                if($name && $phone && $email && $budget){
                    $contact = new Contact($name, $phone, $email, $budget);
                }else{
                    $failedContacts++;
                    $this->logger->logError("Не хватает данных для пользователя под номером: " . $index, '/../../logs/error.log');
                    continue;
                }

                if($contact->isValid()){
                    $contacts['contacts'][] = $contact;
                }                  
            }
            $contacts['failedContacts'] = $failedContacts;
            return $contacts;
        } catch (Exception $e) {
            error_log('Error fetching data from Google Sheets: ' . $e->getMessage());
            throw $e;
        }
    }
}