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
           p.cliente, p.agencia, p.tipo, p.situacao, p.corredor, p.formato,
           COALESCE(
               (SELECT pf.caminho FROM ponto_fotos pf WHERE pf.ponto_id = p.id AND pf.principal = 1 LIMIT 1),
               p.foto
           ) AS foto,
           CASE
               WHEN p.fim_contrato IS NULL OR p.fim_contrato = '0000-00-00' OR p.fim_contrato = ''
               THEN NULL ELSE DATE(p.fim_contrato)
           END AS fim_contrato
    FROM pontos p
    WHERE p.ativo = 1 OR p.ativo IS NULL
    ORDER BY p.id DESC
";
$pontos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$regioes    = $pdo->query("SELECT DISTINCT regiao   FROM pontos WHERE regiao   IS NOT NULL AND regiao   != '' AND (ativo=1 OR ativo IS NULL) ORDER BY regiao"  )->fetchAll(PDO::FETCH_COLUMN);
$cidades    = $pdo->query("SELECT DISTINCT cidade   FROM pontos WHERE cidade   IS NOT NULL AND cidade   != '' AND (ativo=1 OR ativo IS NULL) ORDER BY cidade"  )->fetchAll(PDO::FETCH_COLUMN);
$clientes   = $pdo->query("SELECT DISTINCT cliente  FROM pontos WHERE cliente  IS NOT NULL AND cliente  != '' AND (ativo=1 OR ativo IS NULL) ORDER BY cliente" )->fetchAll(PDO::FETCH_COLUMN);
$corredores = $pdo->query("SELECT DISTINCT corredor FROM pontos WHERE corredor IS NOT NULL AND corredor != '' AND (ativo=1 OR ativo IS NULL) ORDER BY corredor")->fetchAll(PDO::FETCH_COLUMN);

$pontosJson = json_encode($pontos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
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

        @media print { .no-print { display:none !important; } body { overflow:auto; } }
    </style>
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

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
            </select>
            <select class="filtro-select" id="filtroCorredor">
                <option value="">Todos os corredores</option>
                <?php foreach ($corredores as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn-limpar-filtros" id="btnLimpar">✕ Limpar filtros</button>
        </div>
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:0.3rem;">
            <span id="resultCount"></span>
            <span id="selCount" style="font-size:0.75rem;color:var(--color-accent-primary);font-weight:700;"></span>
            <a href="/gestor/pontos/novo" class="btn-novo-ponto" style="margin-left:auto">+ Novo Ponto</a>
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
                        <th data-col="cidade" style="width:18%">Cidade / Região<span class="sort-icon"></span></th>
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
var PONTOS   = <?= $pontosJson ?>;
var CART_KEY = 'impakto_cart';
var selecao  = new Set(JSON.parse(localStorage.getItem(CART_KEY) || '[]'));
var sortCol  = 'id', sortDir = 'desc';
var filtros  = { busca:'', regiao:'', cidade:'', cliente:'', situacao:'', corredor:'' };
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
function badgeSit(sit) {
    if (!sit) return '<span class="badge-sit sit-outro">—</span>';
    var s = normalizar(sit);
    var cls = (s==='disponivel'||s==='disponível') ? 'sit-disponivel'
            : s==='ocupado'   ? 'sit-ocupado'
            : s==='reservado' ? 'sit-reservado'
            : s==='vencido'   ? 'sit-vencido'
            : s==='permuta'   ? 'sit-permuta'
            : s==='bisemana'  ? 'sit-bisemana' : 'sit-outro';
    return '<span class="badge-sit '+cls+'">'+esc(sit)+'</span>';
}
function badgeContrato(data) {
    if (!data) return '<span class="badge-contrato ctr-sem">Sem prazo</span>';
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

// ── Filtrar / Ordenar ────────────────────────────────────────
function filtrar(lista) {
    var busca = normalizar(filtros.busca);
    return lista.filter(function(p) {
        if (filtros.regiao   && (p.regiao  ||'').trim() !== filtros.regiao)   return false;
        if (filtros.cidade   && (p.cidade  ||'').trim() !== filtros.cidade)   return false;
        if (filtros.cliente  && (p.cliente ||'').trim() !== filtros.cliente)  return false;
        if (filtros.situacao && (p.situacao||'').trim() !== filtros.situacao) return false;
        if (filtros.corredor && (p.corredor||'').trim() !== filtros.corredor) return false;
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

// ── Renderizar tabela ────────────────────────────────────────
function renderTabela() {
    var resultado = ordenar(filtrar(PONTOS));
    var busca = filtros.busca;
    var temFiltro = Object.values(filtros).some(function(v){ return !!v; });

    var rc = document.getElementById('resultCount');
    if (temFiltro) {
        rc.innerHTML = '<strong style="color:var(--color-accent-primary)">'+resultado.length+'</strong><span style="color:var(--color-text-muted);font-size:0.78rem"> resultados · '+PONTOS.length+' no total</span>';
    } else {
        rc.innerHTML = '<strong style="font-size:1rem">'+PONTOS.length+'</strong><span style="color:var(--color-text-muted);font-size:0.78rem"> pontos cadastrados</span>';
    }
    document.getElementById('btnLimpar').className = 'btn-limpar-filtros'+(temFiltro?' visible':'');

    var html = '';
    if (resultado.length === 0) {
        html = '<tr class="empty-row"><td colspan="7">Nenhum ponto encontrado</td></tr>';
    } else {
        resultado.forEach(function(p) {
            var id  = String(p.id);
            var sel = selecao.has(id);
            html += '<tr data-id="'+id+'"'+(sel?' class="selecionado"':'')+' onclick="toggleRow(event,\''+id+'\')">';
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
            html += '<td class="col-txt" title="'+esc(p.cidade)+(p.regiao?' · '+esc(p.regiao):'')+'">'+highlight(p.cidade,busca)+(p.regiao?'<span class="col-sub"> · '+highlight(p.regiao,busca)+'</span>':'')+'</td>';
            html += '<td class="col-txt" title="'+esc(p.cliente||'-')+(p.agencia?' · '+esc(p.agencia):'')+'">'+highlight(p.cliente||'—',busca)+(p.agencia?'<span class="col-sub"> · '+highlight(p.agencia,busca)+'</span>':'')+'</td>';
            html += '<td style="white-space:nowrap">'+badgeSit(p.situacao)+' '+badgeContrato(p.fim_contrato)+'</td>';
            html += '<td class="no-print" onclick="event.stopPropagation()" style="white-space:nowrap">';
            html += '<a href="/gestor/pontos/detalhes?id='+p.id+'" class="link-info">+Info</a>';
            html += '<a href="/gestor/pontos/editar?id='+p.id+'" class="link-editar" title="Editar">✏️</a>';
            html += '</td>';
            html += '</tr>';
        });
    }
    document.getElementById('tabelaBody').innerHTML = html;
    atualizarCart();
}

// ── Seleção / Cart ───────────────────────────────────────────
function toggleRow(e, id) {
    if (e.target.tagName==='A'||e.target.classList.contains('row-check')||e.target.classList.contains('thumb-img')) return;
    toggleSel(id);
}
function toggleSel(id) {
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
}
function toggleTodos(marcar) {
    var visiveis = ordenar(filtrar(PONTOS));
    visiveis.forEach(function(p) {
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
    var visiveis = ordenar(filtrar(PONTOS));
    var chkAll = document.getElementById('checkAll');
    if (chkAll && visiveis.length > 0) {
        var todosSel = visiveis.every(function(p){ return selecao.has(String(p.id)); });
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
    debTimer = setTimeout(function(){ filtros.busca = v; renderTabela(); }, 120);
});
document.getElementById('searchClear').addEventListener('click', function() {
    document.getElementById('searchInput').value = '';
    this.className = 'search-clear';
    filtros.busca = '';
    renderTabela();
    document.getElementById('searchInput').focus();
});

var mapaFiltros = { filtroRegiao:'regiao', filtroCidade:'cidade', filtroCliente:'cliente', filtroSituacao:'situacao', filtroCorredor:'corredor' };
Object.keys(mapaFiltros).forEach(function(id) {
    document.getElementById(id).addEventListener('change', function() {
        filtros[mapaFiltros[id]] = this.value;
        this.className = 'filtro-select'+(this.value?' ativo':'');
        renderTabela();
    });
});
document.getElementById('btnLimpar').addEventListener('click', function() {
    filtros = { busca:'', regiao:'', cidade:'', cliente:'', situacao:'', corredor:'' };
    document.getElementById('searchInput').value = '';
    document.getElementById('searchClear').className = 'search-clear';
    Object.keys(mapaFiltros).forEach(function(id) {
        document.getElementById(id).value = '';
        document.getElementById(id).className = 'filtro-select';
    });
    renderTabela();
});

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
    if (e.key==='Escape') fecharLb();
});

// ── Altura viewport ──────────────────────────────────────────
function ajustarAltura() {
    var h = document.querySelector('.header');
    document.getElementById('pontosPage').style.height = (window.innerHeight-(h?h.offsetHeight:60))+'px';
}
ajustarAltura();
window.addEventListener('resize', ajustarAltura);

// ── Init ─────────────────────────────────────────────────────
renderTabela();
document.getElementById('searchInput').focus();
</script>

</body>
</html>
