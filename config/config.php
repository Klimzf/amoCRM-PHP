<?php
function getConfig(){
    return [
        'google' => [
            'spreadsheet_id' => '1H7St3Wh720ExCc1XOVc1gZmgZuiKtYbISF7cqsU49Z4',
            'range' => 'Лист1!A:D',
            'credentials_file' => __DIR__ . '/../config/credentials.json',
            'token_file' => __DIR__ . '/../config/token.json'
        ],
        'amocrm' => [
            'subdomain' => 'kushnarevwork',
            'client_id' => '71f6e880-2c71-4c79-9a05-f24f5eb4c332',
            'client_secret' => 'i9wvKCCLMYIUFHolrJNF6tNGErBb0BhsqYV4DV2hS4YhXNL6Hmkp1jUFbvCXpw8V',
            'redirect_uri' => 'http://localhost:8000/callback.php',
            'token_file' => __DIR__ . '\amocrm.json',
            'pipeline_id' => '10214858',
            'stage_id' => '80870302',
            'refresh_script' => 10
        ],
        'sync' => [
            'check_interval' => 5,
            'last_sync_file' => __DIR__ . '/../logs/last_sync.txt'
        ]
    ];
}