<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}
$paginaAtual = 'pontos';

try {
    require_once __DIR__ . '/../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("Erro na conexão: " . $e->getMessage());
}

$sql = "
    SELECT p.id, p.numero, p.logradouro, p.descricao, p.cidade, p.regiao,
           c.cliente AS cliente,
           c.agencia AS agencia,
           COALESCE(c.contato, p.contato) AS contato,
           p.tipo, p.situacao, p.corredor, p.formato,
           p.exclusivo, p.cliente_exclusivo, p.liberado_comercializacao,
           COALESCE(
               (SELECT pf.caminho FROM ponto_fotos pf WHERE pf.ponto_id = p.id AND pf.principal = 1 LIMIT 1),
               p.foto
           ) AS foto,
           CASE
               WHEN c.fim IS NULL OR CAST(c.fim AS CHAR) = '0000-00-00'
               THEN NULL ELSE DATE(c.fim)
           END AS fim_contrato
    FROM pontos p
    LEFT JOIN campanhas c ON c.ponto_id = p.id AND c.ativo = 1
    WHERE p.ativo = 1 OR p.ativo IS NULL
    ORDER BY p.id DESC
";
$pontos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$regioes    = $pdo->query("SELECT DISTINCT regiao   FROM pontos WHERE regiao   IS NOT NULL AND regiao   != '' AND (ativo=1 OR ativo IS NULL) ORDER BY regiao"  )->fetchAll(PDO::FETCH_COLUMN);
$cidades    = $pdo->query("SELECT DISTINCT cidade   FROM pontos WHERE cidade   IS NOT NULL AND cidade   != '' AND (ativo=1 OR ativo IS NULL) ORDER BY cidade"  )->fetchAll(PDO::FETCH_COLUMN);
$clientes   = $pdo->query("
    SELECT DISTINCT c.cliente AS cliente
    FROM pontos p
    LEFT JOIN campanhas c ON c.ponto_id = p.id AND c.ativo = 1
    WHERE (p.ativo = 1 OR p.ativo IS NULL)
    HAVING cliente IS NOT NULL AND cliente != ''
    ORDER BY cliente
")->fetchAll(PDO::FETCH_COLUMN);
$corredores = $pdo->query("SELECT DISTINCT corredor FROM pontos WHERE corredor IS NOT NULL AND corredor != '' AND (ativo=1 OR ativo IS NULL) ORDER BY corredor")->fetchAll(PDO::FETCH_COLUMN);

// Garante UTF-8 válido em todos os campos antes do json_encode
array_walk_recursive($pontos, function(&$v) {
    if (is_string($v)) $v = mb_convert_encoding($v, 'UTF-8', 'UTF-8');
});
$pontosJson = json_encode($pontos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($pontosJson === false) {
    // Fallback: substitui chars inválidos e tenta novamente
    $pontosJson = json_encode($pontos, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
}
if ($pontosJson === false) $pontosJson = '[]';

// Últimas 6 alterações/adições para a barra de push
$recentes = $pdo->query(
    "SELECT id, numero, logradouro, cidade, situacao
     FROM pontos WHERE ativo = 1 OR ativo IS NULL
     ORDER BY id DESC LIMIT 6"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pontos - Impakto Mídia</title>
    <link rel="icon" href="/public/assets/img/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
    <link rel="stylesheet" href="/public/assets/css/pontos.css">
    <style>
        body { overflow: hidden; }

        /* ── Layout full-viewport ── */
        .pontos-page { display:flex; flex-direction:column; padding:0.5rem 1.2rem 0; overflow:hidden; box-sizing:border-box; }
        .pontos-left { display:flex; flex-direction:column; min-height:0; overflow:hidden; flex:1; }
        .table-container { flex:1; overflow-y:auto; min-height:0; scrollbar-width:none; }
        .table-container::-webkit-scrollbar { display:none; }

        /* ── Carrinho flutuante ── */
        .cart-btn {
            position:fixed; bottom:1.5rem; right:1.5rem; z-index:500;
            display:flex; align-items:center; gap:0.6rem;
            background:var(--color-accent-primary); color:white;
            border:none; border-radius:50px; padding:0.65rem 1.25rem 0.65rem 1rem;
            font-family:'Montserrat',sans-serif; font-size:0.85rem; font-weight:700;
            cursor:pointer; box-shadow:0 4px 20px rgba(192,57,43,0.4);
            transition:all 0.2s; text-decoration:none;
            opacity:0; pointer-events:none; transform:translateY(10px);
        }
        .cart-btn.visivel { opacity:1; pointer-events:auto; transform:translateY(0); }
        .cart-btn:hover { background:#a93226; box-shadow:0 6px 24px rgba(192,57,43,0.5); }
        .cart-icon { font-size:1.1rem; }
        .cart-badge {
            background:white; color:var(--color-accent-primary);
            font-size:0.72rem; font-weight:800;
            padding:1px 7px; border-radius:20px; min-width:20px; text-align:center;
        }

        /* ── Tabela ── */
        .col-check { width:32px; padding-right:0 !important; text-align:center; }
        .col-foto  { width:70px; padding:3px 4px !important; }
        .thumb-wrap { width:62px; height:52px; border-radius:4px; overflow:hidden; background:#f0f0f0; display:flex; align-items:center; justify-content:center; }
        .thumb-img  { width:100%; height:100%; object-fit:cover; cursor:zoom-in; }
        .thumb-vazio { font-size:0.65rem; color:#ccc; }
        .table { width:100%; border-collapse:collapse; font-size:0.78rem; table-layout:fixed; }
        .table thead th {
            background:var(--color-bg-primary); padding:0.3rem 0.5rem;
            font-size:0.65rem; font-weight:700; color:var(--color-text-muted);
            text-transform:uppercase; letter-spacing:0.5px;
            border-bottom:1.5px solid var(--color-border);
            white-space:nowrap; cursor:pointer; user-select:none;
        }
        .table thead th:hover { color:var(--color-accent-primary); }
        .table thead th .sort-icon::after { content:' ↕'; opacity:0.4; }
        .table thead th.sort-asc  .sort-icon::after { content:' ↑'; opacity:1; }
        .table thead th.sort-desc .sort-icon::after { content:' ↓'; opacity:1; }
        .table tbody tr { border-bottom:1px solid var(--color-border); transition:background 0.1s; cursor:pointer; }
        .table tbody tr:hover { background:#fafafa; }
        .table tbody tr.selecionado { background:#fff8f7 !important; box-shadow:inset 3px 0 0 var(--color-accent-primary); }
        .table td { padding:1.32rem 0.5rem; vertical-align:middle; line-height:1.3; }
        .col-num { font-weight:800; color:var(--color-accent-primary); font-size:0.82rem; white-space:nowrap; }
        .col-txt  { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:0; font-weight:600; }
        .col-sub  { font-size:0.66rem; color:var(--color-text-muted); font-weight:400; }
        .row-check { width:15px; height:15px; cursor:pointer; accent-color:var(--color-accent-primary); }

        /* ── Badges ── */
        .badge-sit { display:inline-block; padding:2px 7px; border-radius:20px; font-size:0.62rem; font-weight:700; text-transform:uppercase; letter-spacing:0.3px; white-space:nowrap; }
        .sit-disponivel { background:#dcfce7; color:#166534; }
        .sit-ocupado    { background:#fee2e2; color:#991b1b; }
        .sit-reservado  { background:#ffedd5; color:#9a3412; }
        .sit-vencido    { background:#f3e8ff; color:#6b21a8; }
        .sit-permuta    { background:#ede9fe; color:#4c1d95; }
        .sit-bisemana   { background:#cffafe; color:#164e63; }
        .sit-outro      { background:#f1f5f9; color:#475569; }
        .col-sit        { max-width:0; }
        .sit-badges     { display:flex; flex-wrap:wrap; gap:3px; align-items:center; }
        .badge-excl-wrap { margin-top:3px; }
        .badge-excl     { display:inline-block; padding:2px 7px; border-radius:20px; font-size:0.62rem; font-weight:700; white-space:nowrap; background:#1e293b; color:#f8fafc; border:none; font-family:'Montserrat', sans-serif; cursor:pointer; transition:filter 0.15s; }
        .badge-excl:hover { filter:brightness(1.2); }
        .badge-excl.liberado { background:#78350f; color:#fef3c7; }
        /* ── Dropdown de ações de exclusivos ── */
        .dropdown-excl { position:relative; }
        .btn-dropdown-excl {
            display:inline-flex; align-items:center; gap:0.35rem;
            background:none; border:1.5px solid var(--color-border); color:var(--color-text-muted);
            font-family:'Montserrat', sans-serif; font-size:0.75rem; font-weight:600;
            cursor:pointer; white-space:nowrap; padding:0.4rem 0.7rem; border-radius:6px;
            transition:all 0.15s;
        }
        .btn-dropdown-excl:hover, .btn-dropdown-excl.aberto { color:var(--color-text-dark); border-color:var(--color-text-muted); background:#f8fafc; }
        .dropdown-caret { font-size:0.65rem; transition:transform 0.15s; }
        .btn-dropdown-excl.aberto .dropdown-caret { transform:rotate(180deg); }
        .dropdown-excl-menu {
            display:none; position:absolute; top:calc(100% + 6px); right:0; z-index:100;
            background:white; border:1px solid var(--color-border); border-radius:8px;
            box-shadow:0 8px 24px rgba(0,0,0,0.12); min-width:230px; overflow:hidden;
            padding:0.3rem;
        }
        .dropdown-excl-menu.aberto { display:block; }
        .dropdown-excl-item {
            display:flex; align-items:center; gap:0.5rem; width:100%;
            background:none; border:none; text-align:left;
            font-family:'Montserrat', sans-serif; font-size:0.78rem; font-weight:600;
            color:var(--color-text-dark); cursor:pointer; padding:0.55rem 0.6rem; border-radius:5px;
            white-space:nowrap;
        }
        .dropdown-excl-item:hover { background:#f1f5f9; color:var(--color-accent-primary); }
        .acoes-divider { width:1px; height:1.6rem; background:var(--color-border); }
        .badge-contrato { display:inline-block; padding:1px 6px; border-radius:10px; font-size:0.62rem; font-weight:600; white-space:nowrap; }
        .ctr-vencido { background:#fde8e8; color:#c0392b; }
        .ctr-critico { background:#fee2e2; color:#991b1b; }
        .ctr-urgente { background:#fff3cd; color:#856404; }
        .ctr-atencao { background:#dbeafe; color:#1e40af; }
        .ctr-ok      { background:#dcfce7; color:#166534; }
        .ctr-sem     { background:#f1f5f9; color:#94a3b8; }

        /* ── Links ação ── */
        .link-info { color:var(--color-accent-primary); font-weight:700; text-decoration:none; font-size:0.7rem; padding:2px 7px; border:1.5px solid var(--color-accent-primary); border-radius:5px; transition:all 0.15s; white-space:nowrap; }
        .link-info:hover { background:var(--color-accent-primary); color:white; }
        .link-editar { color:var(--color-text-muted); font-size:0.85rem; margin-left:4px; text-decoration:none; }
        .link-editar:hover { color:var(--color-accent-primary); }

        /* ── Lightbox ── */
        .lb-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.82); z-index:2000; align-items:center; justify-content:center; cursor:zoom-out; }
        .lb-overlay.aberto { display:flex; }
        .lb-img { max-width:90vw; max-height:88vh; border-radius:10px; box-shadow:0 20px 60px rgba(0,0,0,0.5); }

        mark { background:#fff3cd; padding:1px 2px; border-radius:2px; }
        .empty-row td { text-align:center; padding:3rem 1rem; color:var(--color-text-muted); font-size:0.85rem; }

        /* ── Cabeçalho de grupo (região) ── */
        .group-header td {
            background:var(--color-bg-primary);
            padding:0.28rem 0.5rem;
            border-top:2px solid var(--color-border);
            border-bottom:1px solid var(--color-border);
            cursor:default;
        }
        .group-header:first-child td { border-top:none; }
        .group-reg {
            font-size:0.65rem; font-weight:800;
            color:var(--color-accent-primary);
            text-transform:uppercase; letter-spacing:0.8px;
        }
        .group-count {
            margin-left:0.5rem; font-size:0.65rem;
            color:var(--color-text-muted); font-weight:600;
        }
        .group-disp {
            margin-left:0.4rem; font-size:0.62rem; font-weight:700;
            color:#166534; background:#dcfce7;
            padding:1px 6px; border-radius:8px;
        }

        /* ── Push bar ── */
        .push-bar {
            display:flex; align-items:center; gap:0.6rem;
            background:linear-gradient(180deg, #1e293b, #17212f);
            color:white;
            padding:0.4rem 1.2rem; font-size:0.72rem; flex-shrink:0;
            overflow:hidden;
            box-shadow:inset 0 1px 0 rgba(255,255,255,0.05);
        }
        .push-bar-label { font-weight:700; color:#94a3b8; white-space:nowrap; flex-shrink:0; }
        .push-bar-items {
            display:flex; gap:0.4rem; overflow:hidden; flex:1;
            mask-image: linear-gradient(90deg, #000 calc(100% - 24px), transparent 100%);
            -webkit-mask-image: linear-gradient(90deg, #000 calc(100% - 24px), transparent 100%);
        }
        .push-bar-item {
            color:white; text-decoration:none;
            padding:3px 10px; border-radius:20px;
            background:rgba(255,255,255,0.08);
            border:1px solid rgba(255,255,255,0.06);
            white-space:nowrap; font-size:0.7rem;
            transition:background 0.15s, transform 0.15s; flex-shrink:0;
        }
        .push-bar-item:hover { background:rgba(255,255,255,0.18); transform:translateY(-1px); }
        .push-bar-item strong { color:#f87171; margin-right:3px; }
        .push-bar-close {
            margin-left:auto; flex-shrink:0;
            background:none; border:none; color:#475569;
            font-size:0.8rem; cursor:pointer; padding:2px 5px;
            border-radius:3px; line-height:1;
        }
        .push-bar-close:hover { color:white; background:rgba(255,255,255,0.1); }

        @media print { .no-print { display:none !important; } body { overflow:auto; } }
    </style>
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<?php if (!empty($recentes)): ?>
<div class="push-bar no-print" id="pushBar">
    <span class="push-bar-label">🕐 Recentes:</span>
    <div class="push-bar-items">
        <?php foreach ($recentes as $r): ?>
        <a href="/gestor/pontos/detalhes?id=<?= (int)$r['id'] ?>" class="push-bar-item">
            <strong><?= htmlspecialchars($r['numero']) ?></strong><?= htmlspecialchars(mb_strimwidth($r['logradouro'], 0, 35, '…')) ?><?= $r['cidade'] ? ' · '.htmlspecialchars($r['cidade']) : '' ?>
        </a>
        <?php endforeach; ?>
    </div>
    <button class="push-bar-close" onclick="fecharPushBar()" title="Fechar">✕</button>
</div>
<?php endif; ?>

<div class="pontos-page" id="pontosPage">

    <!-- Controles -->
    <div class="no-print">
        <div class="search-bar">
            <span class="search-icon">🔍</span>
            <input type="text" id="searchInput" placeholder="Buscar por número, logradouro, cidade, cliente..." autocomplete="off">
            <button class="search-clear" id="searchClear" title="Limpar">✕</button>
            <span class="search-kbd">Ctrl+K</span>
        </div>
        <div class="filtros-wrap">
            <select class="filtro-select" id="filtroRegiao">
                <option value="">Todas as regiões</option>
                <?php foreach ($regioes as $r): ?>
                <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="filtro-select" id="filtroCidade">
                <option value="">Todas as cidades</option>
                <?php foreach ($cidades as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="filtro-select" id="filtroCliente">
                <option value="">Todos os clientes</option>
                <?php foreach ($clientes as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="filtro-select" id="filtroSituacao">
                <option value="">Todas as situações</option>
                <option value="Disponivel">Disponível</option>
                <option value="Ocupado">Ocupado</option>
                <option value="Reservado">Reservado</option>
                <option value="Vencido">Vencido</option>
                <option value="Permuta">Permuta</option>
                <option value="Bisemana">Bisemana</option>
                <option value="__exclusivo__">🔒 Exclusivo</option>
            </select>
            <select class="filtro-select" id="filtroCorredor">
                <option value="">Todos os corredores</option>
                <?php foreach ($corredores as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="filtro-select" id="filtroVencimento">
                <option value="">Todos os prazos</option>
                <option value="7">⚠️ Vencendo em 7 dias</option>
                <option value="15">Vencendo em 15 dias</option>
                <option value="30">Vencendo em 30 dias</option>
                <option value="vencido">🔴 Já vencidos</option>
                <option value="sem_prazo">Sem prazo definido</option>
            </select>
            <button class="btn-limpar-filtros" id="btnLimpar">✕ Limpar filtros</button>
        </div>
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:0.3rem;">
            <span id="resultCount"></span>
            <span id="selCount" style="font-size:0.75rem;color:var(--color-accent-primary);font-weight:700;"></span>
            <div class="dropdown-excl" id="dropdownExcl" style="margin-left:auto">
                <button type="button" class="btn-dropdown-excl" id="btnDropdownExcl" onclick="toggleDropdownExcl(event)">🔒 Exclusivos <span class="dropdown-caret">▾</span></button>
                <div class="dropdown-excl-menu" id="dropdownExclMenu">
                    <button type="button" class="dropdown-excl-item" onclick="fecharDropdownExcl();gerarRelatorioExclusivos()">📋 Relatório de Exclusivos</button>
                    <button type="button" class="dropdown-excl-item" onclick="fecharDropdownExcl();gerarPdfExclusivos()">📑 PDF de Apresentação</button>
                </div>
            </div>
            <span class="acoes-divider"></span>
            <a href="/gestor/pontos/novo" class="btn-novo-ponto">+ Novo Ponto</a>
        </div>
    </div>

    <!-- Tabela -->
    <div class="pontos-left">
        <div class="table-container">
            <table class="table" id="tabelaPontos">
                <thead>
                    <tr>
                        <th class="col-check no-print" style="width:32px"><input type="checkbox" id="checkAll" class="row-check" title="Selecionar todos visíveis" onclick="toggleTodos(this.checked)"></th>
                        <th class="col-foto no-print" style="width:70px"></th>
                        <th data-col="numero" style="width:52px">Nº<span class="sort-icon"></span></th>
                        <th data-col="logradouro" style="width:35%">Logradouro<span class="sort-icon"></span></th>
                        <th data-col="cidade" style="width:18%">Cidade<span class="sort-icon"></span></th>
                        <th data-col="cliente" style="width:16%">Cliente<span class="sort-icon"></span></th>
                        <th data-col="situacao" style="width:20%">Situação / Vencimento<span class="sort-icon"></span></th>
                        <th class="no-print" style="width:80px"></th>
                    </tr>
                </thead>
                <tbody id="tabelaBody"></tbody>
            </table>
        </div>
    </div>

</div>

<!-- Carrinho flutuante -->
<a class="cart-btn no-print" id="cartBtn" href="/gestor/pre-selecao">
    <span class="cart-icon">🛒</span>
    <span id="cartLabel">Pré-Seleção</span>
    <span class="cart-badge" id="cartBadge">0</span>
</a>

<!-- Lightbox -->
<div class="lb-overlay" id="lbOverlay" onclick="fecharLb()">
    <img class="lb-img" id="lbImg" src="" alt="">
</div>

<script>
var PONTOS   = (function(){ try { return <?= $pontosJson ?>; } catch(e){ return []; } })();
if (!Array.isArray(PONTOS)) PONTOS = [];
var CART_KEY = 'impakto_cart';
var selecao  = new Set(JSON.parse(localStorage.getItem(CART_KEY) || '[]'));
var sortCol  = 'numero', sortDir = 'asc';
var listaVisivelAtual = []; // pontos atualmente renderizados na tabela (vazio no estado padrão sem filtro)
var FILTROS_KEY = 'impakto_pontos_filtros_v1';
var filtros  = { busca:'', regiao:'', cidade:'', cliente:'', situacao:'', corredor:'', vencimento:'' };
function salvarFiltros() {
    try { sessionStorage.setItem(FILTROS_KEY, JSON.stringify(filtros)); } catch(e) {}
}
var pontosMap = {};
PONTOS.forEach(function(p) { pontosMap[String(p.id)] = p; });

// ── Helpers ──────────────────────────────────────────────────
function esc(str) {
    return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function highlight(text, termo) {
    if (!termo||!text) return esc(text||'');
    var re = new RegExp('('+termo.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+')','gi');
    return esc(text).replace(re,'<mark>$1</mark>');
}
function normalizar(str) {
    return String(str||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'');
}
function estaAVenda(p) {
    return !(parseInt(p.exclusivo) === 1 && parseInt(p.liberado_comercializacao) !== 1);
}
function badgeSit(sit, p) {
    if (!sit) return '<span class="badge-sit sit-outro">—</span>';
    var s = normalizar(sit);
    if ((s==='disponivel'||s==='disponível') && p && !estaAVenda(p)) return '';
    var cls = (s==='disponivel'||s==='disponível') ? 'sit-disponivel'
            : s==='ocupado'   ? 'sit-ocupado'
            : s==='reservado' ? 'sit-reservado'
            : s==='vencido'   ? 'sit-vencido'
            : s==='permuta'   ? 'sit-permuta'
            : s==='bisemana'  ? 'sit-bisemana' : 'sit-outro';
    return '<span class="badge-sit '+cls+'">'+esc(sit)+'</span>';
}
function badgeContrato(data) {
    if (!data) return '';
    var fim = new Date(data), hoje = new Date();
    hoje.setHours(0,0,0,0); fim.setHours(0,0,0,0);
    var dias = Math.round((fim-hoje)/86400000);
    var mes = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'][fim.getMonth()];
    var lbl = '<span style="font-size:0.63rem;font-weight:600;margin-right:3px">'+mes+'/'+fim.getFullYear().toString().slice(2)+'</span>';
    if (dias < 0)   return lbl+'<span class="badge-contrato ctr-vencido">há '+Math.abs(dias)+'d</span>';
    if (dias <= 7)  return lbl+'<span class="badge-contrato ctr-critico">'+dias+'d</span>';
    if (dias <= 30) return lbl+'<span class="badge-contrato ctr-urgente">'+dias+'d</span>';
    if (dias <= 90) return lbl+'<span class="badge-contrato ctr-atencao">'+Math.floor(dias/30)+'m</span>';
    return lbl+'<span class="badge-contrato ctr-ok">'+Math.floor(dias/30)+'m</span>';
}
function badgeExclusivo(p) {
    if (!p.exclusivo || parseInt(p.exclusivo) !== 1) return '';
    var titulo = p.cliente_exclusivo ? 'Exclusivo de ' + p.cliente_exclusivo : 'Exclusivo';
    if (parseInt(p.liberado_comercializacao) === 1) {
        return '<div class="badge-excl-wrap"><button type="button" class="badge-excl liberado" onclick="event.stopPropagation();toggleLiberadoExclusivo('+p.id+')" title="'+esc(titulo)+', liberado para comercialização — clique para bloquear de novo">🔓 Liberado p/ comerc.</button></div>';
    }
    return '<div class="badge-excl-wrap"><button type="button" class="badge-excl" onclick="event.stopPropagation();toggleLiberadoExclusivo('+p.id+')" title="'+esc(titulo)+' — clique para liberar para comercialização">🔒 Exclusivo</button></div>';
}

// ── Liberar/bloquear ponto exclusivo p/ comercialização (inline) ──
function toggleLiberadoExclusivo(id) {
    fetch('/gestor/pontos/toggle-liberado', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id})
    })
    .then(function(r){ return r.json(); })
    .then(function(data) {
        if (!data.ok) {
            mostrarAviso('⚠️ Não foi possível alterar a liberação deste ponto.', 'laranja');
            return;
        }
        var ponto = PONTOS.find(function(p){ return String(p.id) === String(id); });
        if (ponto) {
            ponto.liberado_comercializacao = data.liberado_comercializacao;
            var liberado = parseInt(data.liberado_comercializacao) === 1;
            mostrarAviso(
                liberado
                    ? '🔓 Ponto ' + (ponto.numero||id) + ' liberado — já pode ser incluído numa pré-seleção junto com pontos comuns.'
                    : '🔒 Ponto ' + (ponto.numero||id) + ' bloqueado de novo para comercialização.',
                liberado ? 'verde' : 'laranja'
            );
        }
        renderTabela();
    })
    .catch(function() {
        mostrarAviso('⚠️ Erro de comunicação ao alterar a liberação.', 'laranja');
    });
}

// ── Relatório interno de painéis exclusivos (não é o fluxo de comercialização) ──
function gerarRelatorioExclusivos() {
    var exclusivos = filtrar(PONTOS).filter(function(p){ return parseInt(p.exclusivo) === 1; });
    if (exclusivos.length === 0) {
        alert('Nenhum painel exclusivo encontrado com os filtros atuais.');
        return;
    }
    exclusivos = exclusivos.slice().sort(function(a,b) {
        return normalizar(a.cliente_exclusivo||'').localeCompare(normalizar(b.cliente_exclusivo||''));
    });

    // Agrupa por cliente exclusivo, na ordem já ordenada
    var grupos = {};
    var ordemClientes = [];
    exclusivos.forEach(function(p) {
        var cli = p.cliente_exclusivo || '—';
        if (!grupos[cli]) { grupos[cli] = []; ordemClientes.push(cli); }
        grupos[cli].push(p);
    });

    var linhas = ordemClientes.map(function(cli) {
        var pontosCliente = grupos[cli];
        var ckQ = 'cliente=' + encodeURIComponent(pontosCliente[0].cliente_exclusivo || '')
            + '&agencia=&campanha=' + encodeURIComponent('Checking Exclusivos')
            + '&situacao=' + encodeURIComponent('Exclusivo') + '&inicio=&fim=';
        pontosCliente.forEach(function(p) { ckQ += '&pontoIds[]=' + p.id; });
        var grupoHtml = '<tr class="grp-row no-print"><td colspan="7"><strong>'+esc(cli)+'</strong>'
            + ' <span class="grp-count">'+pontosCliente.length+' painel(is)</span>'
            + ' <a class="chk-link" href="/gestor/campanhas/checking?'+ckQ+'" target="_blank">📸 Gerar Checking</a></td></tr>';
        var linhasCliente = pontosCliente.map(function(p) {
            var comerc = parseInt(p.liberado_comercializacao) === 1
                ? '<span class="tag tag-liberado">🔓 Liberado p/ comercialização</span>'
                : '<span class="tag tag-preso">🔒 Bloqueado</span>';
            return '<tr><td>'+esc(p.cliente_exclusivo||'—')+'</td><td>'+esc(p.numero)+'</td><td>'+esc(p.logradouro)+'</td><td>'+esc(p.cidade)+'</td>'
                + '<td>'+esc(p.formato||'—')+'</td><td>'+esc(p.contato||'—')+'</td><td>'+comerc+'</td></tr>';
        }).join('');
        return grupoHtml + linhasCliente;
    }).join('');
    var html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
        + '<title>Relatório de Painéis Exclusivos — Impakto Mídia</title><style>'
        + 'body{font-family:Arial,Helvetica,sans-serif;padding:28px;color:#111}'
        + 'h1{font-size:1.15rem;margin:0 0 2px}'
        + '.sub{color:#666;font-size:0.78rem;margin:0 0 4px}'
        + 'table{width:100%;border-collapse:collapse;margin-top:10px}'
        + 'th,td{border:1px solid #ddd;padding:6px 8px;font-size:0.78rem;text-align:left}'
        + 'th{background:#f1f5f9}'
        + '.tag{display:inline-block;padding:2px 7px;border-radius:20px;font-size:0.65rem;font-weight:700;white-space:nowrap}'
        + '.tag-liberado{background:#78350f;color:#fef3c7}.tag-preso{background:#1e293b;color:#f8fafc}'
        + '.grp-row td{background:#f8fafc;padding:8px}'
        + '.grp-count{color:#666;font-size:0.72rem;margin-left:6px}'
        + '.chk-link{float:right;background:#7e22ce;color:#fff;text-decoration:none;padding:3px 10px;border-radius:6px;font-size:0.72rem;font-weight:700}'
        + '.chk-link:hover{background:#6b21a8}'
        + '@media print{ .no-print{display:none} }'
        + '</style></head><body>'
        + '<h1>🔒 Relatório Interno — Painéis Exclusivos</h1>'
        + '<p class="sub">Impakto Mídia · gerado em ' + new Date().toLocaleString('pt-BR') + ' · ' + exclusivos.length + ' painel(is)</p>'
        + '<table><thead><tr><th>Cliente exclusivo</th><th>Nº</th><th>Logradouro</th><th>Cidade</th><th>Formato</th><th>Contato</th><th>Comercialização</th></tr></thead>'
        + '<tbody>' + linhas + '</tbody></table>'
        + '</body></html>';
    var win = window.open('', '_blank');
    if (!win) { alert('Habilite pop-ups para gerar o relatório.'); return; }
    win.document.write(html);
    win.document.close();
    win.focus();
    setTimeout(function(){ win.print(); }, 300);
}

// ── PDF de apresentação dos painéis exclusivos (estrutura da pré-seleção) ──
function gerarPdfExclusivos() {
    var exclusivos = filtrar(PONTOS).filter(function(p){ return parseInt(p.exclusivo) === 1; });
    if (exclusivos.length === 0) {
        alert('Nenhum painel exclusivo encontrado com os filtros atuais.');
        return;
    }
    exclusivos = exclusivos.slice().sort(function(a,b) {
        return normalizar(a.cliente_exclusivo||'').localeCompare(normalizar(b.cliente_exclusivo||''));
    });
    var q = exclusivos.map(function(p){ return 'pontoIds[]=' + p.id; }).join('&');
    window.open('/gestor/pontos/exclusivos/pdf?' + q, '_blank');
}

// ── Filtrar / Ordenar ────────────────────────────────────────
function filtrar(lista) {
    var busca = normalizar(filtros.busca);
    return lista.filter(function(p) {
        if (filtros.regiao   && (p.regiao  ||'').trim() !== filtros.regiao)   return false;
        if (filtros.cidade   && (p.cidade  ||'').trim() !== filtros.cidade)   return false;
        if (filtros.cliente  && (p.cliente ||'').trim() !== filtros.cliente)  return false;
        if (filtros.situacao === '__exclusivo__') {
            if (parseInt(p.exclusivo) !== 1) return false;
        } else if (filtros.situacao && (p.situacao||'').trim() !== filtros.situacao) {
            return false;
        }
        if (filtros.corredor && (p.corredor||'').trim() !== filtros.corredor) return false;
        if (filtros.vencimento) {
            var hoje = new Date(); hoje.setHours(0,0,0,0);
            var fim  = p.fim_contrato ? new Date(p.fim_contrato) : null;
            if (filtros.vencimento === 'sem_prazo') {
                if (fim) return false;
            } else if (filtros.vencimento === 'vencido') {
                if (!fim || fim >= hoje) return false;
            } else {
                var dias = parseInt(filtros.vencimento, 10);
                var limite = new Date(hoje); limite.setDate(hoje.getDate() + dias);
                if (!fim || fim < hoje || fim > limite) return false;
            }
        }
        if (busca) {
            var campos = [p.numero,p.logradouro,p.descricao,p.cidade,p.regiao,p.cliente,p.agencia,p.corredor];
            return campos.some(function(c){ return normalizar(c).indexOf(busca) !== -1; });
        }
        return true;
    });
}
function ordenar(lista) {
    var dir = sortDir === 'asc' ? 1 : -1;
    return lista.slice().sort(function(a,b) {
        var va = a[sortCol], vb = b[sortCol];
        if (sortCol === 'numero' || sortCol === 'id') return ((parseInt(va)||0)-(parseInt(vb)||0))*dir;
        if (sortCol === 'fim_contrato') {
            if (!va&&!vb) return 0; if (!va) return 1; if (!vb) return -1;
            return (new Date(va)-new Date(vb))*dir;
        }
        va = normalizar(va||''); vb = normalizar(vb||'');
        return (va<vb?-1:va>vb?1:0)*dir;
    });
}

// ── Renderizar linha de ponto ────────────────────────────────
function renderLinha(p, busca) {
    var id  = String(p.id);
    var sel = selecao.has(id);
    var html = '<tr data-id="'+id+'"'+(sel?' class="selecionado"':'')+' onclick="toggleRow(event,\''+id+'\')">';
    html += '<td class="col-check no-print"><input type="checkbox" class="row-check"'+(sel?' checked':'')+' onclick="event.stopPropagation();toggleSel(\''+id+'\')" ></td>';
    html += '<td class="col-foto no-print" onclick="event.stopPropagation()">';
    if (p.foto) {
        html += '<div class="thumb-wrap"><img class="thumb-img" src="/'+esc(p.foto)+'" loading="lazy" onclick="abrirLb(\''+esc(p.foto)+'\')" onerror="this.parentElement.innerHTML=\'<span class=\\\'thumb-vazio\\\'>📷</span>\'"></div>';
    } else {
        html += '<div class="thumb-wrap"><span class="thumb-vazio">📷</span></div>';
    }
    html += '</td>';
    html += '<td class="col-num">'+highlight(p.numero,busca)+'</td>';
    html += '<td class="col-txt" title="'+esc(p.logradouro)+(p.descricao?' · '+esc(p.descricao):'')+'">'+highlight(p.logradouro,busca)+(p.descricao?'<span class="col-sub"> · '+highlight(p.descricao.substring(0,50),busca)+'</span>':'')+'</td>';
    html += '<td class="col-txt" title="'+esc(p.cidade)+'">'+highlight(p.cidade,busca)+'</td>';
    html += '<td class="col-txt" title="'+esc(p.cliente||'-')+(p.agencia?' · '+esc(p.agencia):'')+'">'+highlight(p.cliente||'—',busca)+(p.agencia?'<span class="col-sub"> · '+highlight(p.agencia,busca)+'</span>':'')+'</td>';
    html += '<td class="col-sit"><div class="sit-badges">'+badgeSit(p.situacao, p)+' '+badgeContrato(p.fim_contrato)+'</div>'+badgeExclusivo(p)+'</td>';
    html += '<td class="no-print" onclick="event.stopPropagation()" style="white-space:nowrap">';
    html += '<a href="/gestor/pontos/detalhes?id='+p.id+'" class="link-info">+Info</a>';
    html += '<a href="/gestor/pontos/editar?id='+p.id+'" class="link-editar" title="Editar">✏️</a>';
    html += '</td>';
    html += '</tr>';
    return html;
}

// ── Renderizar tabela agrupada por região ────────────────────
function renderTabela() {
    var lista = filtrar(PONTOS);
    var busca = filtros.busca;
    var temFiltro = Object.values(filtros).some(function(v){ return !!v; });

    var rc = document.getElementById('resultCount');
    if (temFiltro) {
        rc.innerHTML = '<strong style="color:var(--color-accent-primary)">'+lista.length+'</strong><span style="color:var(--color-text-muted);font-size:0.78rem"> resultados · '+PONTOS.length+' no total</span>';
    } else {
        rc.innerHTML = '<strong style="font-size:1rem">'+PONTOS.length+'</strong><span style="color:var(--color-text-muted);font-size:0.78rem"> pontos cadastrados</span>';
    }
    document.getElementById('btnLimpar').className = 'btn-limpar-filtros'+(temFiltro?' visible':'');

    if (!temFiltro) {
        listaVisivelAtual = [];
        document.getElementById('tabelaBody').innerHTML = '<tr class="empty-row"><td colspan="8">🔍 Use a busca ou um filtro acima para visualizar os pontos.</td></tr>';
        atualizarCart();
        return;
    }

    if (lista.length === 0) {
        listaVisivelAtual = [];
        document.getElementById('tabelaBody').innerHTML = '<tr class="empty-row"><td colspan="8">Nenhum ponto encontrado</td></tr>';
        atualizarCart();
        return;
    }
    listaVisivelAtual = lista;

    // Agrupar por região
    var grupos = {}, ordemGrupos = [];
    lista.forEach(function(p) {
        var reg = (p.regiao||'').trim() || 'Sem região';
        if (!grupos[reg]) { grupos[reg] = []; ordemGrupos.push(reg); }
        grupos[reg].push(p);
    });

    // Regiões em ordem alfabética, "Sem região" sempre por último
    ordemGrupos.sort(function(a, b) {
        if (a === 'Sem região') return 1;
        if (b === 'Sem região') return -1;
        return normalizar(a) < normalizar(b) ? -1 : normalizar(a) > normalizar(b) ? 1 : 0;
    });

    var html = '';
    ordemGrupos.forEach(function(reg) {
        var pts = ordenar(grupos[reg]);
        var nDisp = pts.filter(function(p){ return normalizar(p.situacao||'').indexOf('disponiv') === 0 && estaAVenda(p); }).length;

        // Cabeçalho do grupo
        html += '<tr class="group-header"><td colspan="8">';
        html += '<span class="group-reg">'+esc(reg)+'</span>';
        html += '<span class="group-count">'+pts.length+' ponto'+(pts.length>1?'s':'')+'</span>';
        if (nDisp > 0) html += '<span class="group-disp">'+nDisp+' disponív'+(nDisp>1?'eis':'el')+'</span>';
        html += '</td></tr>';

        pts.forEach(function(p) { html += renderLinha(p, busca); });
    });

    document.getElementById('tabelaBody').innerHTML = html;
    atualizarCart();
}

// ── Seleção / Cart ───────────────────────────────────────────
function toggleRow(e, id) {
    if (e.target.tagName==='A'||e.target.classList.contains('row-check')||e.target.classList.contains('thumb-img')) return;
    toggleSel(id);
}
function toggleSel(id) {
    var adicionando = !selecao.has(id);
    if (selecao.has(id)) selecao.delete(id);
    else selecao.add(id);
    localStorage.setItem(CART_KEY, JSON.stringify(Array.from(selecao)));
    var row = document.querySelector('tr[data-id="'+id+'"]');
    if (row) {
        row.classList.toggle('selecionado', selecao.has(id));
        var cb = row.querySelector('.row-check');
        if (cb) cb.checked = selecao.has(id);
    }
    atualizarCart();

    // ── Aviso de conflito ao adicionar ponto não-disponível ──
    if (adicionando) {
        var ponto = PONTOS.find(function(p){ return String(p.id) === String(id); });
        if (ponto) {
            var sit = (ponto.situacao||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'');
            if (sit === 'ocupado') {
                mostrarAviso('⚠️ Ponto ' + (ponto.numero||id) + ' está <strong>Ocupado</strong> por ' + esc(ponto.cliente||'outro cliente') + '. Considere remover da seleção.', 'laranja');
            } else if (sit === 'reservado') {
                mostrarAviso('⚠️ Ponto ' + (ponto.numero||id) + ' está <strong>Reservado</strong>. Pode haver conflito com outra proposta.', 'laranja');
            }
            if (parseInt(ponto.exclusivo) === 1 && parseInt(ponto.liberado_comercializacao) !== 1) {
                mostrarAviso('🔒 Ponto ' + (ponto.numero||id) + ' é <strong>EXCLUSIVO</strong> de ' + esc(ponto.cliente_exclusivo||'outro cliente') + ' e não será incluído na pré-seleção/PDF gerado para outros clientes.', 'laranja');
            }
        }
    }
}

function mostrarAviso(html, tipo) {
    var t = document.getElementById('pontoAviso');
    if (!t) {
        t = document.createElement('div');
        t.id = 'pontoAviso';
        t.style.cssText = 'position:fixed;bottom:5.5rem;right:1.5rem;z-index:9998;'
            +'max-width:340px;padding:0.7rem 1rem;border-radius:8px;'
            +'font-size:0.8rem;font-weight:600;line-height:1.4;'
            +'box-shadow:0 4px 16px rgba(0,0,0,0.18);'
            +'transform:translateY(80px);opacity:0;transition:all 0.3s ease;pointer-events:none;';
        document.body.appendChild(t);
    }
    t.innerHTML = html;
    t.style.background = tipo === 'laranja' ? '#fff3cd' : '#d4edda';
    t.style.border = tipo === 'laranja' ? '1px solid #ffc107' : '1px solid #28a745';
    t.style.color = tipo === 'laranja' ? '#856404' : '#155724';
    t.style.transform = 'translateY(0)';
    t.style.opacity = '1';
    clearTimeout(t._tmr);
    t._tmr = setTimeout(function() {
        t.style.transform = 'translateY(80px)';
        t.style.opacity = '0';
    }, 4500);
}
function toggleTodos(marcar) {
    listaVisivelAtual.forEach(function(p) {
        var id = String(p.id);
        if (marcar) selecao.add(id);
        else selecao.delete(id);
    });
    localStorage.setItem(CART_KEY, JSON.stringify(Array.from(selecao)));
    renderTabela();
}
function atualizarCart() {
    var n = selecao.size;
    document.getElementById('cartBadge').textContent = n;
    document.getElementById('selCount').textContent = n > 0 ? n+' selecionado'+(n>1?'s':'') : '';
    document.getElementById('cartBtn').className = 'cart-btn no-print'+(n>0?' visivel':'');

    // sincroniza checkbox master com os visíveis
    var visiveis = listaVisivelAtual;
    var chkAll = document.getElementById('checkAll');
    if (chkAll) {
        var todosSel = visiveis.length > 0 && visiveis.every(function(p){ return selecao.has(String(p.id)); });
        var algumSel = visiveis.some(function(p){  return selecao.has(String(p.id)); });
        chkAll.checked = todosSel;
        chkAll.indeterminate = !todosSel && algumSel;
    }
}

// ── Filtros / busca ──────────────────────────────────────────
var debTimer;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(debTimer);
    var v = this.value;
    document.getElementById('searchClear').className = 'search-clear'+(v?' visible':'');
    debTimer = setTimeout(function(){ filtros.busca = v; salvarFiltros(); renderTabela(); }, 120);
});
document.getElementById('searchClear').addEventListener('click', function() {
    document.getElementById('searchInput').value = '';
    this.className = 'search-clear';
    filtros.busca = '';
    salvarFiltros();
    renderTabela();
    document.getElementById('searchInput').focus();
});

var mapaFiltros = { filtroRegiao:'regiao', filtroCidade:'cidade', filtroCliente:'cliente', filtroSituacao:'situacao', filtroCorredor:'corredor', filtroVencimento:'vencimento' };
Object.keys(mapaFiltros).forEach(function(id) {
    document.getElementById(id).addEventListener('change', function() {
        filtros[mapaFiltros[id]] = this.value;
        this.className = 'filtro-select'+(this.value?' ativo':'');
        salvarFiltros();
        renderTabela();
    });
});
document.getElementById('btnLimpar').addEventListener('click', function() {
    filtros = { busca:'', regiao:'', cidade:'', cliente:'', situacao:'', corredor:'', vencimento:'' };
    document.getElementById('searchInput').value = '';
    document.getElementById('searchClear').className = 'search-clear';
    Object.keys(mapaFiltros).forEach(function(id) {
        document.getElementById(id).value = '';
        document.getElementById(id).className = 'filtro-select';
    });
    salvarFiltros();
    renderTabela();
});

// ── Restaurar filtros ao voltar para esta página (sessionStorage) ──
(function restaurarFiltros() {
    var salvo;
    try { salvo = JSON.parse(sessionStorage.getItem(FILTROS_KEY) || 'null'); } catch(e) { salvo = null; }
    if (!salvo) return;
    filtros = Object.assign({ busca:'', regiao:'', cidade:'', cliente:'', situacao:'', corredor:'', vencimento:'' }, salvo);
    document.getElementById('searchInput').value = filtros.busca;
    document.getElementById('searchClear').className = 'search-clear'+(filtros.busca?' visible':'');
    Object.keys(mapaFiltros).forEach(function(id) {
        var val = filtros[mapaFiltros[id]];
        document.getElementById(id).value = val;
        document.getElementById(id).className = 'filtro-select'+(val?' ativo':'');
    });
    renderTabela();
})();

// ── Ordenação ────────────────────────────────────────────────
document.querySelectorAll('.table thead th[data-col]').forEach(function(th) {
    th.addEventListener('click', function() {
        var col = this.getAttribute('data-col');
        sortDir = (sortCol===col&&sortDir==='asc') ? 'desc' : 'asc';
        sortCol = col;
        document.querySelectorAll('.table thead th').forEach(function(t){ t.classList.remove('sort-asc','sort-desc'); });
        this.classList.add('sort-'+sortDir);
        renderTabela();
    });
});

// ── Lightbox ─────────────────────────────────────────────────
function abrirLb(foto) {
    document.getElementById('lbImg').src = '/'+foto;
    document.getElementById('lbOverlay').classList.add('aberto');
}
function fecharLb() {
    document.getElementById('lbOverlay').classList.remove('aberto');
    document.getElementById('lbImg').src = '';
}
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey||e.metaKey)&&e.key==='k') { e.preventDefault(); document.getElementById('searchInput').focus(); }
    if (e.key==='Escape') { fecharLb(); fecharDropdownExcl(); }
});

// ── Dropdown de ações de exclusivos ──────────────────────────
function toggleDropdownExcl(e) {
    e.stopPropagation();
    var menu = document.getElementById('dropdownExclMenu');
    var abrir = !menu.classList.contains('aberto');
    menu.classList.toggle('aberto', abrir);
    document.getElementById('btnDropdownExcl').classList.toggle('aberto', abrir);
}
function fecharDropdownExcl() {
    document.getElementById('dropdownExclMenu').classList.remove('aberto');
    document.getElementById('btnDropdownExcl').classList.remove('aberto');
}
document.addEventListener('click', function(e) {
    var dd = document.getElementById('dropdownExcl');
    if (dd && !dd.contains(e.target)) fecharDropdownExcl();
});

// ── Push bar ─────────────────────────────────────────────────
function fecharPushBar() {
    var pb = document.getElementById('pushBar');
    if (pb) { pb.style.display = 'none'; ajustarAltura(); }
    sessionStorage.setItem('pushBarFechado', '1');
}
(function() {
    if (sessionStorage.getItem('pushBarFechado')) {
        var pb = document.getElementById('pushBar');
        if (pb) pb.style.display = 'none';
    }
})();

// ── Altura viewport ──────────────────────────────────────────
function ajustarAltura() {
    var header  = document.querySelector('.header');
    var pushBar = document.getElementById('pushBar');
    var used = (header ? header.offsetHeight : 60)
             + (pushBar && pushBar.style.display !== 'none' ? pushBar.offsetHeight : 0);
    document.getElementById('pontosPage').style.height = (window.innerHeight - used) + 'px';
}
ajustarAltura();
window.addEventListener('resize', ajustarAltura);

// ── Init ─────────────────────────────────────────────────────
renderTabela();
document.getElementById('searchInput').focus();
</script>

</body>
</html>
