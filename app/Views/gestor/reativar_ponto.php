<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /gestor/pontos");
    exit;
}
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    die("Token de segurança inválido.");
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    header("Location: /gestor/pontos");
    exit;
}

require_once __DIR__ . '/../../../config/database.php';
$pdo = getDatabase();

$pdo->prepare("UPDATE pontos SET ativo = 1 WHERE id = ?")->execute([$id]);

header("Location: /gestor/pontos/editar?id=" . $id . "&msg=reativado");
exit;
