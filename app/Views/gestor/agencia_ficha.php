<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

$paginaAtual = 'relatorios';

require_once __DIR__ . '/../../../config/database.php';
$pdo = getDatabase();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: /gestor/agencias");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM agencias WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$agencia = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$agencia) {
    header("Location: /gestor/agencias");
    exit;
}

$stmtC = $pdo->prepare("SELECT nome, email, tipo FROM agencia_contatos WHERE agencia_id = ? ORDER BY ordem ASC, id ASC");
$stmtC->execute([$id]);
$diretoria = [];
$midia = [];
foreach ($stmtC->fetchAll(PDO::FETCH_ASSOC) as $c) {
    if ($c['tipo'] === 'diretoria') $diretoria[] = $c;
    else $midia[] = $c;
}

$temCampanhaAtiva = (bool)$pdo->query("SELECT 1 FROM campanhas WHERE agencia_id = " . (int)$id . " AND ativo = 1 LIMIT 1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($agencia['nome']) ?> · Impakto</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
    <style>
        .cf-page { max-width:800px; margin:0 auto; padding:1.5rem 1.5rem 4rem; }
        .cf-voltar { font-size:0.8rem; font-weight:700; color:var(--color-text-muted); text-decoration:none; }
        .cf-voltar:hover { color:var(--color-accent-primary); }
        .cf-head { display:flex; align-items:center; gap:1rem; margin:0.75rem 0 1.25rem; flex-wrap:wrap; }
        .cf-logo { width:56px; height:56px; border-radius:10px; object-fit:contain; background:#f6f7fb; border:1px solid var(--color-border); flex-shrink:0; }
        .cf-nome { font-size:1.4rem; font-weight:800; color:var(--color-text-dark); margin:0; flex:1; }
        .cf-status { font-size:0.65rem; font-weight:800; text-transform:uppercase; letter-spacing:0.4px; padding:2px 9px; border-radius:8px; }
        .cf-status.ativo   { background:#dcfce7; color:#166534; }
        .cf-status.inativo { background:#f1f5f9; color:#475569; }
        .cf-card { background:white; border:1px solid var(--color-border); border-radius:12px; padding:1.25rem; margin-bottom:1rem; }
        .cf-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:1rem; }
        .cf-campo-label { font-size:0.65rem; font-weight:800; text-transform:uppercase; letter-spacing:0.4px; color:var(--color-text-muted); margin-bottom:0.2rem; }
        .cf-campo-val { font-size:0.9rem; font-weight:600; color:var(--color-text-dark); }
        .cf-secao-titulo { font-size:0.78rem; font-weight:800; text-transform:uppercase; letter-spacing:0.4px; color:var(--color-text-muted); margin-bottom:0.6rem; }
        .cf-pessoa { display:flex; justify-content:space-between; align-items:center; padding:0.5rem 0; border-bottom:1px solid #f0f2f5; }
        .cf-pessoa:last-child { border-bottom:none; }
        .cf-pessoa-nome { font-size:0.88rem; font-weight:700; color:var(--color-text-dark); }
        .cf-pessoa-email { font-size:0.8rem; color:var(--color-accent-primary); text-decoration:none; }
        .cf-pessoa-email:hover { text-decoration:underline; }
        .cf-vazio { font-size:0.82rem; color:var(--color-text-muted); font-style:italic; }
        @media(max-width:520px) { .cf-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="cf-page">
    <a href="/gestor/agencias" class="cf-voltar">← Voltar para Agências</a>

    <div class="cf-head">
        <?php if ($agencia['logo']): ?>
        <img src="/<?= htmlspecialchars($agencia['logo']) ?>" alt="" class="cf-logo">
        <?php endif; ?>
        <h1 class="cf-nome">🏛️ <?= htmlspecialchars($agencia['nome']) ?></h1>
        <span class="cf-status <?= $temCampanhaAtiva ? 'ativo' : 'inativo' ?>"><?= $temCampanhaAtiva ? 'Ativo' : 'Inativo' ?></span>
    </div>

    <div class="cf-card">
        <div class="cf-grid">
            <div>
                <div class="cf-campo-label">Telefone</div>
                <div class="cf-campo-val"><?= htmlspecialchars($agencia['telefone'] ?: '—') ?></div>
            </div>
            <div style="grid-column:2/3"></div>
            <div style="grid-column:1/-1">
                <div class="cf-campo-label">Endereço</div>
                <div class="cf-campo-val"><?= htmlspecialchars($agencia['endereco'] ?: '—') ?></div>
            </div>
            <?php if ($agencia['observacoes']): ?>
            <div style="grid-column:1/-1">
                <div class="cf-campo-label">Observações</div>
                <div class="cf-campo-val"><?= nl2br(htmlspecialchars($agencia['observacoes'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="cf-card">
        <div class="cf-secao-titulo">Diretoria</div>
        <?php if (empty($diretoria)): ?>
        <div class="cf-vazio">Nenhum diretor(a) cadastrado.</div>
        <?php else: foreach ($diretoria as $d): ?>
        <div class="cf-pessoa">
            <span class="cf-pessoa-nome"><?= htmlspecialchars($d['nome']) ?></span>
            <?php if ($d['email']): ?>
            <a href="mailto:<?= htmlspecialchars($d['email']) ?>" class="cf-pessoa-email"><?= htmlspecialchars($d['email']) ?></a>
            <?php endif; ?>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <div class="cf-card">
        <div class="cf-secao-titulo">Mídia</div>
        <?php if (empty($midia)): ?>
        <div class="cf-vazio">Nenhum contato de mídia cadastrado.</div>
        <?php else: foreach ($midia as $m): ?>
        <div class="cf-pessoa">
            <span class="cf-pessoa-nome"><?= htmlspecialchars($m['nome']) ?></span>
            <?php if ($m['email']): ?>
            <a href="mailto:<?= htmlspecialchars($m['email']) ?>" class="cf-pessoa-email"><?= htmlspecialchars($m['email']) ?></a>
            <?php endif; ?>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <a href="/gestor/agencias/editar?id=<?= (int)$agencia['id'] ?>" class="cf-voltar">✏️ Editar cadastro</a>
</div>

</body>
</html>
