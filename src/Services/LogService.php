<?php

class LogService
{
    private function makeLogFile($path) {
        $fullPath = __DIR__ . $path;
        $dir = dirname($fullPath);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }  
    }
    public function logError($message, $path)
    {
        file_put_contents(__DIR__ . $path, date('Y-m-d H:i:s') . " - " . $message . PHP_EOL, FILE_APPEND);
    }
    
    public function logSuccess($message, $path)
    {
        file_put_contents(__DIR__ . $path, date('Y-m-d H:i:s') . " - " . $message . PHP_EOL, FILE_APPEND);
    }
    public function logInfo($message, $path)
    {
        file_put_contents(__DIR__ . $path, date('Y-m-d H:i:s') . " - INFO: " . $message . PHP_EOL, FILE_APPEND);
        echo $message . PHP_EOL;
    }
}