<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /gestor/clientes");
    exit;
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    die("Token de segurança inválido.");
}

require_once __DIR__ . '/../../../../config/database.php';
$pdo = getDatabase();

$id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$acao = $_POST['acao'] ?? '';

$stmt = $pdo->prepare("SELECT id FROM clientes WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$alvo = $stmt->fetch(PDO::FETCH_ASSOC);

if ($alvo) {
    if ($acao === 'ativar') {
        $pdo->prepare("UPDATE clientes SET ativo = 1 WHERE id = ?")->execute([$id]);
        header("Location: /gestor/clientes?msg=ativado");
        exit;
    }
    if ($acao === 'desativar') {
        $pdo->prepare("UPDATE clientes SET ativo = 0 WHERE id = ?")->execute([$id]);
        header("Location: /gestor/clientes?msg=desativado");
        exit;
    }
}

header("Location: /gestor/clientes");
exit;
