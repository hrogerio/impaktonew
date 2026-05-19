<?php
// web/ponto.php — URL curta para acesso público ao detalhe do ponto
// Uso: https://impaktomidia.com.br/ponto.php?n=225
// Redireciona para detalhes_ponto.php com view=publico

$numero = isset($_GET['n']) ? (int)$_GET['n'] : 0;

if (!$numero) {
    http_response_code(404);
    die('Ponto não encontrado.');
}

try {
    require_once __DIR__ . '/config/database.php';
    $pdo = getDatabase();
    $stmt = $pdo->prepare("SELECT id FROM pontos WHERE numero = ? AND (ativo = 1 OR ativo IS NULL) LIMIT 1");
    $stmt->execute([$numero]);
    $ponto = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    http_response_code(500);
    die('Erro interno.');
}

if (!$ponto) {
    http_response_code(404);
    die('Ponto não encontrado.');
}

header("Location: /gestor/pontos/detalhes?id=" . $ponto['id'] . "&view=publico");
exit;