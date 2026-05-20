<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
               (SELECT pf.caminho FROM ponto_fotos pf
                WHERE pf.ponto_id = p.id AND pf.principal = 1 LIMIT 1),
               p.foto
           ) AS foto,
           CASE
               WHEN p.fim_contrato IS NULL OR p.fim_contrato = '0000-00-00' OR p.fim_contrato = ''
               THEN NULL
               ELSE DATE(p.fim_contrato)
           END AS fim_contrato,
           p.id AS ultima_alteracao
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
    <link rel="stylesheet" href="/public/assets/css/pre-selecao.css">
    <link rel="stylesheet" href="/public/assets/css/form_ponto.css">
    <style>
        /* ── Full-viewport layout ── */
        body { overflow: hidden; }
        .pontos-page { display:flex; flex-direction:column; padding:0.75rem 1.5rem 0; overflow:hidden; box-sizing:border-box; }
        .pontos-controls { flex-shrink:0; }
        .pontos-layout { display:grid; grid-template-columns:1fr minmax(0,310px); gap:1rem; flex:1; min-height:0; overflow:hidden; margin-top:0.5rem; }
        .pontos-left { display:flex; flex-direction:column; min-height:0; overflow:hidden; }
        .table-container { flex:1; overflow-y:auto; min-height:0; }
        .sel-panel { overflow-y:auto; position:relative !important; top:0 !important; }

        /* ── Tabela ── */
        .col-check { width:36px; padding-right:0 !important; text-align:center; }
        .table tbody tr { cursor:pointer; }
        .table tbody tr.selecionado { background:#fff8f7 !important; box-shadow:inset 3px 0 0 var(--color-accent-primary); }
        .row-check { width:16px; height:16px; cursor:pointer; accent-color:var(--color-accent-primary); }

        /* ── Painel lateral ── */
        .sel-required { color:var(--color-accent-primary); }
        .sel-label-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:0.3rem; }
        .sel-label-row .sel-label { margin-bottom:0; }
        .sel-check-label { display:flex; align-items:center; gap:0.3rem; font-size:0.72rem; font-weight:600; color:var(--color-text-muted); cursor:pointer; user-select:none; }
        .sel-check-label input[type=checkbox] { accent-color:var(--color-accent-primary); width:13px; height:13px; cursor:pointer; }
        .sel-input:disabled { opacity:0.4; pointer-events:none; background:var(--color-bg-primary); }

        /* ── Thumbnail ── */
        .col-foto { width:38px; padding:2px 3px !important; }
        .thumb-wrap { width:32px; height:24px; border-radius:3px; overflow:hidden; background:#f0f0f0; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .thumb-img { width:100%; height:100%; object-fit:cover; cursor:zoom-in; transition:opacity 0.15s; }
        .thumb-img:hover { opacity:0.85; }
        .thumb-vazio { font-size:0.75rem; color:#ccc; }

        /* ── Lightbox ── */
        .lb-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.82); z-index:2000; align-items:center; justify-content:center; cursor:zoom-out; }
        .lb-overlay.aberto { display:flex; }
        .lb-img { max-width:90vw; max-height:88vh; border-radius:10px; box-shadow:0 20px 60px rgba(0,0,0,0.5); object-fit:contain; }
        .lb-info { position:fixed; bottom:1.5rem; left:50%; transform:translateX(-50%); background:rgba(0,0,0,0.7); color:white; padding:0.4rem 1rem; border-radius:20px; font-size:0.8rem; font-family:'Montserrat',sans-serif; white-space:nowrap; }

        /* ── Modal Resultado ── */
        .resultado-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:900; align-items:center; justify-content:center; }
        .resultado-overlay.aberto { display:flex; }
        .resultado-modal { background:white; border-radius:12px; width:92vw; max-width:1050px; max-height:88vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,0.3); }
        .resultado-modal-header { padding:1rem 1.5rem; border-bottom:1px solid var(--color-border); display:flex; align-items:center; justify-content:space-between; flex-shrink:0; gap:1rem; flex-wrap:wrap; }
        .resultado-modal-title { font-size:0.92rem; font-weight:800; color:var(--color-text-dark); flex:1; }
        .resultado-modal-actions { display:flex; gap:0.5rem; flex-shrink:0; }
        .resultado-modal-body { flex:1; overflow-y:auto; padding:1rem 1.5rem; }
        .resultado-modal-close { background:none; border:none; font-size:1.3rem; cursor:pointer; color:#999; padding:0; line-height:1; flex-shrink:0; }
        .resultado-modal-close:hover { color:#333; }

        @media (max-width:960px) { .pontos-layout { grid-template-columns:1fr; } }
        @media print { .no-print { display:none !important; } }
    </style>
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="pontos-page" id="pontosPage">

    <!-- ── Controles ── -->
    <div class="pontos-controls">
        <div class="search-bar no-print">
                <span class="search-icon">🔍</span>
                <input type="text" id="searchInput" placeholder="Buscar por número, logradouro, cidade, cliente..." autocomplete="off">
                <button class="search-clear" id="searchClear" title="Limpar">✕</button>
                <span class="search-kbd">Ctrl+K</span>
            </div>

            <div class="filtros-wrap no-print">
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
                </select>
                <select class="filtro-select" id="filtroCorredor">
                    <option value="">Todos os corredores</option>
                    <?php foreach ($corredores as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="filtro-select" id="filtroTipo">
                    <option value="">Todos os tipos</option>
                    <option value="Painel">Painel</option>
                    <option value="Outdoor">Outdoor</option>
                    <option value="Frontlight">Frontlight</option>
                    <option value="Painel LED">LED</option>
                </select>
                <button class="btn-limpar-filtros" id="btnLimpar">Limpar filtros</button>
            </div>

            <div class="result-bar no-print" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-top:0.4rem;">
                <span id="resultCount" style="flex:1;"></span>
                <span id="selCount" style="font-size:0.78rem;color:var(--color-accent-primary);font-weight:700;"></span>
                <a href="/gestor/pontos/novo" class="btn-novo-ponto">+ Novo Ponto</a>
            </div>
    </div><!-- /pontos-controls -->

    <!-- ── Layout principal ── -->
    <div class="pontos-layout">

        <!-- Coluna esquerda: tabela -->
        <div class="pontos-left">
            <div class="table-container">
                <table class="table" id="tabelaPontos">
                    <thead>
                        <tr>
                            <th class="col-check no-print"></th>
                            <th class="col-foto no-print"></th>
                            <th data-col="numero" style="width:44px">Nº<span class="sort-icon"></span></th>
                            <th data-col="logradouro">Logradouro<span class="sort-icon"></span></th>
                            <th data-col="cidade" style="width:130px">Cidade / Região<span class="sort-icon"></span></th>
                            <th data-col="cliente" style="width:110px">Cliente<span class="sort-icon"></span></th>
                            <th data-col="situacao" style="width:155px">Situação / Vencimento<span class="sort-icon"></span></th>
                            <th class="no-print" style="width:75px"></th>
                        </tr>
                    </thead>
                    <tbody id="tabelaBody"></tbody>
                </table>
            </div>
        </div><!-- /pontos-left -->

        <!-- Coluna direita: painel seleção -->
        <div class="sel-panel no-print">
            <div class="sel-header">
                <span class="sel-title">Selecionados</span>
                <span class="sel-badge" id="selBadge">0</span>
            </div>
            <div class="sel-form">

                <div class="sel-input-group">
                    <label class="sel-label">Cliente <span class="sel-required">*</span></label>
                    <input type="text" class="sel-input" id="selCliente" placeholder="Nome do cliente">
                </div>

                <div class="sel-input-group">
                    <div class="sel-label-row">
                        <label class="sel-label">Agência</label>
                        <label class="sel-check-label">
                            <input type="checkbox" id="clienteDireto" onchange="toggleClienteDireto()">
                            Cliente direto
                        </label>
                    </div>
                    <input type="text" class="sel-input" id="selAgencia" placeholder="Nome da agência (opcional)">
                </div>

                <div class="sel-input-group">
                    <div class="sel-label-row">
                        <label class="sel-label">Período da Campanha</label>
                        <label class="sel-check-label">
                            <input type="checkbox" id="semPeriodo" onchange="toggleSemPeriodo()">
                            Sem período
                        </label>
                    </div>
                    <div id="periodoDatas" style="display:flex;flex-direction:column;gap:0.3rem;">
                        <input type="date" class="sel-input" id="selDataInicio" placeholder="Início">
                        <input type="date" class="sel-input" id="selDataFim" placeholder="Fim">
                    </div>
                </div>

                <div class="sel-list" id="selList">
                    <div class="sel-list-empty">Nenhum ponto selecionado.<br>Clique nos pontos ao lado.</div>
                </div>

                <div class="sel-actions">
                    <button class="btn-gerar" id="btnGerar" disabled onclick="gerarProposta()">
                        Gerar Pré-Seleção
                    </button>
                    <button class="btn-limpar-sel" onclick="limparSelecao()">
                        Limpar seleção
                    </button>
                </div>

            </div>
        </div>

    </div><!-- /pontos-layout -->

</div><!-- /pontos-page -->

<!-- ===== MODAL RESULTADO ===== -->
<div class="resultado-overlay" id="resultadoOverlay">
    <div class="resultado-modal">
        <div class="resultado-modal-header">
            <div class="resultado-modal-title" id="resultadoTitulo"></div>
            <div class="resultado-modal-actions no-print">
                <button class="btn-resultado btn-print-r" onclick="window.print()">🖨️ Imprimir</button>
                <button class="btn-resultado btn-csv-r"   onclick="exportarCSV()">📊 CSV</button>
                <button class="btn-resultado btn-email-r" onclick="abrirEmail()">✉️ E-mail</button>
            </div>
            <button class="resultado-modal-close no-print" onclick="fecharResultado()">✕</button>
        </div>
        <div class="resultado-modal-body">
            <div id="resultadoTabela"></div>
        </div>
    </div>
</div>

<!-- ===== LIGHTBOX ===== -->
<div class="lb-overlay" id="lbOverlay" onclick="fecharLb()">
    <img class="lb-img" id="lbImg" src="" alt="">
    <div class="lb-info" id="lbInfo"></div>
</div>

<!-- ===== MODAL E-MAIL ===== -->
<div class="email-overlay" id="emailOverlay">
    <div class="email-modal">
        <div class="email-modal-header">
            <span class="email-modal-title">✉️ Texto para E-mail</span>
            <button class="email-modal-close" onclick="fecharEmail()">✕</button>
        </div>
        <textarea class="email-textarea" id="emailTexto" spellcheck="false"></textarea>
        <div class="email-modal-footer">
            <button class="btn-fechar-modal" onclick="fecharEmail()">Fechar</button>
            <button class="btn-copiar" id="btnCopiar" onclick="copiarEmail()">📋 Copiar</button>
        </div>
    </div>
</div>

<script>
var PONTOS      = <?= $pontosJson ?>;
var selecionados = new Set();
var sortCol     = 'ultima_alteracao';
var sortDir     = 'desc';
var filtros     = { busca:'', regiao:'', cidade:'', cliente:'', situacao:'', corredor:'', tipo:'' };
var pontosMap   = {};
PONTOS.forEach(function(p) { pontosMap[String(p.id)] = p; });

// ── Helpers ────────────────────────────────────────────────────
function esc(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function highlight(text, termo) {
    if (!termo || !text) return esc(text||'');
    var re = new RegExp('('+termo.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+')','gi');
    return esc(text).replace(re,'<mark>$1</mark>');
}
function normalizar(str) {
    return (str||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'');
}
function badgeSit(sit) {
    if (!sit) return '<span class="badge-sit sit-outro">-</span>';
    var s = normalizar(sit);
    var cls = (s==='disponivel'||s==='disponível') ? 'sit-disponivel'
            : s==='ocupado'    ? 'sit-ocupado'
            : s==='reservado'  ? 'sit-reservado'
            : s==='vencido'    ? 'sit-vencido'
            : s==='permuta'    ? 'sit-permuta'
            : s==='bisemana'   ? 'sit-bisemana' : 'sit-outro';
    return '<span class="badge-sit '+cls+'">'+esc(sit)+'</span>';
}
function badgeContrato(data) {
    if (!data) return '<span class="badge-contrato ctr-sem">Sem prazo</span>';
    var fim = new Date(data), hoje = new Date();
    hoje.setHours(0,0,0,0); fim.setHours(0,0,0,0);
    var dias = Math.round((fim - hoje) / 86400000);
    var mes  = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'][fim.getMonth()];
    var lbl  = mes + '/' + fim.getFullYear().toString().slice(2);
    if (dias < 0)   return '<div style="font-size:0.78rem;font-weight:600;color:#6b21a8;">'+lbl+'</div><span class="badge-contrato ctr-vencido">Vencido há '+Math.abs(dias)+'d</span>';
    if (dias <= 7)  return '<div style="font-size:0.78rem;font-weight:600;">'+lbl+'</div><span class="badge-contrato ctr-critico">'+dias+' dias</span>';
    if (dias <= 30) return '<div style="font-size:0.78rem;font-weight:600;">'+lbl+'</div><span class="badge-contrato ctr-urgente">'+dias+' dias</span>';
    if (dias <= 90) return '<div style="font-size:0.78rem;font-weight:600;">'+lbl+'</div><span class="badge-contrato ctr-atencao">'+Math.floor(dias/30)+'m</span>';
    return '<div style="font-size:0.78rem;font-weight:600;">'+lbl+'</div><span class="badge-contrato ctr-ok">'+Math.floor(dias/30)+'m</span>';
}

// ── Filtrar / Ordenar ──────────────────────────────────────────
function filtrar(lista) {
    var busca = normalizar(filtros.busca);
    return lista.filter(function(p) {
        if (filtros.regiao   && (p.regiao   ||'').trim() !== filtros.regiao)   return false;
        if (filtros.cidade   && (p.cidade   ||'').trim() !== filtros.cidade)   return false;
        if (filtros.cliente  && (p.cliente  ||'').trim() !== filtros.cliente)  return false;
        if (filtros.situacao && (p.situacao ||'').trim() !== filtros.situacao) return false;
        if (filtros.corredor && (p.corredor ||'').trim() !== filtros.corredor) return false;
        if (filtros.tipo     && (p.tipo     ||'').trim() !== filtros.tipo)     return false;
        if (busca) {
            var campos = [p.numero,p.logradouro,p.descricao,p.cidade,p.regiao,p.cliente,p.agencia,p.tipo,p.corredor];
            return campos.some(function(c){ return normalizar(c).indexOf(busca) !== -1; });
        }
        return true;
    });
}
function ordenar(lista) {
    var dir = sortDir === 'asc' ? 1 : -1;
    return lista.slice().sort(function(a,b) {
        var va = a[sortCol], vb = b[sortCol];
        if (sortCol === 'numero' || sortCol === 'ultima_alteracao') {
            return ((parseInt(va)||0) - (parseInt(vb)||0)) * dir;
        }
        if (sortCol === 'fim_contrato') {
            if (!va && !vb) return 0; if (!va) return 1; if (!vb) return -1;
            return (new Date(va) - new Date(vb)) * dir;
        }
        va = normalizar(va||''); vb = normalizar(vb||'');
        return (va < vb ? -1 : va > vb ? 1 : 0) * dir;
    });
}

// ── Renderizar tabela ──────────────────────────────────────────
function renderTabela() {
    var resultado = ordenar(filtrar(PONTOS));
    var tbody = document.getElementById('tabelaBody');
    var busca  = filtros.busca;
    var temFiltro = Object.values(filtros).some(function(v){ return !!v; });

    var rc = document.getElementById('resultCount');
    if (temFiltro) {
        rc.innerHTML = '<strong style="font-size:1.1rem;color:var(--color-accent-primary)">'+resultado.length+'</strong>'
            +'<span style="color:var(--color-text-muted);font-weight:400;"> resultados &nbsp;·&nbsp; '+PONTOS.length+' no total</span>';
    } else {
        rc.innerHTML = '<strong style="font-size:1.1rem;color:var(--color-text-dark)">'+PONTOS.length+'</strong>'
            +'<span style="color:var(--color-text-muted);font-weight:400;"> pontos cadastrados</span>';
    }
    document.getElementById('btnLimpar').className = 'btn-limpar-filtros'+(temFiltro?' visible':'');

    if (resultado.length === 0) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="7"><div class="empty-icon">🔍</div><div>Nenhum ponto encontrado</div></td></tr>';
        return;
    }

    var html = '';
    resultado.forEach(function(p) {
        var id  = String(p.id);
        var sel = selecionados.has(id);
        html += '<tr data-id="'+id+'"'+(sel?' class="selecionado"':'')+' onclick="toggleRow(event,\''+id+'\')">';
        html += '<td class="col-check no-print"><input type="checkbox" class="row-check"'+(sel?' checked':'')+' onclick="event.stopPropagation();toggleSelecao(\''+id+'\')"></td>';
        html += '<td class="col-foto no-print" onclick="event.stopPropagation()">';
        if (p.foto) {
            html += '<div class="thumb-wrap"><img class="thumb-img" src="/'+esc(p.foto)+'" loading="lazy" alt="Ponto '+esc(p.numero)+'" onclick="abrirLb(\''+esc(p.foto)+'\',\'Ponto '+esc(p.numero)+' – '+esc(p.logradouro)+'\')" onerror="this.parentElement.innerHTML=\'<span class=\\\'thumb-vazio\\\'>📷</span>\'"></div>';
        } else {
            html += '<div class="thumb-wrap"><span class="thumb-vazio">📷</span></div>';
        }
        html += '</td>';
        html += '<td class="col-num">'+highlight(p.numero,busca)+'</td>';
        html += '<td><div class="col-local-main">'+highlight(p.logradouro,busca)+'</div>';
        if (p.descricao) html += '<div class="col-local-sub">'+highlight(p.descricao.substring(0,60)+(p.descricao.length>60?'...':''),busca)+'</div>';
        html += '</td>';
        html += '<td class="col-regiao"><div>'+highlight(p.cidade,busca)+'</div>';
        if (p.regiao) html += '<div class="col-local-sub">'+highlight(p.regiao,busca)+'</div>';
        html += '</td>';
        html += '<td><div class="col-cliente-main">'+highlight(p.cliente||'-',busca)+'</div>';
        if (p.agencia) html += '<div class="col-cliente-sub">'+highlight(p.agencia,busca)+'</div>';
        html += '</td>';
        html += '<td>'+badgeSit(p.situacao)+'<div style="margin-top:3px">'+badgeContrato(p.fim_contrato)+'</div></td>';
        html += '<td class="no-print" onclick="event.stopPropagation()" style="white-space:nowrap">';
        html += '<a href="/gestor/pontos/detalhes?id='+encodeURIComponent(p.id)+'" class="link-info">+Info</a>';
        html += '<a href="/gestor/pontos/editar?id='+encodeURIComponent(p.id)+'" class="link-editar" title="Editar ponto">✏️</a>';
        html += '</td>';
        html += '</tr>';
    });
    tbody.innerHTML = html;
}

// ── Seleção ────────────────────────────────────────────────────
function toggleRow(e, id) {
    if (e.target.tagName === 'A' || e.target.classList.contains('row-check')) return;
    toggleSelecao(id);
}
function toggleSelecao(id) {
    if (selecionados.has(id)) selecionados.delete(id);
    else selecionados.add(id);
    var row = document.querySelector('tr[data-id="'+id+'"]');
    if (row) {
        row.classList.toggle('selecionado', selecionados.has(id));
        var cb = row.querySelector('.row-check');
        if (cb) cb.checked = selecionados.has(id);
    }
    renderSelPanel();
}
function limparSelecao() {
    selecionados.clear();
    renderTabela();
    renderSelPanel();
    fecharResultado();
}

// ── Painel lateral ─────────────────────────────────────────────
function renderSelPanel() {
    var count = selecionados.size;
    document.getElementById('selBadge').textContent = count;
    document.getElementById('btnGerar').disabled = count === 0;
    document.getElementById('selCount').textContent = count > 0
        ? count + ' selecionado' + (count > 1 ? 's' : '') : '';

    var list = document.getElementById('selList');
    if (count === 0) {
        list.innerHTML = '<div class="sel-list-empty">Clique nos pontos para selecionar</div>';
        return;
    }
    var html = '';
    selecionados.forEach(function(id) {
        var p = pontosMap[id];
        if (!p) return;
        html += '<div class="sel-list-item">';
        html += '<span class="sel-list-num">'+esc(p.numero)+'</span>';
        html += '<span class="sel-list-local" title="'+esc(p.logradouro)+'">'+esc(p.logradouro)+'</span>';
        html += '<button class="sel-remove" onclick="toggleSelecao(\''+id+'\')" title="Remover">✕</button>';
        html += '</div>';
    });
    list.innerHTML = html;
}

// ── Toggles dos checkboxes ─────────────────────────────────────
function toggleClienteDireto() {
    var cb    = document.getElementById('clienteDireto');
    var input = document.getElementById('selAgencia');
    input.disabled = cb.checked;
    input.value    = '';
    input.placeholder = cb.checked ? 'Cliente direto (sem agência)' : 'Nome da agência (opcional)';
}
function toggleSemPeriodo() {
    var cb    = document.getElementById('semPeriodo');
    var ini   = document.getElementById('selDataInicio');
    var fim   = document.getElementById('selDataFim');
    ini.disabled = fim.disabled = cb.checked;
    if (cb.checked) { ini.value = ''; fim.value = ''; }
}
function formatarData(val) {
    if (!val) return '';
    var p = val.split('-');
    return p[2] + '/' + p[1] + '/' + p[0];
}
function getPeriodo() {
    if (document.getElementById('semPeriodo').checked) return 'Sem período definido';
    var ini = formatarData(document.getElementById('selDataInicio').value);
    var fim = formatarData(document.getElementById('selDataFim').value);
    if (ini && fim) return ini + ' até ' + fim;
    if (ini) return 'A partir de ' + ini;
    return '';
}
function getAgencia() {
    if (document.getElementById('clienteDireto').checked) return 'Cliente direto';
    return document.getElementById('selAgencia').value.trim();
}

// ── Gerar proposta ─────────────────────────────────────────────
function gerarProposta() {
    if (selecionados.size === 0) return;
    var cliente = document.getElementById('selCliente').value.trim() || 'Cliente';
    var periodo = getPeriodo();
    var agencia = getAgencia();

    var titulo = '📋 Pré-Seleção — ' + cliente + (agencia ? ' / ' + agencia : '') + (periodo ? ' · ' + periodo : '');
    document.getElementById('resultadoTitulo').textContent = titulo;

    var lista = [];
    selecionados.forEach(function(id){ if(pontosMap[id]) lista.push(pontosMap[id]); });
    lista.sort(function(a,b){ return ((parseInt(a.numero)||0) - (parseInt(b.numero)||0)); });

    var html = '<table class="res-table" id="tabelaResultado">';
    html += '<thead><tr><th>Nº</th><th>Logradouro</th><th>Cidade / Região</th><th>Tipo / Formato</th><th>Situação</th><th class="no-print"></th></tr></thead><tbody>';
    lista.forEach(function(p) {
        html += '<tr>';
        html += '<td class="res-num">'+esc(p.numero)+'</td>';
        html += '<td><div style="font-weight:600;">'+esc(p.logradouro)+'</div>'+(p.descricao?'<div class="res-sub">'+esc(p.descricao)+'</div>':'')+'</td>';
        html += '<td><div>'+esc(p.cidade||'-')+'</div>'+(p.regiao?'<div class="res-sub">'+esc(p.regiao)+'</div>':'')+'</td>';
        html += '<td><div>'+esc(p.tipo||'-')+'</div>'+(p.formato?'<div class="res-sub">'+esc(p.formato)+'</div>':'')+'</td>';
        html += '<td>'+badgeSit(p.situacao)+'</td>';
        html += '<td class="no-print"><a href="/gestor/pontos/detalhes?id='+encodeURIComponent(p.id)+'&view=publico" class="link-info-btn" target="_blank">+Info</a></td>';
        html += '</tr>';
    });
    html += '</tbody></table>';

    document.getElementById('resultadoTabela').innerHTML = html;
    document.getElementById('resultadoOverlay').classList.add('aberto');
}
function fecharResultado() {
    document.getElementById('resultadoOverlay').classList.remove('aberto');
}

// ── Exportar CSV ───────────────────────────────────────────────
function exportarCSV() {
    var lista = [];
    selecionados.forEach(function(id){ if(pontosMap[id]) lista.push(pontosMap[id]); });
    lista.sort(function(a,b){ return ((parseInt(a.numero)||0)-(parseInt(b.numero)||0)); });
    var cliente = document.getElementById('selCliente').value.trim() || 'proposta';
    var csv = 'Nº,Logradouro,Descrição,Cidade,Região,Tipo,Formato,Situação\n';
    lista.forEach(function(p) {
        csv += [p.numero,p.logradouro,p.descricao,p.cidade,p.regiao,p.tipo,p.formato,p.situacao]
            .map(function(v){ return '"'+(v||'').replace(/"/g,'""')+'"'; }).join(',') + '\n';
    });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob(['﻿'+csv],{type:'text/csv;charset=utf-8;'}));
    a.download = 'proposta_'+cliente.replace(/\s+/g,'_')+'_'+new Date().toISOString().slice(0,10)+'.csv';
    a.click();
}

// ── E-mail ─────────────────────────────────────────────────────
function abrirEmail() {
    var lista = [];
    selecionados.forEach(function(id){ if(pontosMap[id]) lista.push(pontosMap[id]); });
    lista.sort(function(a,b){ return ((parseInt(a.numero)||0)-(parseInt(b.numero)||0)); });
    var cliente = document.getElementById('selCliente').value.trim() || '[Cliente]';
    var agencia = getAgencia();
    var periodo = getPeriodo() || '[Período]';
    var linhas  = lista.map(function(p,i) {
        var local = p.logradouro + (p.cidade?', '+p.cidade:'') + (p.regiao?' – '+p.regiao:'');
        var tipo  = p.tipo ? ' ('+p.tipo+(p.formato?' '+p.formato:'')+')' : '';
        var url   = window.location.origin + '/gestor/pontos/detalhes?id=' + p.id;
        return (i+1)+'. Ponto '+(p.numero||'')+' – '+local+tipo+'\n   '+url;
    });
    var dest = cliente + (agencia && agencia !== 'Cliente direto' ? ' / ' + agencia : '');
    var txt = 'Prezado(a),\n\nEncaminhamos a pré-seleção de mídia exterior para '+dest
        +' referente ao período de '+periodo+'.\n\nPONTOS SELECIONADOS:\n\n'+linhas.join('\n')
        +'\n\nEstamos à disposição para quaisquer esclarecimentos.\n\nAtenciosamente,\nImpakto Mídia';
    document.getElementById('emailTexto').value = txt;
    document.getElementById('emailOverlay').classList.add('aberto');
}
function fecharEmail() { document.getElementById('emailOverlay').classList.remove('aberto'); }
function copiarEmail() {
    navigator.clipboard.writeText(document.getElementById('emailTexto').value).then(function() {
        var btn = document.getElementById('btnCopiar');
        btn.textContent = '✅ Copiado!';
        btn.classList.add('copiado');
        setTimeout(function(){ btn.textContent = '📋 Copiar'; btn.classList.remove('copiado'); }, 2000);
    });
}

// ── Filtros / busca ────────────────────────────────────────────
var debounceTimer;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(debounceTimer);
    var val = this.value;
    document.getElementById('searchClear').className = 'search-clear'+(val?' visible':'');
    debounceTimer = setTimeout(function(){ filtros.busca = val; renderTabela(); }, 120);
});
document.getElementById('searchClear').addEventListener('click', function() {
    document.getElementById('searchInput').value = '';
    this.className = 'search-clear';
    filtros.busca = '';
    renderTabela();
    document.getElementById('searchInput').focus();
});

var mapaFiltros = { filtroRegiao:'regiao', filtroCidade:'cidade', filtroCliente:'cliente',
                    filtroSituacao:'situacao', filtroCorredor:'corredor', filtroTipo:'tipo' };
Object.keys(mapaFiltros).forEach(function(id) {
    document.getElementById(id).addEventListener('change', function() {
        filtros[mapaFiltros[id]] = this.value;
        this.className = 'filtro-select'+(this.value?' ativo':'');
        renderTabela();
    });
});
document.getElementById('btnLimpar').addEventListener('click', function() {
    filtros = { busca:'', regiao:'', cidade:'', cliente:'', situacao:'', corredor:'', tipo:'' };
    document.getElementById('searchInput').value = '';
    document.getElementById('searchClear').className = 'search-clear';
    Object.keys(mapaFiltros).forEach(function(id) {
        document.getElementById(id).value = '';
        document.getElementById(id).className = 'filtro-select';
    });
    renderTabela();
});

// ── Ordenação ──────────────────────────────────────────────────
document.querySelectorAll('.table thead th[data-col]').forEach(function(th) {
    th.addEventListener('click', function() {
        var col = this.getAttribute('data-col');
        sortDir = (sortCol === col && sortDir === 'asc') ? 'desc' : 'asc';
        sortCol = col;
        document.querySelectorAll('.table thead th').forEach(function(t){ t.classList.remove('sort-asc','sort-desc'); });
        this.classList.add('sort-'+sortDir);
        renderTabela();
    });
});

// ── Lightbox ───────────────────────────────────────────────────
function abrirLb(foto, info) {
    document.getElementById('lbImg').src  = '/' + foto;
    document.getElementById('lbInfo').textContent = info;
    document.getElementById('lbOverlay').classList.add('aberto');
}
function fecharLb() {
    document.getElementById('lbOverlay').classList.remove('aberto');
    document.getElementById('lbImg').src = '';
}

document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey||e.metaKey) && e.key==='k') { e.preventDefault(); document.getElementById('searchInput').focus(); }
    if (e.key === 'Escape') { fecharEmail(); fecharLb(); fecharResultado(); }
});

// ── Altura full-viewport ───────────────────────────────────────
function ajustarAltura() {
    var header = document.querySelector('.header');
    var h = window.innerHeight - (header ? header.offsetHeight : 60);
    document.getElementById('pontosPage').style.height = h + 'px';
}
ajustarAltura();
window.addEventListener('resize', ajustarAltura);

// ── Init ───────────────────────────────────────────────────────
renderTabela();
renderSelPanel();
document.getElementById('searchInput').focus();
</script>

</body>
</html>
