<?php
// config/database.php — compatível com PHP 7.x
// Credenciais lidas do .env — NUNCA coloque senhas diretamente aqui

function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Ignora comentários e linhas sem "="
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        if (!array_key_exists($key, $_ENV)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// .env fica na mesma pasta (web/)
loadEnv(__DIR__ . '/../.env');   // tenta um nível acima (caso config/ esteja dentro de web/)
loadEnv(__DIR__ . '/../../.env'); // dois níveis acima
// Fallback: web/.env direto
if (!getenv('DB_HOST')) {
    loadEnv(dirname(__DIR__) . '/.env');
}

$config = array(
    'host'    => getenv('DB_HOST') ? getenv('DB_HOST') : 'localhost',
    'db'      => getenv('DB_NAME') ? getenv('DB_NAME') : '',
    'user'    => getenv('DB_USER') ? getenv('DB_USER') : '',
    'pass'    => getenv('DB_PASS') ? getenv('DB_PASS') : '',
    'charset' => 'utf8mb4',
);

function getDatabase() {
    global $config;
    static $connection = null;
    if ($connection === null) {
        $dsn = "mysql:host={$config['host']};dbname={$config['db']};charset={$config['charset']}";
        $options = array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']}",
        );
        try {
            $connection = new PDO($dsn, $config['user'], $config['pass'], $options);
        } catch (PDOException $e) {
            error_log('ERRO DB: ' . $e->getMessage());
            throw new Exception('Erro na conexão com o banco de dados.');
        }
    }
    return $connection;
}

return $config;