<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}
$paginaAtual = 'pontos-inativos';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

try {
    require_once __DIR__ . '/../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("Erro na conexão com o banco de dados.");
}

$pontos = $pdo->query("
    SELECT id, numero, logradouro, cidade, regiao, situacao
    FROM pontos
    WHERE ativo = 0
    ORDER BY numero
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pontos Inativos · Impakto</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css?v=2">
</head>
<body>
<?php include __DIR__ . '/../partials/env_banner.php'; ?>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="container" style="padding-bottom:2rem;">

    <div class="welcome" style="margin-bottom:1.5rem;">
        <h2>⏸ Pontos Inativos</h2>
        <p><?= count($pontos) ?> ponto(s) desativado(s). Eles não aparecem na listagem principal nem podem ser incluídos em pré-seleções.</p>
    </div>

    <div class="table-container">
        <table class="backup-table">
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>Logradouro</th>
                    <th>Cidade</th>
                    <th>Região</th>
                    <th>Última situação</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($pontos)): ?>
                <tr><td colspan="6" class="backup-empty">Nenhum ponto inativo no momento.</td></tr>
            <?php else: ?>
                <?php foreach ($pontos as $p): ?>
                <tr>
                    <td class="backup-nome"><?= htmlspecialchars($p['numero']) ?></td>
                    <td><?= htmlspecialchars($p['logradouro']) ?></td>
                    <td class="backup-size"><?= htmlspecialchars($p['cidade'] ?? '—') ?></td>
                    <td class="backup-size"><?= htmlspecialchars($p['regiao'] ?? '—') ?></td>
                    <td class="backup-size"><?= htmlspecialchars($p['situacao'] ?? '—') ?></td>
                    <td style="white-space:nowrap">
                        <a href="/gestor/pontos/editar?id=<?= (int)$p['id'] ?>" class="btn-dl" style="margin-right:0.4rem;">Ver / Editar</a>
                        <form method="post" action="/gestor/pontos/reativar" style="display:inline"
                              onsubmit="return confirm('Reativar o ponto #<?= htmlspecialchars($p['numero'], ENT_QUOTES) ?>?')">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <button type="submit" class="btn-dl" style="background:#166534;color:#fff;border-color:#166534">↻ Reativar</button>
                        </form>
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
