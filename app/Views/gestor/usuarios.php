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

$usuarios = $pdo->query("SELECT id, usuario, ativo, role, failed_attempts, locked_until, ultimo_login, created_at,
                                 (locked_until IS NOT NULL AND locked_until > NOW()) AS bloqueado
                          FROM admins ORDER BY usuario ASC")->fetchAll(PDO::FETCH_ASSOC);

$mensagem = '';
if (isset($_GET['msg'])) {
    $mapa = [
        'criado'      => 'Usuário criado com sucesso.',
        'atualizado'  => 'Usuário atualizado com sucesso.',
        'ativado'     => 'Usuário ativado.',
        'desativado'  => 'Usuário desativado.',
        'desbloqueado'=> 'Bloqueio removido.',
    ];
    $mensagem = $mapa[$_GET['msg']] ?? '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários · SGI</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="container" style="padding-bottom:2rem;">

    <div class="welcome" style="margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
        <div>
            <h2>👤 Usuários do Sistema</h2>
            <p>Gerencie quem tem acesso ao gestor.</p>
        </div>
        <a href="/gestor/usuarios/novo" class="btn-backup btn-baixar">➕ Novo Usuário</a>
    </div>

    <?php if ($mensagem): ?>
        <div class="alerta alerta-ok">✅ <?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <div class="table-container">
        <table class="backup-table">
            <thead>
                <tr>
                    <th>Usuário</th>
                    <th>Papel</th>
                    <th>Status</th>
                    <th>Último login</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
                <?php $bloqueado = (bool)$u['bloqueado']; ?>
                <tr>
                    <td class="backup-nome"><?= htmlspecialchars($u['usuario']) ?></td>
                    <td><?= $u['role'] === 'admin' ? 'Administrador' : 'Operador' ?></td>
                    <td>
                        <?php if ($bloqueado): ?>
                            🔒 Bloqueado até <?= date('d/m H:i', strtotime($u['locked_until'])) ?>
                        <?php elseif (!$u['ativo']): ?>
                            ⛔ Inativo
                        <?php else: ?>
                            ✅ Ativo
                        <?php endif; ?>
                    </td>
                    <td class="backup-size"><?= $u['ultimo_login'] ? date('d/m/Y H:i', strtotime($u['ultimo_login'])) : '—' ?></td>
                    <td style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                        <a href="/gestor/usuarios/editar?id=<?= (int)$u['id'] ?>" class="btn-dl">✏️ Editar</a>
                        <?php if ($u['usuario'] !== $_SESSION['usuario']): ?>
                        <form method="POST" action="/gestor/usuarios/status" style="margin:0;"
                              onsubmit="return confirm('<?= $u['ativo'] ? 'Desativar' : 'Ativar' ?> o usuário <?= htmlspecialchars($u['usuario']) ?>?')">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="acao" value="<?= $u['ativo'] ? 'desativar' : 'ativar' ?>">
                            <button type="submit" class="btn-del"><?= $u['ativo'] ? '⛔ Desativar' : '✅ Ativar' ?></button>
                        </form>
                        <?php endif; ?>
                        <?php if ($bloqueado): ?>
                        <form method="POST" action="/gestor/usuarios/status" style="margin:0;"
                              onsubmit="return confirm('Remover bloqueio de <?= htmlspecialchars($u['usuario']) ?>?')">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="acao" value="desbloquear">
                            <button type="submit" class="btn-dl">🔓 Desbloquear</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
