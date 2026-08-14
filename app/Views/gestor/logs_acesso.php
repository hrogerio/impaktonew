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

$paginaAtual = 'logs-acesso';

try {
    require_once __DIR__ . '/../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("Erro na conexão com o banco de dados.");
}

$filtroUsuario = trim($_GET['usuario'] ?? '');
$apenasFalhas  = isset($_GET['falhas']) && $_GET['falhas'] === '1';

$where  = [];
$params = [];
if ($filtroUsuario !== '') {
    $where[] = "usuario LIKE ?";
    $params[] = "%{$filtroUsuario}%";
}
if ($apenasFalhas) {
    $where[] = "sucesso = 0";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("SELECT usuario, sucesso, motivo, ip, user_agent, criado_em FROM login_logs {$whereSql} ORDER BY criado_em DESC LIMIT 300");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$motivos = [
    'ok'                => 'Login com sucesso',
    'senha_invalida'    => 'Senha incorreta',
    'usuario_invalido'  => 'Usuário não existe',
    'inativo'           => 'Usuário inativo',
    'conta_bloqueada'   => 'Conta bloqueada',
    'ip_bloqueado'      => 'IP bloqueado (excesso de tentativas)',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log de Acessos · Impakto</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="container" style="padding-bottom:2rem;">

    <div class="welcome" style="margin-bottom:1.5rem;">
        <h2>🛡️ Log de Acessos</h2>
        <p>Últimas 300 tentativas de login (sucesso e falha).</p>
    </div>

    <form method="GET" style="display:flex; gap:0.75rem; margin-bottom:1rem; flex-wrap:wrap; align-items:center;">
        <input type="text" name="usuario" placeholder="Filtrar por usuário" value="<?= htmlspecialchars($filtroUsuario) ?>"
               style="padding:0.5rem 0.75rem; border:1px solid var(--color-border); border-radius:8px;">
        <label style="display:flex; align-items:center; gap:0.4rem; font-size:0.85rem;">
            <input type="checkbox" name="falhas" value="1" <?= $apenasFalhas ? 'checked' : '' ?>> Apenas falhas
        </label>
        <button type="submit" class="btn-dl">Filtrar</button>
    </form>

    <div class="table-container">
        <table class="backup-table">
            <thead>
                <tr>
                    <th>Quando</th>
                    <th>Usuário</th>
                    <th>Resultado</th>
                    <th>IP</th>
                    <th>Navegador</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="5" class="backup-empty">Nenhum registro encontrado.</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $l): ?>
                <tr>
                    <td class="backup-size"><?= date('d/m/Y H:i:s', strtotime($l['criado_em'])) ?></td>
                    <td class="backup-nome"><?= htmlspecialchars($l['usuario']) ?></td>
                    <td><?= $l['sucesso'] ? '✅' : '❌' ?> <?= htmlspecialchars($motivos[$l['motivo']] ?? $l['motivo']) ?></td>
                    <td class="backup-size"><?= htmlspecialchars($l['ip'] ?? '—') ?></td>
                    <td class="backup-size" style="max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($l['user_agent'] ?? '') ?>">
                        <?= htmlspecialchars($l['user_agent'] ?? '—') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
