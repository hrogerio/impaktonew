<?php
// ============================================
// SOLUÇÃO 1: Configurar base URL correta
// Criar arquivo: config/app.php
// ============================================

// Detectar a base URL automaticamente
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    
    // Extrair o diretório base
    $basePath = str_replace('/gestor/index.php', '', $scriptName);
    $basePath = str_replace('/public/index.php', '', $basePath);
    $basePath = str_replace('/index.php', '', $basePath);
    
    return $protocol . $host . $basePath;
}

define('BASE_URL', getBaseUrl());
define('GESTOR_URL', BASE_URL . '/gestor');
define('PUBLIC_URL', BASE_URL . '/public');
define('ASSETS_URL', BASE_URL . '/public/assets');
