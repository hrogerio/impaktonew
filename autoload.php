<?php
// autoload.php
spl_autoload_register(function ($class) {
    $directories = [
        __DIR__ . '/app/Controllers/',
        __DIR__ . '/app/Models/', 
        __DIR__ . '/app/Services/',
        __DIR__ . '/app/Middleware/',
    ];
    
    foreach ($directories as $directory) {
        $file = $directory . $class . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// Carregar configurações essenciais
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/cache.php';