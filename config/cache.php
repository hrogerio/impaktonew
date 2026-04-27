<?php
// config/cache.php
class SimpleCache {
    private $cacheDir;
    
    public function __construct() {
        $this->cacheDir = __DIR__ . '/../storage/cache/';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    public function get($key, $default = null) {
        $filename = $this->cacheDir . md5($key) . '.cache';
        
        if (!file_exists($filename)) {
            return $default;
        }
        
        $data = unserialize(file_get_contents($filename));
        
        if ($data['expires'] < time()) {
            unlink($filename);
            return $default;
        }
        
        return $data['value'];
    }
    
    public function set($key, $value, $ttl = 3600) {
        $filename = $this->cacheDir . md5($key) . '.cache';
        
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        
        return file_put_contents($filename, serialize($data)) !== false;
    }
    
    public function remember($key, $callback, $ttl = 3600) {
        $value = $this->get($key);
        
        if ($value === null) {
            $value = $callback();
            $this->set($key, $value, $ttl);
        }
        
        return $value;
    }
}

// Função global para facilitar uso
function cache() {
    static $cache = null;
    if ($cache === null) {
        $cache = new SimpleCache();
    }
    return $cache;
}