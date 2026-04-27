<?php
// config/database.php - Conexão apenas servidor remoto

$config = [
    'host' => 'ipk2024.mysql.uhserver.com',
    'db'   => 'ipk2024', 
    'user' => 'ipk',
    'pass' => 'Ipk@12647',
    'charset' => 'utf8mb4'
];

function getDatabase() {
    global $config;
    static $connection = null;
    
    if ($connection === null) {
        $dsn = "mysql:host={$config['host']};dbname={$config['db']};charset={$config['charset']}";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']}"
        ];
        
        try {
            $connection = new PDO($dsn, $config['user'], $config['pass'], $options);
        } catch (PDOException $e) {
            error_log("❌ ERRO DB: " . $e->getMessage());
            throw new Exception("Erro na conexão com o banco de dados");
        }
    }
    
    return $connection;
}

return $config;