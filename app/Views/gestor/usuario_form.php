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

$paginaAtual = 'usuarios';

try {
    require_once __DIR__ . '/../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("Erro na conexão com o banco de dados.");
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editando = $id > 0;

$dados = ['usuario' => '', 'role' => 'operador'];
if ($editando) {
    $stmt = $pdo->prepare("SELECT id, usuario, role FROM admins WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        header("Location: /gestor/usuarios");
        exit;
    }
    $dados = $row;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $editando ? 'Editar' : 'Novo' ?> Usuário · Impakto</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="container" style="padding-bottom:2rem; max-width:520px;">

    <div class="welcome" style="margin-bottom:1.5rem;">
        <h2><?= $editando ? '✏️ Editar Usuário' : '➕ Novo Usuário' ?></h2>
    </div>

    <?php if (!empty($_GET['erro'])): ?>
        <div class="alerta alerta-err">❌ <?= htmlspecialchars($_GET['erro']) ?></div>
    <?php endif; ?>

    <form method="POST" action="/gestor/usuarios/salvar" class="table-container" style="padding:1.5rem;">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="id" value="<?= (int)$dados['id'] ?? 0 ?>">

        <div class="form-group" style="margin-bottom:1rem;">
            <label>Nome de usuário</label><br>
            <input type="text" name="usuario" required maxlength="50"
                   value="<?= htmlspecialchars($dados['usuario']) ?>"
                   <?= $editando ? 'readonly' : '' ?>
                   style="width:100%; padding:0.6rem; border:1px solid var(--color-border); border-radius:8px;">
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label>Papel</label><br>
            <select name="role" style="width:100%; padding:0.6rem; border:1px solid var(--color-border); border-radius:8px;">
                <option value="operador" <?= $dados['role'] === 'operador' ? 'selected' : '' ?>>Operador</option>
                <option value="admin" <?= $dados['role'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
            </select>
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label><?= $editando ? 'Nova senha (deixe em branco para manter a atual)' : 'Senha' ?></label><br>
            <input type="password" name="senha" <?= $editando ? '' : 'required' ?> minlength="10" autocomplete="new-password"
                   style="width:100%; padding:0.6rem; border:1px solid var(--color-border); border-radius:8px;">
            <small style="color:var(--color-text-muted);">Mínimo de 10 caracteres.</small>
        </div>

        <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
            <button type="submit" class="btn-backup btn-baixar">💾 Salvar</button>
            <a href="/gestor/usuarios" class="btn-backup" style="background:#f3f4f6; color:var(--color-text-dark);">Cancelar</a>
        </div>
    </form>

</div>

</body>
</html>
