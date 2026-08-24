<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

$paginaAtual = 'relatorios';

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

// Pra onde voltar depois de salvar: de onde o usuário veio, restrito a páginas internas.
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$refPath = parse_url($referer, PHP_URL_PATH) ?: '';
$voltarUrl = str_starts_with($refPath, '/gestor/') ? $referer : '/gestor/agencias';

$dados = ['id' => 0, 'nome' => '', 'endereco' => '', 'telefone' => '', 'logo' => '', 'observacoes' => ''];
$diretoria = [];
$midia = [];
if ($editando) {
    $stmt = $pdo->prepare("SELECT * FROM agencias WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        header("Location: /gestor/agencias");
        exit;
    }
    $dados = $row;

    $stmtC = $pdo->prepare("SELECT nome, email, tipo FROM agencia_contatos WHERE agencia_id = ? ORDER BY ordem ASC, id ASC");
    $stmtC->execute([$id]);
    foreach ($stmtC->fetchAll(PDO::FETCH_ASSOC) as $c) {
        if ($c['tipo'] === 'diretoria') $diretoria[] = $c;
        else $midia[] = $c;
    }
}
if (empty($diretoria)) $diretoria[] = ['nome' => '', 'email' => ''];
if (empty($midia)) $midia[] = ['nome' => '', 'email' => ''];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $editando ? 'Editar' : 'Nova' ?> Agência · Impakto</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css?v=2">
    <style>
        .ag-form-linha { display:flex; gap:0.5rem; align-items:center; margin-bottom:0.5rem; }
        .ag-form-linha input { flex:1; padding:0.55rem; border:1px solid var(--color-border); border-radius:8px; }
        .ag-form-linha input.ag-email { flex:0.8; }
        .ag-form-remover {
            flex-shrink:0; width:32px; height:32px; border-radius:8px; border:1px solid var(--color-border);
            background:#fff; color:#dc3545; cursor:pointer; font-size:0.9rem;
        }
        .ag-form-remover:hover { background:#fdecea; }
        .ag-form-adicionar {
            padding:0.4rem 0.8rem; border-radius:8px; border:1px dashed var(--color-border);
            background:none; color:var(--color-accent-primary); cursor:pointer; font-size:0.82rem; font-weight:700;
        }
        .ag-form-adicionar:hover { background:#fef4f3; }
        .ag-logo-preview { width:80px; height:80px; border-radius:10px; object-fit:contain; background:#f6f7fb; border:1px solid var(--color-border); margin-bottom:0.6rem; display:block; }
        .ag-secao-titulo { font-size:0.9rem; font-weight:800; color:var(--color-text-dark); margin:1.25rem 0 0.6rem; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../partials/env_banner.php'; ?>


<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="container" style="padding-bottom:2rem; max-width:680px;">

    <div class="welcome" style="margin-bottom:1.5rem;">
        <h2><?= $editando ? '✏️ Editar Agência' : '➕ Nova Agência' ?></h2>
    </div>

    <?php if (!empty($_GET['erro'])): ?>
        <div class="alerta alerta-err">❌ <?= htmlspecialchars($_GET['erro']) ?></div>
    <?php endif; ?>

    <form method="POST" action="/gestor/agencias/salvar" enctype="multipart/form-data" class="table-container" style="padding:1.5rem;">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="id" value="<?= (int)$dados['id'] ?>">
        <input type="hidden" name="voltar" value="<?= htmlspecialchars($voltarUrl) ?>">

        <div class="form-group" style="margin-bottom:1rem;">
            <label>Logomarca</label><br>
            <?php if (!empty($dados['logo'])): ?>
            <img src="/<?= htmlspecialchars($dados['logo']) ?>" class="ag-logo-preview" alt="Logo atual">
            <?php endif; ?>
            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml">
            <div style="font-size:0.75rem; color:var(--color-text-muted); margin-top:0.3rem;">PNG, JPG, WEBP ou SVG — até 2 MB.</div>
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label>Nome *</label><br>
            <input type="text" name="nome" required maxlength="200"
                   value="<?= htmlspecialchars($dados['nome']) ?>"
                   style="width:100%; padding:0.6rem; border:1px solid var(--color-border); border-radius:8px;">
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label>Endereço</label><br>
            <input type="text" name="endereco" maxlength="255"
                   value="<?= htmlspecialchars($dados['endereco'] ?? '') ?>"
                   style="width:100%; padding:0.6rem; border:1px solid var(--color-border); border-radius:8px;">
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label>Telefone</label><br>
            <input type="text" name="telefone" maxlength="30"
                   value="<?= htmlspecialchars($dados['telefone'] ?? '') ?>"
                   style="width:100%; padding:0.6rem; border:1px solid var(--color-border); border-radius:8px;">
        </div>

        <div class="ag-secao-titulo">Diretoria</div>
        <div id="agListaDiretoria">
            <?php foreach ($diretoria as $d): ?>
            <div class="ag-form-linha">
                <input type="text" name="diretoria_nome[]" placeholder="Nome" value="<?= htmlspecialchars($d['nome']) ?>">
                <input type="email" name="diretoria_email[]" class="ag-email" placeholder="E-mail" value="<?= htmlspecialchars($d['email'] ?? '') ?>">
                <button type="button" class="ag-form-remover" onclick="agRemoverLinha(this)" title="Remover">✕</button>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="ag-form-adicionar" onclick="agAdicionarLinha('agListaDiretoria', 'diretoria')">➕ Adicionar diretor(a)</button>

        <div class="ag-secao-titulo">Mídia</div>
        <div id="agListaMidia">
            <?php foreach ($midia as $m): ?>
            <div class="ag-form-linha">
                <input type="text" name="midia_nome[]" placeholder="Nome" value="<?= htmlspecialchars($m['nome']) ?>">
                <input type="email" name="midia_email[]" class="ag-email" placeholder="E-mail" value="<?= htmlspecialchars($m['email'] ?? '') ?>">
                <button type="button" class="ag-form-remover" onclick="agRemoverLinha(this)" title="Remover">✕</button>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="ag-form-adicionar" onclick="agAdicionarLinha('agListaMidia', 'midia')">➕ Adicionar contato de mídia</button>

        <div class="form-group" style="margin-top:1.25rem;">
            <label>Observações</label><br>
            <textarea name="observacoes" rows="3"
                      style="width:100%; padding:0.6rem; border:1px solid var(--color-border); border-radius:8px;"><?= htmlspecialchars($dados['observacoes'] ?? '') ?></textarea>
        </div>

        <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
            <button type="submit" class="btn-backup btn-baixar">💾 Salvar</button>
            <a href="<?= htmlspecialchars($voltarUrl) ?>" class="btn-backup" style="background:#f3f4f6; color:var(--color-text-dark);">Cancelar</a>
        </div>
    </form>

</div>

<script>
function agAdicionarLinha(containerId, prefixo) {
    var container = document.getElementById(containerId);
    var linha = document.createElement('div');
    linha.className = 'ag-form-linha';
    linha.innerHTML =
        '<input type="text" name="' + prefixo + '_nome[]" placeholder="Nome">' +
        '<input type="email" name="' + prefixo + '_email[]" class="ag-email" placeholder="E-mail">' +
        '<button type="button" class="ag-form-remover" onclick="agRemoverLinha(this)" title="Remover">✕</button>';
    container.appendChild(linha);
}

function agRemoverLinha(btn) {
    var container = btn.parentElement.parentElement;
    if (container.children.length > 1) {
        btn.parentElement.remove();
    } else {
        btn.parentElement.querySelectorAll('input').forEach(function(i) { i.value = ''; });
    }
}
</script>

</body>
</html>
