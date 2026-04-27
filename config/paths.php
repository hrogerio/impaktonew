<?php
// config/paths.php - Configurações de caminhos

// Base paths
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('CONFIG_PATH', BASE_PATH . '/config');
define('STORAGE_PATH', BASE_PATH . '/storage');

// URL paths (ajustar conforme seu domínio)
define('BASE_URL', '/');
define('ASSETS_URL', BASE_URL . 'public/assets');
define('GESTOR_URL', BASE_URL . 'gestor');

// Função para gerar URLs corretas
function url($path = '') {
    return BASE_URL . ltrim($path, '/');
}

function asset($path) {
    return ASSETS_URL . '/' . ltrim($path, '/');
}

function gestor_url($path = '') {
    return GESTOR_URL . '/' . ltrim($path, '/');
}
?>