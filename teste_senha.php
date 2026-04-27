<?php
// teste_senha.php - Execute uma vez e delete
$senha = '123456';
$hash = password_hash($senha, PASSWORD_DEFAULT);

echo "<h3>Informações da Senha:</h3>";
echo "Senha: " . $senha . "<br>";
echo "Hash: " . $hash . "<br>";

// Teste de verificação
if (password_verify($senha, $hash)) {
    echo "✅ Verificação: OK<br>";
} else {
    echo "❌ Verificação: FALHA<br>";
}

// SQL para atualizar
echo "<h3>SQL para Executar:</h3>";
echo "<pre>";
echo "UPDATE admins SET senha = '$hash' WHERE usuario = 'master';";
echo "</pre>";
?>
*/
