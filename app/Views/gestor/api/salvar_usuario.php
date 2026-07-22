<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}
if (($_SESSION['usuario_role'] ?? 'admin') !== 'admin') {
    http_response_code(403);
    die("Acesso restrito.");
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /gestor/usuarios");
    exit;
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    die("Token de segurança inválido.");
}

require_once __DIR__ . '/../../../../config/database.php';
$pdo = getDatabase();

$id      = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$usuario = trim($_POST['usuario'] ?? '');
$role    = ($_POST['role'] ?? 'operador') === 'admin' ? 'admin' : 'operador';
$senha   = $_POST['senha'] ?? '';

function voltarComErro($msg, $id) {
    $url = $id > 0 ? "/gestor/usuarios/editar?id={$id}" : "/gestor/usuarios/novo";
    header("Location: {$url}&erro=" . urlencode($msg));
    exit;
}

if ($id === 0) {
    // Criação
    if (empty($usuario) || strlen($usuario) < 3) {
        voltarComErro("Usuário deve ter ao menos 3 caracteres.", 0);
    }
    if (strlen($senha) < 10) {
        voltarComErro("A senha deve ter ao menos 10 caracteres.", 0);
    }

    $existe = $pdo->prepare("SELECT id FROM admins WHERE usuario = ? LIMIT 1");
    $existe->execute([$usuario]);
    if ($existe->fetch()) {
        voltarComErro("Já existe um usuário com esse nome.", 0);
    }

    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO admins (usuario, senha, role, ativo) VALUES (?, ?, ?, 1)")
        ->execute([$usuario, $hash, $role]);

    header("Location: /gestor/usuarios?msg=criado");
    exit;
} else {
    // Edição
    $stmt = $pdo->prepare("SELECT id FROM admins WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        header("Location: /gestor/usuarios");
        exit;
    }

    if ($senha !== '') {
        if (strlen($senha) < 10) {
            voltarComErro("A senha deve ter ao menos 10 caracteres.", $id);
        }
        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE admins SET role = ?, senha = ? WHERE id = ?")
            ->execute([$role, $hash, $id]);
    } else {
        $pdo->prepare("UPDATE admins SET role = ? WHERE id = ?")
            ->execute([$role, $id]);
    }

    header("Location: /gestor/usuarios?msg=atualizado");
    exit;
}
