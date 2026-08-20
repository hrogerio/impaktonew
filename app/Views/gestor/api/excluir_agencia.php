<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /gestor/relatorios#agencias");
    exit;
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    die("Token de segurança inválido.");
}

require_once __DIR__ . '/../../../../config/database.php';
$pdo = getDatabase();

$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT logo FROM agencias WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $pdo->prepare("DELETE FROM agencias WHERE id = ?")->execute([$id]);

    if ($row && !empty($row['logo'])) {
        $path = __DIR__ . '/../../../../' . $row['logo'];
        if (file_exists($path)) @unlink($path);
    }
}

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
    exit;
}

header("Location: /gestor/relatorios#agencias");
exit;
