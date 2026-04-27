// ============================================
// SCRIPT DE VERIFICAÇÃO RÁPIDA
// ============================================
/* 
Crie também um arquivo teste_db.php para verificar:

<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=ipk2024;charset=utf8", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "<h3>✅ Conexão OK</h3>";
    
    // Verificar tabela admins
    $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
    $count = $stmt->fetchColumn();
    echo "Total de admins: $count<br>";
    
    // Listar usuários
    $stmt = $pdo->query("SELECT id, usuario, ativo, LEFT(senha, 20) as senha_inicio FROM admins");
    $users = $stmt->fetchAll();
    
    echo "<h4>Usuários cadastrados:</h4>";
    foreach ($users as $user) {
        echo "ID: {$user['id']}, Usuário: {$user['usuario']}, Ativo: {$user['ativo']}, Hash: {$user['senha_inicio']}...<br>";
    }
    
    // Teste de senha específico
    $stmt = $pdo->prepare("SELECT senha FROM admins WHERE usuario = ?");
    $stmt->execute(['master']);
    $hash = $stmt->fetchColumn();
    
    if ($hash) {
        $teste = password_verify('123456', $hash);
        echo "<h4>Teste de senha para 'master':</h4>";
        echo "Hash: " . substr($hash, 0, 30) . "...<br>";
        echo "Verificação com '123456': " . ($teste ? "✅ OK" : "❌ FALHA") . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>
*/
?>