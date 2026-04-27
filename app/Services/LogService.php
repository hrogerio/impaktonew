<?php
// app/Services/LogService.php

class LogService {
    private $logDir;
    
    public function __construct($logDir = null) {
        $this->logDir = $logDir ?: __DIR__ . '/../../storage/logs';
        $this->ensureLogDir();
    }
    
    private function ensureLogDir() {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }
    
    public function log($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $filename = $this->logDir . '/' . date('Y-m-d') . '.log';
        
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        $logLine = json_encode($logEntry) . PHP_EOL;
        
        return file_put_contents($filename, $logLine, FILE_APPEND | LOCK_EX) !== false;
    }
    
    public function info($message, $context = []) {
        return $this->log('info', $message, $context);
    }
    
    public function error($message, $context = []) {
        return $this->log('error', $message, $context);
    }
    
    public function warning($message, $context = []) {
        return $this->log('warning', $message, $context);
    }
    
    public function debug($message, $context = []) {
        if ($_ENV['APP_ENV'] === 'development') {
            return $this->log('debug', $message, $context);
        }
        return true;
    }
}