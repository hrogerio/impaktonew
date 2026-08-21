<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

$paginaAtual = 'relatorios';

require_once __DIR__ . '/../../../config/database.php';
$pdo = getDatabase();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

// Nomes de agência usados em campanhas (texto livre) que ainda não têm cadastro
// correspondente em `agencias`. "Direto"/"-"/vazio significam "sem agência", não entram.
$candidatas = $pdo->query("
    SELECT TRIM(c.agencia) AS nome, COUNT(*) AS qtd_campanhas
    FROM campanhas c
    WHERE NULLIF(TRIM(c.agencia), '') IS NOT NULL
      AND LOWER(TRIM(c.agencia)) NOT IN ('direto', '-')
      AND NOT EXISTS (
          SELECT 1 FROM agencias a WHERE LOWER(TRIM(a.nome)) = LOWER(TRIM(c.agencia))
      )
    GROUP BY TRIM(c.agencia)
    ORDER BY nome ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Agências · Impakto</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css?v=2">
    <style>
        .imp-lista { background:white; border:1px solid var(--color-border); border-radius:12px; padding:0.5rem 1.25rem; margin-bottom:1.25rem; }
        .imp-linha { display:flex; align-items:center; gap:0.75rem; padding:0.7rem 0; border-bottom:1px solid #f0f2f5; }
        .imp-linha:last-child { border-bottom:none; }
        .imp-linha input[type="checkbox"] { width:18px; height:18px; flex-shrink:0; }
        .imp-nome { font-weight:700; color:var(--color-text-dark); flex:1; }
        .imp-qtd { font-size:0.78rem; color:var(--color-text-muted); }
        .imp-topo { display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; }
        .imp-marcar { font-size:0.8rem; color:var(--color-accent-primary); background:none; border:none; cursor:pointer; font-weight:700; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="container" style="padding-bottom:2rem; max-width:640px;">

    <div class="welcome" style="margin-bottom:1.5rem;">
        <h2>📥 Importar Agências de Campanhas</h2>
        <p>Cria o cadastro a partir dos nomes de agência já usados nas campanhas. Depois é só completar telefone, logo, diretoria e mídia de cada uma.</p>
    </div>

    <?php if (!empty($_GET['erro'])): ?>
        <div class="alerta alerta-err">❌ <?= htmlspecialchars($_GET['erro']) ?></div>
    <?php endif; ?>

    <?php if (empty($candidatas)): ?>
        <div class="empty-state"><p>Nenhuma agência nova pra importar — todas as agências usadas em campanhas já têm cadastro.</p></div>
        <a href="/gestor/agencias" class="btn-backup" style="background:#f3f4f6; color:var(--color-text-dark);">← Voltar</a>
    <?php else: ?>
    <form method="POST" action="/gestor/agencias/importar">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <div class="imp-topo">
            <strong><?= count($candidatas) ?> agência<?= count($candidatas) === 1 ? '' : 's' ?> encontrada<?= count($candidatas) === 1 ? '' : 's' ?></strong>
            <button type="button" class="imp-marcar" onclick="impAlternarTodos()">Marcar/desmarcar todas</button>
        </div>

        <div class="imp-lista">
            <?php foreach ($candidatas as $c): ?>
            <label class="imp-linha">
                <input type="checkbox" name="nomes[]" value="<?= htmlspecialchars($c['nome']) ?>" checked>
                <span class="imp-nome"><?= htmlspecialchars($c['nome']) ?></span>
                <span class="imp-qtd"><?= (int)$c['qtd_campanhas'] ?> campanha<?= $c['qtd_campanhas'] == 1 ? '' : 's' ?></span>
            </label>
            <?php endforeach; ?>
        </div>

        <div style="display:flex; gap:0.75rem;">
            <button type="submit" class="btn-backup btn-baixar">✅ Importar selecionadas</button>
            <a href="/gestor/agencias" class="btn-backup" style="background:#f3f4f6; color:var(--color-text-dark);">Cancelar</a>
        </div>
    </form>
    <?php endif; ?>

</div>

<script>
function impAlternarTodos() {
    var boxes = document.querySelectorAll('input[name="nomes[]"]');
    var algumDesmarcado = Array.prototype.some.call(boxes, function(b) { return !b.checked; });
    boxes.forEach(function(b) { b.checked = algumDesmarcado; });
}
</script>

</body>
</html>
