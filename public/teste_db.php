<?php
require_once __DIR__ . '/../config/database.php';

echo "<h2>🔍 Verificação de Conexão</h2>";
echo "<strong>Host:</strong> " . $config['host'] . "<br>";
echo "<strong>Database:</strong> " . $config['db'] . "<br>";
echo "<strong>User:</strong> " . $config['user'] . "<br>";
echo "<strong>Ambiente:</strong> " . (isLocalEnvironment() ? '🏠 LOCAL' : '☁️ PRODUÇÃO') . "<br>";

try {
    $pdo = getDatabase();
    echo "<h3 style='color: green;'>✅ CONEXÃO OK!</h3>";
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ ERRO: " . $e->getMessage() . "</h3>";
}
?>