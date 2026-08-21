<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$paginaAtual = 'pre_selecao';

try {
    require_once __DIR__ . '/../../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// Buscar todos os pontos para o seletor
$pontos = $pdo->query("
    SELECT id, numero, logradouro, descricao, cidade, regiao, tipo, situacao,
           cliente, agencia, formato,
           CASE WHEN fim_contrato IS NULL OR fim_contrato = '0000-00-00'
                THEN NULL ELSE DATE(fim_contrato) END AS fim_contrato
    FROM pontos
    WHERE ativo = 1 OR ativo IS NULL
    ORDER BY numero ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Listas para filtros
$cidades   = $pdo->query("SELECT DISTINCT cidade  FROM pontos WHERE cidade  IS NOT NULL AND cidade  != '' AND (ativo=1 OR ativo IS NULL) ORDER BY cidade" )->fetchAll(PDO::FETCH_COLUMN);
$regioes   = $pdo->query("SELECT DISTINCT regiao  FROM pontos WHERE regiao  IS NOT NULL AND regiao  != '' AND (ativo=1 OR ativo IS NULL) ORDER BY regiao" )->fetchAll(PDO::FETCH_COLUMN);
$tipos     = $pdo->query("SELECT DISTINCT tipo    FROM pontos WHERE tipo    IS NOT NULL AND tipo    != '' AND (ativo=1 OR ativo IS NULL) ORDER BY tipo"   )->fetchAll(PDO::FETCH_COLUMN);

$pontosJson = json_encode($pontos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pré-Seleção · Impakto</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css?v=2">
    <link rel="stylesheet" href="/public/assets/css/pre-selecao.css">
</head>
<body>

<?php require __DIR__ . '/../../layouts/_nav.php'; ?>

<div class="container" style="padding-bottom:2rem;">

    <div class="welcome" style="margin-bottom:1rem;">
        <h2>🎯 Pré-Seleção de Pontos</h2>
        <p>Filtre e selecione os pontos para gerar a proposta.</p>
    </div>

    <div class="ps-layout">

        <!-- ===== PAINEL ESQUERDO — LISTA ===== -->
        <div>
            <div class="ps-panel">
                <!-- Cabeçalho -->
                <div class="ps-panel-header">
                    <span class="ps-panel-title">Pontos disponíveis</span>
                    <button onclick="selecionarTodosVisiveis()" style="font-family:inherit;font-size:0.72rem;font-weight:700;border:1px solid var(--color-border);background:white;padding:3px 10px;border-radius:6px;cursor:pointer;color:var(--color-text-muted);">
                        Selec. visíveis
                    </button>
                </div>

                <!-- Busca -->
                <div class="ps-search">
                    <span style="color:var(--color-text-muted);">🔍</span>
                    <input type="text" id="psBusca" placeholder="Buscar número, logradouro, cidade, cliente..."
                           oninput="filtrarLista()" autocomplete="off">
                    <button id="psBuscaClear" onclick="limparBusca()" style="background:none;border:none;cursor:pointer;color:var(--color-text-muted);display:none;font-size:14px;padding:0;">✕</button>
                </div>

                <!-- Filtros -->
                <div class="ps-filters">
                    <select class="ps-select" id="fCidade" onchange="filtrarLista()">
                        <option value="">Todas cidades</option>
                        <?php foreach ($cidades as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="ps-select" id="fRegiao" onchange="filtrarLista()">
                        <option value="">Todas regiões</option>
                        <?php foreach ($regioes as $r): ?>
                        <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="ps-select" id="fSituacao" onchange="filtrarLista()">
                        <option value="">Todas situações</option>
                        <?php
                        $situacoes = $pdo->query("SELECT DISTINCT situacao FROM pontos WHERE situacao IS NOT NULL AND situacao != '' AND (ativo=1 OR ativo IS NULL) ORDER BY situacao")->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($situacoes as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="ps-select" id="fTipo" onchange="filtrarLista()">
                        <option value="">Todos os tipos</option>
                        <?php foreach ($tipos as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Contador -->
                <div class="ps-count-bar" id="psCountBar">
                    <?= count($pontos) ?> pontos
                </div>

                <!-- Lista -->
                <div class="ps-list" id="psLista">
                    <?php foreach ($pontos as $p):
                        $sit = strtolower(trim($p['situacao'] ?? ''));
                        if ($sit === 'disponível' || $sit === 'disponivel') $cls = 'sit-d';
                        elseif ($sit === 'ocupado') $cls = 'sit-o';
                        elseif ($sit === 'reservado') $cls = 'sit-r';
                        elseif ($sit === 'vencido') $cls = 'sit-v';
                        else $cls = 'sit-x';
                    ?>
                    <div class="ps-item"
                         data-id="<?= $p['id'] ?>"
                         data-num="<?= htmlspecialchars($p['numero']) ?>"
                         data-local="<?= htmlspecialchars($p['logradouro'] ?? '') ?>"
                         data-cidade="<?= htmlspecialchars($p['cidade'] ?? '') ?>"
                         data-regiao="<?= htmlspecialchars($p['regiao'] ?? '') ?>"
                         data-tipo="<?= htmlspecialchars($p['tipo'] ?? '') ?>"
                         data-sit="<?= htmlspecialchars($p['situacao'] ?? '') ?>"
                         data-cliente="<?= htmlspecialchars($p['cliente'] ?? '') ?>"
                         data-agencia="<?= htmlspecialchars($p['agencia'] ?? '') ?>"
                         data-fim="<?= htmlspecialchars($p['fim_contrato'] ?? '') ?>"
                         onclick="toggleSelecionado(this)">
                        <div class="ps-item-check"></div>
                        <div class="ps-item-num"><?= htmlspecialchars($p['numero']) ?></div>
                        <div class="ps-item-info">
                            <div class="ps-item-local"><?= htmlspecialchars($p['logradouro'] ?? '') ?></div>
                            <div class="ps-item-meta"><?= htmlspecialchars($p['cidade'] ?? '') ?><?= !empty($p['regiao']) ? ' · ' . htmlspecialchars($p['regiao']) : '' ?><?= !empty($p['tipo']) ? ' · ' . htmlspecialchars($p['tipo']) : '' ?></div>
                        </div>
                        <span class="ps-item-sit <?= $cls ?>"><?= htmlspecialchars($p['situacao'] ?? '') ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div id="psVazio" class="ps-empty" style="display:none;">Nenhum ponto encontrado.</div>
                </div>
            </div>
        </div>

        <!-- ===== PAINEL DIREITO — SELEÇÃO ===== -->
        <div class="sel-panel">
            <div class="sel-header">
                <span class="sel-title">Selecionados</span>
                <span class="sel-badge" id="selBadge">0</span>
            </div>

            <div class="sel-form">
                <!-- Dados do cliente -->
                <div class="sel-input-group">
                    <label class="sel-label">Cliente *</label>
                    <input type="text" class="sel-input" id="selCliente" placeholder="Nome do cliente" autocomplete="off">
                </div>
                <div class="sel-input-group">
                    <label class="sel-label">Agência</label>
                    <input type="text" class="sel-input" id="selAgencia" placeholder="Nome da agência (opcional)" autocomplete="off">
                </div>
                <div class="sel-input-group">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.3rem;">
                        <label class="sel-label" style="margin:0;">Período da Campanha</label>
                        <label style="display:flex;align-items:center;gap:4px;font-size:0.72rem;color:var(--color-text-muted);font-weight:600;cursor:pointer;">
                            <input type="checkbox" id="semPeriodo" onchange="togglePeriodo(this)" style="cursor:pointer;">
                            Sem período
                        </label>
                    </div>
                    <div id="camposPeriodo" style="display:flex;align-items:center;gap:6px;">
                        <input type="date" class="sel-input" id="selDataInicio" style="flex:1;">
                        <span style="font-size:0.75rem;color:var(--color-text-muted);flex-shrink:0;">até</span>
                        <input type="date" class="sel-input" id="selDataFim" style="flex:1;">
                    </div>
                    <div id="labelSemPeriodo" style="display:none;font-size:0.8rem;color:var(--color-text-muted);font-weight:600;padding:0.4rem 0;">
                        — Sem período definido
                    </div>
                </div>

                <!-- Lista de selecionados -->
                <div class="sel-list" id="selLista">
                    <div class="sel-list-empty" id="selVazio">Nenhum ponto selecionado.<br>Clique nos pontos ao lado.</div>
                </div>

                <!-- Ações -->
                <div class="sel-actions">
                    <button class="btn-gerar" id="btnGerar" onclick="gerarPreSelecao()" disabled>
                        Gerar Pré-Seleção
                    </button>
                    <button class="btn-limpar-sel" onclick="limparSelecao()">
                        Limpar seleção
                    </button>
                </div>
            </div>
        </div>
    </div><!-- /ps-layout -->

    <!-- ===== SEÇÃO DE RESULTADOS ===== -->
    <div class="resultado-section" id="resultadoSection" style="display:none;">
        <div class="resultado-header">
            <div>
                <div class="resultado-title">📋 Pré-Seleção: <span id="resCliente"></span></div>
                <div style="font-size:0.78rem;color:var(--color-text-muted);margin-top:2px;">
                    <span id="resAgencia"></span>
                    <span id="resQtd"></span>
                    <span id="resPeriodo"></span>
                </div>
            </div>
            <div class="resultado-actions">
                <button class="btn-resultado btn-email-r" onclick="gerarEmail()">📧 Gerar E-mail</button>
                <button class="btn-resultado btn-print-r" onclick="imprimirResultado()">🖨 Imprimir</button>
                <button class="btn-resultado btn-csv-r" onclick="exportCSV()">⬇ CSV</button>
                <button class="btn-resultado btn-pdf-r" onclick="exportPDF()">⬇ PDF</button>
            </div>
        </div>

        <div class="table-container">
            <table class="res-table" id="resTable">
                <thead>
                    <tr>
                        <th>Nº</th>
                        <th>Logradouro</th>
                        <th>Cidade / Região</th>
                        <th>Tipo</th>
                        <th>Situação</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="resTbody"></tbody>
            </table>
        </div>
        <div style="background:#fff8f0;border:1px solid #f39c12;border-left:4px solid #f39c12;border-radius:6px;padding:8px 14px;margin-top:12px;font-size:0.8rem;color:#7a4700;font-weight:600;">
            ⏳ Estes pontos estão pré-reservados pelo prazo de 72 horas a partir da data desta proposta.
        </div>
    </div>

</div><!-- /container -->

<!-- ===== MODAL E-MAIL ===== -->
<div class="email-overlay" id="emailOverlay" onclick="if(event.target===this)fecharEmail()">
    <div class="email-modal">
        <div class="email-modal-header">
            <span class="email-modal-title">📧 Texto para E-mail</span>
            <button class="email-modal-close" onclick="fecharEmail()">✕</button>
        </div>
        <textarea class="email-textarea" id="emailTexto" readonly></textarea>
        <div class="email-modal-footer">
            <button class="btn-fechar-modal" onclick="fecharEmail()">Fechar</button>
            <button class="btn-copiar" id="btnCopiar" onclick="copiarEmail()">📋 Copiar texto</button>
        </div>
    </div>
</div>

<script>
var PONTOS    = <?= $pontosJson ?>;
var selecionados = {}; // id => objeto ponto

// ===== FILTRAR LISTA =====
function normalizar(s) {
    return (s||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
}

function filtrarLista() {
    var busca   = normalizar(document.getElementById('psBusca').value);
    var cidade  = document.getElementById('fCidade').value;
    var regiao  = document.getElementById('fRegiao').value;
    var situacao= document.getElementById('fSituacao').value;
    var tipo    = document.getElementById('fTipo').value;
    var items   = document.querySelectorAll('.ps-item');
    var total   = 0;

    // Mostrar/ocultar botão limpar busca
    document.getElementById('psBuscaClear').style.display = busca ? 'block' : 'none';

    items.forEach(function(item) {
        var ok = true;
        if (busca) {
            var campos = [item.dataset.num, item.dataset.local, item.dataset.cidade, item.dataset.cliente, item.dataset.agencia];
            var encontrou = campos.some(function(c){ return normalizar(c).indexOf(busca) !== -1; });
            if (!encontrou) ok = false;
        }
        if (cidade   && normalizar(item.dataset.cidade) !== normalizar(cidade))   ok = false;
        if (regiao   && normalizar(item.dataset.regiao) !== normalizar(regiao))   ok = false;
        if (situacao) {
            var sitNorm = normalizar(situacao);
            var itemSit = normalizar(item.dataset.sit);
            if (itemSit.indexOf(sitNorm) === -1) ok = false;
        }
        if (tipo     && normalizar(item.dataset.tipo)   !== normalizar(tipo))     ok = false;

        item.style.display = ok ? '' : 'none';
        if (ok) total++;
    });

    document.getElementById('psCountBar').textContent = total + (total === 1 ? ' ponto' : ' pontos');
    document.getElementById('psVazio').style.display = total === 0 ? 'block' : 'none';
}

function limparBusca() {
    document.getElementById('psBusca').value = '';
    document.getElementById('psBuscaClear').style.display = 'none';
    filtrarLista();
    document.getElementById('psBusca').focus();
}

// ===== SELECIONAR / DESSELECIONAR =====
function toggleSelecionado(el) {
    var id = el.dataset.id;
    if (selecionados[id]) {
        delete selecionados[id];
        el.classList.remove('selecionado');
    } else {
        selecionados[id] = {
            id:      id,
            num:     el.dataset.num,
            local:   el.dataset.local,
            cidade:  el.dataset.cidade,
            regiao:  el.dataset.regiao,
            tipo:    el.dataset.tipo,
            sit:     el.dataset.sit,
            cliente: el.dataset.cliente,
            agencia: el.dataset.agencia,
            fim:     el.dataset.fim
        };
        el.classList.add('selecionado');
    }
    atualizarPainel();
}

function selecionarTodosVisiveis() {
    var items = document.querySelectorAll('.ps-item');
    items.forEach(function(el) {
        if (el.style.display === 'none') return;
        var id = el.dataset.id;
        if (!selecionados[id]) {
            selecionados[id] = {
                id: id, num: el.dataset.num, local: el.dataset.local,
                cidade: el.dataset.cidade, regiao: el.dataset.regiao,
                tipo: el.dataset.tipo, sit: el.dataset.sit,
                cliente: el.dataset.cliente, agencia: el.dataset.agencia, fim: el.dataset.fim
            };
            el.classList.add('selecionado');
        }
    });
    atualizarPainel();
}

function removerSelecionado(id) {
    delete selecionados[id];
    var el = document.querySelector('.ps-item[data-id="' + id + '"]');
    if (el) el.classList.remove('selecionado');
    atualizarPainel();
}

function limparSelecao() {
    selecionados = {};
    document.querySelectorAll('.ps-item.selecionado').forEach(function(el){ el.classList.remove('selecionado'); });
    atualizarPainel();
    document.getElementById('resultadoSection').style.display = 'none';
}

// ===== ATUALIZAR PAINEL DIREITO =====
function atualizarPainel() {
    var ids    = Object.keys(selecionados);
    var count  = ids.length;
    document.getElementById('selBadge').textContent = count;
    document.getElementById('btnGerar').disabled    = count === 0;

    var lista = document.getElementById('selLista');
    var vazio = document.getElementById('selVazio');

    if (count === 0) {
        lista.innerHTML = '<div class="sel-list-empty" id="selVazio">Nenhum ponto selecionado.<br>Clique nos pontos ao lado.</div>';
        return;
    }

    var html = '';
    ids.forEach(function(id) {
        var p = selecionados[id];
        html += '<div class="sel-list-item">'
            + '<span class="sel-list-num">' + esc(p.num) + '</span>'
            + '<span class="sel-list-local">' + esc(p.local) + '</span>'
            + '<button class="sel-remove" onclick="removerSelecionado(\'' + id + '\')" title="Remover">✕</button>'
            + '</div>';
    });
    lista.innerHTML = html;
}

// ===== GERAR RESULTADO =====
function togglePeriodo(cb) {
    var campos = document.getElementById('camposPeriodo');
    var label  = document.getElementById('labelSemPeriodo');
    if (cb.checked) {
        campos.style.display = 'none';
        label.style.display  = 'block';
        document.getElementById('selDataInicio').value = '';
        document.getElementById('selDataFim').value    = '';
    } else {
        campos.style.display = 'flex';
        label.style.display  = 'none';
    }
}

function gerarPreSelecao() {
    var cliente = document.getElementById('selCliente').value.trim();
    if (!cliente) {
        document.getElementById('selCliente').focus();
        document.getElementById('selCliente').style.borderColor = '#e34c3e';
        return;
    }
    document.getElementById('selCliente').style.borderColor = '';

    var agencia    = document.getElementById('selAgencia').value.trim();
    var dataInicio = document.getElementById('selDataInicio').value;
    var dataFim    = document.getElementById('selDataFim').value;
    var isSemPeriodo = document.getElementById('semPeriodo').checked;
    var ids        = Object.keys(selecionados);

    function fmtDate(d) {
        if (!d) return '';
        var p = d.split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
    }
    var periodo = '';
    if (isSemPeriodo) {
        periodo = 'Sem período definido';
    } else {
        if (dataInicio && dataFim)  periodo = fmtDate(dataInicio) + ' a ' + fmtDate(dataFim);
        else if (dataInicio)        periodo = 'A partir de ' + fmtDate(dataInicio);
        else if (dataFim)           periodo = 'Até ' + fmtDate(dataFim);
    }

    document.getElementById('resCliente').textContent  = cliente;
    document.getElementById('resAgencia').textContent  = agencia ? agencia + ' · ' : '';
    document.getElementById('resQtd').textContent      = ids.length + ' ponto' + (ids.length > 1 ? 's' : '') + (periodo ? ' · ' : '');
    document.getElementById('resPeriodo').textContent  = periodo ? '📅 ' + periodo : '';

    var tbody = document.getElementById('resTbody');
    var html  = '';

    ids.forEach(function(id) {
        var p = selecionados[id];
        var sit = (p.sit||'').toLowerCase().replace(/\s+/g,'');
        var badgeCls = 'sit-x';
        if (sit === 'disponível' || sit === 'disponivel') badgeCls = 'sit-d';
        else if (sit === 'ocupado')   badgeCls = 'sit-o';
        else if (sit === 'reservado') badgeCls = 'sit-r';
        else if (sit === 'vencido')   badgeCls = 'sit-v';

        var venc = '-';
        if (p.fim) {
            var d = new Date(p.fim);
            var meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
            venc = meses[d.getMonth()] + '/' + d.getFullYear().toString().slice(2);
        }

        html += '<tr>'
            + '<td class="res-num">' + esc(p.num) + '</td>'
            + '<td><div style="font-weight:600">' + esc(p.local) + '</div></td>'
            + '<td><div>' + esc(p.cidade) + '</div>'
            + (p.regiao ? '<div class="res-sub">' + esc(p.regiao) + '</div>' : '') + '</td>'
            + '<td>' + esc(p.tipo||'-') + '</td>'
            + '<td><span class="badge-sit ' + badgeCls + '">' + esc(p.sit||'-') + '</span></td>'
            + '<td><a href="' + window.location.origin + '/gestor/pontos/detalhes?id=' + esc(p.id) + '&view=publico" target="_blank" class="link-info-btn">+Info</a></td>'
            + '</tr>';
    });

    tbody.innerHTML = html;
    document.getElementById('resultadoSection').style.display = 'block';
    document.getElementById('resultadoSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ===== EXPORTAÇÕES =====
function exportCSV() {
    var table = document.getElementById('resTable');
    if (!table) return;
    var csv = '';
    table.querySelectorAll('tr').forEach(function(row) {
        var cells = [];
        row.querySelectorAll('th,td').forEach(function(c) {
            var t = c.innerText.trim().replace(/\n/g,' ');
            if (t.indexOf(',') !== -1 || t.indexOf('"') !== -1) t = '"' + t.replace(/"/g,'""') + '"';
            cells.push(t);
        });
        csv += cells.join(',') + '\n';
    });
    var blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'pre_selecao_' + new Date().toISOString().slice(0,10) + '.csv';
    link.click();
}

function imprimirResultado() {
    window.print();
}

function exportPDF() {
    var cliente = document.getElementById('resCliente').textContent;
    var agencia = document.getElementById('resAgencia').textContent;
    var qtd     = document.getElementById('resQtd').textContent;
    var periodo = document.getElementById('resPeriodo').textContent;
    var content = document.getElementById('resTable').outerHTML;
    var logoUrl = window.location.origin + '/public/assets/img/barra.png';
    var win = window.open('', '_blank');
    win.document.write('<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
        + '<title>Pré-Seleção — ' + cliente + '</title>'
        + '<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">'
        + '<style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:Montserrat,sans-serif;font-size:11px;color:#1f2736;padding:24px}'
        + 'table{width:100%;border-collapse:collapse;margin-top:16px}'
        + 'th{background:#e34c3e;color:white;padding:6px 10px;font-size:10px;text-align:left}'
        + 'td{padding:6px 10px;border-bottom:1px solid #e9ecef;font-size:10px}'
        + '.res-num{font-weight:800;color:#e34c3e}'
        + '.res-sub{font-size:9px;color:#7f8c8d;margin-top:1px}'
        + '.badge-sit{display:inline-block;padding:1px 6px;border-radius:8px;font-size:9px;font-weight:700}'
        + '.sit-d{background:#dcfce7;color:#166534}.sit-o{background:#fee2e2;color:#991b1b}'
        + '.sit-r{background:#ffedd5;color:#9a3412}.sit-v{background:#f3e8ff;color:#6b21a8}.sit-x{background:#f1f5f9;color:#475569}'
        + '.link-info-btn{display:inline-block;padding:1px 6px;border:1px solid #e34c3e;border-radius:4px;color:#e34c3e;font-size:9px;font-weight:700;text-decoration:none}'
        + '.header-pdf{border-bottom:2px solid #e34c3e;padding-bottom:12px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center}'
        + '.logo-img{height:40px}'
        + '.aviso-72h{background:#fff8f0;border:1px solid #f39c12;border-left:4px solid #f39c12;border-radius:6px;padding:8px 12px;margin-top:16px;font-size:10px;color:#7a4700;font-weight:600}'
        + '</style></head><body>'
        + '<div class="header-pdf">'
        + '<img src="' + logoUrl + '" class="logo-img" alt="Impakto Mídia">'
        + '<div style="text-align:right">'
        + '<div style="font-size:13px;font-weight:800;color:#1f2736">Pré-Seleção de Pontos</div>'
        + '<div style="font-size:10px;color:#7f8c8d;margin-top:3px">Cliente: <strong>' + cliente + '</strong>'
        + (agencia ? ' · ' + agencia : '') + '</div>'
        + '<div style="font-size:10px;color:#7f8c8d;margin-top:1px">' + qtd + (periodo ? ' ' + periodo : '') + ' · ' + new Date().toLocaleDateString("pt-BR") + '</div>'
        + '</div>'
        + '</div>'
        + content
        + '<div class="aviso-72h">⏳ Estes pontos estão pré-reservados pelo prazo de 72 horas a partir da data desta proposta.</div>'
        + '</body></html>');
    win.document.close();
    setTimeout(function(){ win.print(); }, 600);
}

// ===== EMAIL =====
function gerarEmail() {
    var cliente    = document.getElementById('resCliente').textContent.trim();
    var agencia    = document.getElementById('resAgencia').textContent.trim().replace(' · ', '');
    var periodo    = document.getElementById('resPeriodo').textContent.trim().replace('📅 ', '');
    var ids     = Object.keys(selecionados);
    var data    = new Date().toLocaleDateString('pt-BR', {day:'2-digit', month:'long', year:'numeric'});

    var linhas = '';
    ids.forEach(function(id, i) {
        var p = selecionados[id];
        var infoUrl = window.location.origin + '/ponto?n=' + p.num;
        linhas += (i + 1) + '. Ponto ' + p.num
            + ' — ' + p.local
            + ' | ' + p.cidade + (p.regiao ? ' (' + p.regiao + ')' : '')
            + ' | ' + (p.tipo || 'Outdoor')
            + '\n   📋 Informações: ' + infoUrl + '\n\n';
    });

    var texto =
        'Prezado(a) ' + cliente + (agencia ? ' / ' + agencia : '') + ',\n\n'
        + 'Segue abaixo nossa pré-seleção de pontos de mídia exterior para sua campanha.\n\n'
        + '──────────────────────────────────────\n'
        + 'PRÉ-SELEÇÃO DE PONTOS — IMPAKTO MÍDIA\n'
        + 'Data: ' + data + '\n'
        + (periodo ? 'Período: ' + periodo + '\n' : '')
        + 'Total de pontos: ' + ids.length + '\n'
        + '──────────────────────────────────────\n\n'
        + linhas
        + '──────────────────────────────────────\n'
        + '⏳ IMPORTANTE: Estes pontos estão pré-reservados\n'
        + 'pelo prazo de 72 horas a partir desta proposta.\n'
        + '──────────────────────────────────────\n\n'
        + 'Para visualizar a foto e localização de cada ponto,\n'
        + 'clique no link 🔗 ao lado de cada item.\n\n'
        + 'Ficamos à disposição para qualquer dúvida.\n\n'
        + 'Atenciosamente,\n'
        + 'Equipe Impakto Mídia OOH\n'
        + 'www.impaktomidia.com.br';

    document.getElementById('emailTexto').value = texto;
    document.getElementById('emailOverlay').classList.add('aberto');
    document.getElementById('btnCopiar').textContent = '📋 Copiar texto';
    document.getElementById('btnCopiar').classList.remove('copiado');
}

function fecharEmail() {
    document.getElementById('emailOverlay').classList.remove('aberto');
}

function copiarEmail() {
    var ta = document.getElementById('emailTexto');
    ta.select();
    document.execCommand('copy');
    var btn = document.getElementById('btnCopiar');
    btn.textContent = '✅ Copiado!';
    btn.classList.add('copiado');
    setTimeout(function(){ btn.textContent = '📋 Copiar texto'; btn.classList.remove('copiado'); }, 2500);
}

// ===== UTIL =====
function esc(s) {
    return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Ctrl+K foca na busca
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.getElementById('psBusca').focus();
    }
});

document.getElementById('psBusca').focus();
</script>

</body>
</html>