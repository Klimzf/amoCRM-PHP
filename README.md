# Документация по интеграции AmoCRM + Google Sheets

## API и сервисы:
- **AmoCRM REST API** - интеграция с CRM системой
- **Google Sheets API v4** - работа с Google таблицами

## Требования к системе
**Обязательно:**
- PHP 7.4 или выше
- Composer 2.0 или выше
- Расширение cURL
- Расширение JSON

**Желательно:**
- PHP 8.0+ для лучшей производительности
- Composer 2.5+ для совместимости

## Структура проекта
config/ # Конфигурационные файлы
├── amocrm.json # Настройки AmoCRM
├── config.php # Основной конфиг
├── credentials.json # Google API credentials

logs/ # Логи приложения
├── error.log
├── info.log
└── sync.log

scripts/ # Исполняемые скрипты
├── amocrm_auth.php # Авторизация в AmoCRM
└── sync.php # Основной скрипт синхронизации

src/ # Исходный код
├── Http/ # HTTP-компоненты
│ ├── callback.php # Callback для OAuth
│ └── localserver.php # Локальный сервер для авторизации
├── Models/ # Модели данных
│ └── Contact.php # Модель контакта
├── Services/ # Бизнес-логика
│ ├── AmocrmService.php # Работа с AmoCRM API
│ ├── GoogleSheetsService.php # Работа с Google Sheets API
│ ├── LogService.php # Логирование
│ └── TokenService.php # Управление токенами
└── Validators/ # Валидация
└── DataValidator.php

## Основные функции scs/Services

#### AmoCrmService.php

1) Создает сделку, после проверки на дубликаты и создание контакта. 
- **Параметры:** `contact` - объект контакта
- **Возвращает:** `true` или `false`
```php
public function createLead($contact)
```

2) Создает контакт
- **Параметры:** `contact` - объект контакта, `accessToken` - токен для работы с API amoCRM (string)
- **Возвращает:** `ID` созданного контакта 
```php
private function createContact($contact, $accessToken)
```

3) Создает сделку для контакта
- **Параметры:** `contact` - объект контакта, `contactId` - ID контакта (int), `accessToken` - токен для работы с API amoCRM (string)
- **Возвращает:** `ID` созданной сделки
```php
private function createLeadForContact($contact, $contactId, $accessToken)
```

4) Проверка на дубликаты
- **Параметры:** `email` - email контакта (string), `phone` - телефон контакта (string), `accessToken` - токен для работы с API amoCRM (string)
- **Возвращает:** `true` или `false`
```php
private function contactExists($email, $phone, $accessToken)
```

5) Приводит формат телефона к виду +7...
- **Параметры:** `phone` - телефон контакта (string)
- **Возвращает:** `string`
```php
private function formatPhone($phone)
```

6) Выполнение http запросов к API 
- **Параметры:** `endpoint` - путь API метода (string), `data` - данные для отправки (array), `accessToken` - токен для работы с API amoCRM (string), `method` - HTTP метод (string)
- **Возвращает:** `string`
```php
private function makeRequest($endpoint, $data = [], $accessToken = null, $method = 'POST')
```

#### GoogleSheetsService.php

1) Получение данных с Google Sheets
- **Возвращает:** `array`
```php
public function getRows()
```

#### AmoCrmService.php

1) Метод для получения токена (обновление или использование существующего) 
- **Возвращает:** `access_token`
```php
public function getToken($code = null)
```

2) Метод для обмена authorization code на токены
- **Параметры:** `code` - код для получения access_token (string)
- **Возвращает:** `access_token`
```php
private function requestToken($code)
```

3) Метод для обновления токена через refresh_token
- **Параметры:** `refreshToken` - токен для получения нового токена (string)
- **Возвращает:** `access_token`
```php
private function refreshToken($refreshToken)
```

4) Общий метод для выполнения запросов получения/обновления токена
- **Параметры:** `url` - путь к API, `data` - данные для запроса
- **Возвращает:** `access_token`
```php
private function makeTokenRequest($url, $data)
```

5) Метод для загрузки токена из файла
- **Возвращает:** `array` или `null`
```php
private function loadToken()
```

6) Метод для сохранения токена в файл
- **Параметры:** `tokenData` - Все данные для работы с API amoCRM
```php
private function saveToken($tokenData)
```

## Запуск интеграции
**Для первого запуска, если нет amocrm.json**
1) Запускаем локальный сервер. Обязательно перейти в папку скрипта localserver.php!
```bash
# Находимся в корне проекта http_amocrm
cd src
cd Http
php localserver.php
```
2) Запускаем скрипт для полученя URL аунтентификации
```bash
# Находимся в корне проекта http_amocrm
php .\scripts\amocrm_auth.php
```
Переходим по ссылке и проходим регистрацию. Далее будет переодресация на callback.php. На странице отобразится статус получения токена. 

**Конец**

1) Основные шаги (Обязательно при вызове скрипта передавать параметр)
Параметры: 
- windows
- linux

**Запустить синхронизацию на Windows**
```bash
# Находимся в корне проекта http_amocrm
php .\scripts\sync.php windows
```
каждые 10 секунд будет выполняться. Чтобы поменять частоту работы, изменить поле refresh_script в config/config.php

**Запустить синхронизацию на Linux**
- Разово
```bash
# Находимся в корне проекта http_amocrm
php .\scripts\sync.php linux
```
- Регулярно
```bash
# Узнаем путь к PHP
which php # Обычно: /usr/bin/php или /usr/local/bin/php

#Далее в зависимости от ответа составляем команду в crontab
sudo crontab -e
#Команда
* * * * * /usr/bin/php -f /путь/к/http_amocrm/scripts/sync.php linux >/dev/null 2>&1
```

