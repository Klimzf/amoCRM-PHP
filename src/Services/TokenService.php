<?php

class TokenService
{
    private $config;
    private $tokenFilePath;

    public function __construct($config)
    {
        $this->config = [
            'subdomain' => $config['amocrm']['subdomain'],
            'client_id' => $config['amocrm']['client_id'],
            'client_secret' => $config['amocrm']['client_secret'],
            'redirect_uri' => $config['amocrm']['redirect_uri'],
        ];
        $this->tokenFilePath = $config['amocrm']['token_file'];
    }

    // Метод для получения токена (обновление или использование существующего)
    public function getToken($code = null) // Добавляем параметр code
    {
        $tokenData = $this->loadToken();
        $currentTime = time();

        // Проверяем, истек ли access_token
        if (!$tokenData || !isset($tokenData['expires_in']) || $currentTime >= $tokenData['expires_in']) {
            if (isset($tokenData['refresh_token'])) {
                return $this->refreshToken($tokenData['refresh_token']);
            } elseif ($code) { // Если токена нет, но передан code
                return $this->requestToken($code); // Вызываем обмен code на токен
            } else {
                throw new Exception('No valid token available and no authorization code provided.');
            }
        }

        return $tokenData['access_token'];
    }

    // Метод для обмена authorization code на токены
    private function requestToken($code)
    {
        $url = 'https://' . $this->config['subdomain'] . '.amocrm.ru/oauth2/access_token';
        $data = [
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->config['redirect_uri'],
        ];

        return $this->makeTokenRequest($url, $data);
    }

    // Метод для обновления токена через refresh_token
    private function refreshToken($refreshToken)
    {
        $url = 'https://' . $this->config['subdomain'] . '.amocrm.ru/oauth2/access_token';
        $data = [
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'redirect_uri' => $this->config['redirect_uri'],
        ];

        return $this->makeTokenRequest($url, $data);
    }

    // Общий метод для выполнения запросов получения/обновления токена
    private function makeTokenRequest($url, $data)
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: AmoCRM-oAuth-client/1.0'
            ],
            CURLOPT_SSL_VERIFYPEER => true, // Убедитесь, что SSL настроен правильно
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl); // Получаем ошибку cURL, если есть
        curl_close($curl);

        if ($curlError) {
            error_log("cURL Error during token request: " . $curlError);
            throw new Exception("cURL Error: " . $curlError);
        }

        if ($httpCode !== 200 && $httpCode !== 204) {
            $errorDetails = $response ? $response : 'No response body';
            error_log("AmoCRM Token Request Failed. HTTP Code: $httpCode. Response: $errorDetails");
            throw new Exception("Failed to get token from AmoCRM API. HTTP Code: $httpCode. Details: $errorDetails");
        }

        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Failed to decode AmoCRM token response JSON: $response");
            throw new Exception("Failed to decode AmoCRM token response JSON.");
        }

        $tokenData = [
            'access_token' => $responseData['access_token'],
            'refresh_token' => $responseData['refresh_token'],
            'token_type' => $responseData['token_type'],
            'expires_in' => time() + $responseData['expires_in']
        ];

        $this->saveToken($tokenData);
        return $tokenData['access_token'];
    }

    // Метод для загрузки токена из файла
    private function loadToken()
    {
        if (file_exists($this->tokenFilePath)) {
            $content = file_get_contents($this->tokenFilePath);
            if ($content) {
                $data = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $data;
                } else {
                    error_log('Failed to decode stored token JSON.');
                }
            }
        }
        return null;
    }

    // Метод для сохранения токена в файл
    private function saveToken($tokenData)
    {
        $dir = dirname($this->tokenFilePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($this->tokenFilePath, json_encode($tokenData, JSON_PRETTY_PRINT));
    }
}