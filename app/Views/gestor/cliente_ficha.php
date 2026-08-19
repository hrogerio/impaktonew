<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

$paginaAtual = 'clientes';

require_once __DIR__ . '/../../../config/database.php';
$pdo = getDatabase();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: /gestor/clientes");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$cliente) {
    header("Location: /gestor/clientes");
    exit;
}

$temCampanhaAtiva = (bool)$pdo->query("SELECT 1 FROM campanhas WHERE cliente_id = " . (int)$id . " AND ativo = 1 LIMIT 1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($cliente['razao_social']) ?> · Impakto</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
    <style>
        .cf-page { max-width:800px; margin:0 auto; padding:1.5rem 1.5rem 4rem; }
        .cf-voltar { font-size:0.8rem; font-weight:700; color:var(--color-text-muted); text-decoration:none; }
        .cf-voltar:hover { color:var(--color-accent-primary); }
        .cf-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin:0.75rem 0 1.25rem; flex-wrap:wrap; }
        .cf-nome { font-size:1.4rem; font-weight:800; color:var(--color-text-dark); margin:0; }
        .cf-status { font-size:0.65rem; font-weight:800; text-transform:uppercase; letter-spacing:0.4px; padding:2px 9px; border-radius:8px; }
        .cf-status.ativo   { background:#dcfce7; color:#166534; }
        .cf-status.inativo { background:#f1f5f9; color:#475569; }
        .cf-card { background:white; border:1px solid var(--color-border); border-radius:12px; padding:1.25rem; margin-bottom:1rem; }
        .cf-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:1rem; }
        .cf-campo-label { font-size:0.65rem; font-weight:800; text-transform:uppercase; letter-spacing:0.4px; color:var(--color-text-muted); margin-bottom:0.2rem; }
        .cf-campo-val { font-size:0.9rem; font-weight:600; color:var(--color-text-dark); }
        .cf-aviso {
            background:#fff7ed; border:1px solid #fed7aa; border-radius:10px;
            padding:0.9rem 1.1rem; font-size:0.82rem; color:#9a3412; font-weight:600;
            margin-bottom:1.25rem;
        }
        @media(max-width:520px) { .cf-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="cf-page">
    <a href="/gestor/clientes" class="cf-voltar">← Voltar para Clientes</a>

    <div class="cf-head">
        <h1 class="cf-nome">🏢 <?= htmlspecialchars($cliente['razao_social']) ?></h1>
        <span class="cf-status <?= $temCampanhaAtiva ? 'ativo' : 'inativo' ?>"><?= $temCampanhaAtiva ? 'Ativo' : 'Inativo' ?></span>
    </div>

    <div class="cf-aviso">
        🚧 Ficha do cliente ainda em desenvolvimento — por enquanto mostrando só os dados básicos do cadastro.
        Para ver as campanhas deste cliente, use o filtro na tela de <a href="/gestor/campanhas" style="color:#9a3412">Campanhas</a>.
    </div>

    <div class="cf-card">
        <div class="cf-grid">
            <div>
                <div class="cf-campo-label">CNPJ</div>
                <div class="cf-campo-val"><?= htmlspecialchars($cliente['cnpj'] ?: '—') ?></div>
            </div>
            <div>
                <div class="cf-campo-label">Contato</div>
                <div class="cf-campo-val"><?= htmlspecialchars($cliente['contato'] ?: '—') ?></div>
            </div>
            <div>
                <div class="cf-campo-label">Telefone</div>
                <div class="cf-campo-val"><?= htmlspecialchars($cliente['telefone'] ?: '—') ?></div>
            </div>
            <div>
                <div class="cf-campo-label">E-mail</div>
                <div class="cf-campo-val"><?= htmlspecialchars($cliente['email'] ?: '—') ?></div>
            </div>
            <div style="grid-column:1/-1">
                <div class="cf-campo-label">Endereço</div>
                <div class="cf-campo-val"><?= htmlspecialchars($cliente['endereco'] ?: '—') ?></div>
            </div>
            <?php if ($cliente['observacoes']): ?>
            <div style="grid-column:1/-1">
                <div class="cf-campo-label">Observações</div>
                <div class="cf-campo-val"><?= nl2br(htmlspecialchars($cliente['observacoes'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <a href="/gestor/clientes/editar?id=<?= (int)$cliente['id'] ?>" class="cf-voltar">✏️ Editar cadastro</a>
</div>

</body>
</html>
