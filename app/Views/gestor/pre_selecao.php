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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pré-Seleção - Impakto Mídia</title>
    <link rel="icon" href="/public/assets/img/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
    <style>
        .ps-page { max-width:1100px; margin:0 auto; padding:1.5rem 1.5rem 3rem; }

        /* ── Header da página ── */
        .ps-titulo {
            display:flex; align-items:center; gap:1rem;
            margin-bottom:1.5rem; flex-wrap:wrap;
        }
        .ps-titulo h1 { font-size:1.3rem; font-weight:800; color:var(--color-text-dark); margin:0; flex:1; }
        .ps-volta { display:flex; align-items:center; gap:0.4rem; color:var(--color-text-muted); font-size:0.82rem; font-weight:600; text-decoration:none; }
        .ps-volta:hover { color:var(--color-accent-primary); }

        /* ── Layout 2 colunas ── */
        .ps-layout { display:grid; grid-template-columns:1fr 320px; gap:1.5rem; align-items:start; }
        @media(max-width:860px) { .ps-layout { grid-template-columns:1fr; } }

        /* ── Tabela de pontos ── */
        .ps-card { background:white; border:1px solid var(--color-border); border-radius:10px; overflow:hidden; }
        .ps-card-header { padding:0.75rem 1rem; background:var(--color-bg-primary); border-bottom:1px solid var(--color-border); display:flex; align-items:center; justify-content:space-between; }
        .ps-card-title { font-size:0.8rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:0.5px; }
        .ps-empty-state { padding:3rem 1rem; text-align:center; color:var(--color-text-muted); }
        .ps-empty-state .icon { font-size:2.5rem; margin-bottom:0.75rem; }
        .ps-empty-state p { font-size:0.9rem; margin-bottom:1rem; }
        .ps-table { width:100%; border-collapse:collapse; font-size:0.82rem; }
        .ps-table th { background:var(--color-bg-primary); padding:0.45rem 0.75rem; font-size:0.68rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:0.4px; border-bottom:1.5px solid var(--color-border); text-align:left; }
        .ps-table td { padding:0.55rem 0.75rem; border-bottom:1px solid var(--color-border); vertical-align:middle; }
        .ps-table tbody tr:last-child td { border-bottom:none; }
        .ps-table tbody tr:hover { background:#fafafa; }
        .ps-num { font-weight:800; color:var(--color-accent-primary); }
        .ps-local { font-weight:600; }
        .ps-sub { font-size:0.72rem; color:var(--color-text-muted); margin-top:1px; }
        .ps-remove { background:none; border:none; color:#ccc; font-size:1rem; cursor:pointer; padding:2px 6px; border-radius:4px; }
        .ps-remove:hover { background:#fee2e2; color:#991b1b; }

        /* ── Badge ── */
        .badge-sit { display:inline-block; padding:2px 8px; border-radius:20px; font-size:0.65rem; font-weight:700; text-transform:uppercase; white-space:nowrap; }
        .sit-disponivel { background:#dcfce7; color:#166534; }
        .sit-ocupado    { background:#fee2e2; color:#991b1b; }
        .sit-reservado  { background:#ffedd5; color:#9a3412; }
        .sit-vencido    { background:#f3e8ff; color:#6b21a8; }
        .sit-permuta    { background:#ede9fe; color:#4c1d95; }
        .sit-bisemana   { background:#cffafe; color:#164e63; }
        .sit-outro      { background:#f1f5f9; color:#475569; }

        /* ── Painel formulário ── */
        .ps-form-card { background:white; border:1px solid var(--color-border); border-radius:10px; overflow:hidden; position:sticky; top:1rem; }
        .ps-form-header { padding:0.75rem 1rem; background:var(--color-bg-primary); border-bottom:1px solid var(--color-border); }
        .ps-form-title { font-size:0.8rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:0.5px; }
        .ps-form-body { padding:1rem; }
        .ps-field { margin-bottom:0.75rem; }
        .ps-field label { display:block; font-size:0.68rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:0.4px; margin-bottom:0.3rem; }
        .ps-field input[type=text], .ps-field input[type=date] {
            width:100%; font-family:'Montserrat',sans-serif; font-size:0.82rem;
            border:1px solid var(--color-border); border-radius:6px;
            padding:0.45rem 0.65rem; color:var(--color-text-dark);
            box-sizing:border-box; transition:border-color 0.15s;
        }
        .ps-field input:focus { outline:none; border-color:var(--color-accent-primary); }
        .ps-field input:disabled { opacity:0.45; background:var(--color-bg-primary); }
        .ps-field-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:0.3rem; }
        .ps-field-row label { margin-bottom:0; }
        .ps-check-label { display:flex; align-items:center; gap:0.3rem; font-size:0.72rem; font-weight:600; color:var(--color-text-muted); cursor:pointer; }
        .ps-check-label input { accent-color:var(--color-accent-primary); }
        .ps-date-row { display:flex; gap:0.4rem; align-items:center; }
        .ps-date-row input { flex:1; }
        .ps-date-sep { font-size:0.72rem; color:var(--color-text-muted); }
        .ps-divider { height:1px; background:var(--color-border); margin:0.75rem 0; }
        .btn-gerar {
            width:100%; padding:0.7rem; background:var(--color-accent-primary); color:white;
            border:none; border-radius:8px; font-family:'Montserrat',sans-serif;
            font-size:0.88rem; font-weight:700; cursor:pointer; transition:opacity 0.15s;
            margin-bottom:0.4rem;
        }
        .btn-gerar:hover { opacity:0.9; }
        .btn-gerar:disabled { opacity:0.4; cursor:not-allowed; }
        .btn-limpar-sel {
            width:100%; padding:0.45rem; background:none; color:var(--color-text-muted);
            border:1px solid var(--color-border); border-radius:6px;
            font-family:'Montserrat',sans-serif; font-size:0.78rem; font-weight:600;
            cursor:pointer; transition:all 0.15s;
        }
        .btn-limpar-sel:hover { border-color:var(--color-accent-primary); color:var(--color-accent-primary); }

        /* ── Resultado ── */
        .ps-resultado { margin-top:1.5rem; background:white; border:1px solid var(--color-border); border-radius:10px; overflow:hidden; display:none; }
        .ps-resultado.visivel { display:block; }
        .ps-resultado-header { padding:0.75rem 1rem 0.75rem 1.25rem; background:var(--color-bg-primary); border-bottom:1px solid var(--color-border); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.5rem; }
        .ps-resultado-titulo { font-size:0.9rem; font-weight:800; color:var(--color-text-dark); }
        .ps-resultado-actions { display:flex; gap:0.5rem; }
        .btn-acao { padding:0.35rem 0.875rem; border-radius:6px; font-size:0.75rem; font-weight:700; cursor:pointer; border:1.5px solid; font-family:'Montserrat',sans-serif; transition:all 0.15s; text-decoration:none; }
        .btn-imprimir { color:#3498db; border-color:#3498db; background:white; }
        .btn-imprimir:hover { background:#3498db; color:white; }
        .btn-csv     { color:#27ae60; border-color:#27ae60; background:white; }
        .btn-csv:hover { background:#27ae60; color:white; }
        .btn-email   { color:#6c3483; border-color:#6c3483; background:white; }
        .btn-email:hover { background:#6c3483; color:white; }
        .ps-resultado-body { padding:1rem 1.25rem; overflow-x:auto; }
        .res-table { width:100%; border-collapse:collapse; font-size:0.82rem; }
        .res-table th { background:var(--color-accent-primary); color:white; padding:0.5rem 0.75rem; text-align:left; font-size:0.72rem; font-weight:700; }
        .res-table td { padding:0.55rem 0.75rem; border-bottom:1px solid var(--color-border); }
        .res-table tbody tr:hover { background:#fafafa; }
        .res-num { font-weight:800; color:var(--color-accent-primary); }
        .res-sub { font-size:0.72rem; color:var(--color-text-muted); }
        .link-info-btn { display:inline-block; padding:3px 10px; border:1.5px solid var(--color-accent-primary); border-radius:6px; color:var(--color-accent-primary); font-size:0.72rem; font-weight:700; text-decoration:none; }
        .link-info-btn:hover { background:var(--color-accent-primary); color:white; }

        /* ── Modal E-mail ── */
        .email-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; }
        .email-overlay.aberto { display:flex; }
        .email-modal { background:white; border-radius:12px; padding:1.5rem; width:680px; max-width:95vw; max-height:90vh; display:flex; flex-direction:column; gap:1rem; box-shadow:0 20px 60px rgba(0,0,0,0.3); }
        .email-modal-header { display:flex; align-items:center; justify-content:space-between; }
        .email-modal-title { font-size:1rem; font-weight:800; }
        .email-modal-close { background:none; border:none; font-size:1.2rem; cursor:pointer; color:#999; }
        .email-textarea { width:100%; min-height:360px; font-family:Calibri,Arial,sans-serif; font-size:14px; border:1px solid #ddd; border-radius:8px; padding:1rem; resize:vertical; line-height:1.7; box-sizing:border-box; }
        .email-modal-footer { display:flex; gap:0.75rem; justify-content:flex-end; }
        .btn-copiar { padding:0.6rem 1.25rem; background:#6c3483; color:white; border:none; border-radius:8px; font-family:inherit; font-size:0.85rem; font-weight:700; cursor:pointer; }
        .btn-copiar.copiado { background:#27ae60; }
        .btn-fechar-modal { padding:0.6rem 1rem; background:none; color:#666; border:1px solid #ddd; border-radius:8px; font-family:inherit; font-size:0.85rem; cursor:pointer; }

        @media print { .no-print { display:none !important; } .ps-resultado { display:block !important; } }
    </style>
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="ps-page">

    <div class="ps-titulo">
        <a href="/gestor/pontos" class="ps-volta no-print">← Voltar aos Pontos</a>
        <h1>🛒 Pré-Seleção</h1>
    </div>

    <div class="ps-layout">

        <!-- Coluna esquerda: pontos selecionados -->
        <div>
            <div class="ps-card">
                <div class="ps-card-header">
                    <span class="ps-card-title">Pontos selecionados</span>
                    <span id="psCount" style="font-size:0.78rem;font-weight:700;color:var(--color-accent-primary)"></span>
                </div>
                <div id="psLista">
                    <div class="ps-empty-state">
                        <div class="icon">🛒</div>
                        <p>Nenhum ponto selecionado.<br>Volte à lista e marque os pontos desejados.</p>
                        <a href="/gestor/pontos" class="btn-gerar" style="display:inline-block;width:auto;padding:0.6rem 1.5rem;text-decoration:none">← Ir para Pontos</a>
                    </div>
                </div>
            </div>

            <!-- Resultado gerado -->
            <div class="ps-resultado" id="psResultado">
                <div class="ps-resultado-header">
                    <div class="ps-resultado-titulo" id="psTitulo"></div>
                    <div class="ps-resultado-actions no-print">
                        <button class="btn-acao btn-imprimir" onclick="window.print()">🖨️ Imprimir</button>
                        <button class="btn-acao btn-csv"     onclick="exportarCSV()">📊 CSV</button>
                        <button class="btn-acao btn-email"   onclick="abrirEmail()">✉️ E-mail</button>
                    </div>
                </div>
                <div class="ps-resultado-body">
                    <div id="psTabelaResultado"></div>
                </div>
            </div>
        </div>

        <!-- Coluna direita: formulário -->
        <div class="ps-form-card no-print">
            <div class="ps-form-header">
                <div class="ps-form-title">Dados da Proposta</div>
            </div>
            <div class="ps-form-body">

                <div class="ps-field">
                    <label>Cliente <span style="color:var(--color-accent-primary)">*</span></label>
                    <input type="text" id="psCliente" placeholder="Nome do cliente">
                </div>

                <div class="ps-field">
                    <div class="ps-field-row">
                        <label>Agência</label>
                        <label class="ps-check-label">
                            <input type="checkbox" id="clienteDireto" onchange="toggleClienteDireto()"> Cliente direto
                        </label>
                    </div>
                    <input type="text" id="psAgencia" placeholder="Nome da agência (opcional)">
                </div>

                <div class="ps-field">
                    <div class="ps-field-row">
                        <label>Período</label>
                        <label class="ps-check-label">
                            <input type="checkbox" id="semPeriodo" onchange="toggleSemPeriodo()"> Sem período
                        </label>
                    </div>
                    <div class="ps-date-row" id="periodoRow">
                        <input type="date" id="psDataInicio">
                        <span class="ps-date-sep">até</span>
                        <input type="date" id="psDataFim">
                    </div>
                </div>

                <div class="ps-divider"></div>

                <button class="btn-gerar" id="btnGerar" onclick="gerarPreSelecao()" disabled>
                    Gerar Pré-Seleção
                </button>
                <button class="btn-limpar-sel" onclick="limparTudo()">
                    🗑️ Limpar seleção
                </button>

            </div>
        </div>

    </div>
</div>

<!-- Modal E-mail -->
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
var CART_KEY = 'impakto_cart';
var selecao  = JSON.parse(localStorage.getItem(CART_KEY) || '[]'); // array de IDs string
var pontosData = {}; // preenchido via AJAX

function esc(str) {
    return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
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
function formatarData(val) {
    if (!val) return '';
    var p = val.split('-');
    return p[2]+'/'+p[1]+'/'+p[0];
}
function getPeriodo() {
    if (document.getElementById('semPeriodo').checked) return 'Sem período definido';
    var ini = formatarData(document.getElementById('psDataInicio').value);
    var fim = formatarData(document.getElementById('psDataFim').value);
    if (ini && fim) return ini+' até '+fim;
    if (ini) return 'A partir de '+ini;
    return '';
}
function getAgencia() {
    if (document.getElementById('clienteDireto').checked) return 'Cliente direto';
    return document.getElementById('psAgencia').value.trim();
}

// ── Carregar dados dos pontos via PHP JSON ────────────────────
function carregarPontos() {
    if (selecao.length === 0) {
        renderLista();
        return;
    }
    // Busca dados dos pontos selecionados via fetch
    fetch('/gestor/pontos/dados?ids='+selecao.join(','))
        .then(function(r){ return r.json(); })
        .then(function(data) {
            data.forEach(function(p){ pontosData[String(p.id)] = p; });
            renderLista();
        })
        .catch(function() {
            // fallback: mostra IDs sem dados
            renderLista();
        });
}

function renderLista() {
    var lista = document.getElementById('psLista');
    var count = document.getElementById('psCount');
    var n = selecao.length;
    count.textContent = n > 0 ? n+' ponto'+(n>1?'s':'') : '';
    document.getElementById('btnGerar').disabled = n === 0;

    if (n === 0) {
        lista.innerHTML = '<div class="ps-empty-state"><div class="icon">🛒</div><p>Nenhum ponto selecionado.<br>Volte à lista e marque os pontos desejados.</p><a href="/gestor/pontos" class="btn-gerar" style="display:inline-block;width:auto;padding:0.6rem 1.5rem;text-decoration:none">← Ir para Pontos</a></div>';
        return;
    }

    var html = '<table class="ps-table"><thead><tr><th style="width:46px">Nº</th><th>Logradouro</th><th style="width:130px">Cidade / Região</th><th style="width:80px">Situação</th><th style="width:30px" class="no-print"></th></tr></thead><tbody>';
    selecao.forEach(function(id) {
        var p = pontosData[id] || { id:id, numero:'#'+id, logradouro:'Carregando...', cidade:'', regiao:'', situacao:'' };
        html += '<tr>';
        html += '<td class="ps-num">'+esc(p.numero)+'</td>';
        html += '<td><div class="ps-local">'+esc(p.logradouro)+'</div>'+(p.descricao?'<div class="ps-sub">'+esc(p.descricao)+'</div>':'')+'</td>';
        html += '<td><div>'+esc(p.cidade||'—')+'</div>'+(p.regiao?'<div class="ps-sub">'+esc(p.regiao)+'</div>':'')+'</td>';
        html += '<td>'+badgeSit(p.situacao)+'</td>';
        html += '<td class="no-print"><button class="ps-remove" onclick="remover(\''+id+'\')" title="Remover">✕</button></td>';
        html += '</tr>';
    });
    html += '</tbody></table>';
    lista.innerHTML = html;
}

function remover(id) {
    selecao = selecao.filter(function(i){ return i !== id; });
    localStorage.setItem(CART_KEY, JSON.stringify(selecao));
    renderLista();
    document.getElementById('psResultado').classList.remove('visivel');
}
function limparTudo() {
    if (!confirm('Limpar toda a seleção?')) return;
    selecao = [];
    localStorage.setItem(CART_KEY, JSON.stringify(selecao));
    renderLista();
    document.getElementById('psResultado').classList.remove('visivel');
}

// ── Toggles ──────────────────────────────────────────────────
function toggleClienteDireto() {
    var cb = document.getElementById('clienteDireto');
    var inp = document.getElementById('psAgencia');
    inp.disabled = cb.checked;
    inp.value = '';
    inp.placeholder = cb.checked ? 'Cliente direto' : 'Nome da agência (opcional)';
}
function toggleSemPeriodo() {
    var cb = document.getElementById('semPeriodo');
    var ini = document.getElementById('psDataInicio');
    var fim = document.getElementById('psDataFim');
    ini.disabled = fim.disabled = cb.checked;
    if (cb.checked) { ini.value=''; fim.value=''; }
}

// ── Gerar ─────────────────────────────────────────────────────
function gerarPreSelecao() {
    if (selecao.length === 0) return;
    var cliente = document.getElementById('psCliente').value.trim() || 'Cliente';
    var agencia = getAgencia();
    var periodo = getPeriodo();

    var titulo = '📋 Pré-Seleção — '+cliente+(agencia?' / '+agencia:'')+(periodo?' · '+periodo:'');
    document.getElementById('psTitulo').textContent = titulo;

    var lista = selecao.map(function(id){ return pontosData[id]; }).filter(Boolean);
    lista.sort(function(a,b){ return (parseInt(a.numero)||0)-(parseInt(b.numero)||0); });

    var html = '<table class="res-table"><thead><tr><th>Nº</th><th>Logradouro</th><th>Cidade / Região</th><th>Tipo / Formato</th><th>Situação</th><th class="no-print"></th></tr></thead><tbody>';
    lista.forEach(function(p) {
        html += '<tr>';
        html += '<td class="res-num">'+esc(p.numero)+'</td>';
        html += '<td><div style="font-weight:600">'+esc(p.logradouro)+'</div>'+(p.descricao?'<div class="res-sub">'+esc(p.descricao)+'</div>':'')+'</td>';
        html += '<td><div>'+esc(p.cidade||'—')+'</div>'+(p.regiao?'<div class="res-sub">'+esc(p.regiao)+'</div>':'')+'</td>';
        html += '<td><div>'+esc(p.tipo||'—')+'</div>'+(p.formato?'<div class="res-sub">'+esc(p.formato)+'</div>':'')+'</td>';
        html += '<td>'+badgeSit(p.situacao)+'</td>';
        html += '<td class="no-print"><a href="/gestor/pontos/detalhes?id='+p.id+'" class="link-info-btn" target="_blank">+Info</a></td>';
        html += '</tr>';
    });
    html += '</tbody></table>';

    document.getElementById('psTabelaResultado').innerHTML = html;
    var res = document.getElementById('psResultado');
    res.classList.add('visivel');
    setTimeout(function(){ res.scrollIntoView({ behavior:'smooth', block:'start' }); }, 50);
}

// ── CSV ───────────────────────────────────────────────────────
function exportarCSV() {
    var cliente = document.getElementById('psCliente').value.trim() || 'proposta';
    var lista = selecao.map(function(id){ return pontosData[id]; }).filter(Boolean);
    lista.sort(function(a,b){ return (parseInt(a.numero)||0)-(parseInt(b.numero)||0); });
    var csv = 'Nº,Logradouro,Descrição,Cidade,Região,Tipo,Formato,Situação\n';
    lista.forEach(function(p) {
        csv += [p.numero,p.logradouro,p.descricao,p.cidade,p.regiao,p.tipo,p.formato,p.situacao]
            .map(function(v){ return '"'+(v||'').replace(/"/g,'""')+'"'; }).join(',')+'\n';
    });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob(['﻿'+csv],{type:'text/csv;charset=utf-8;'}));
    a.download = 'preselecao_'+cliente.replace(/\s+/g,'_')+'_'+new Date().toISOString().slice(0,10)+'.csv';
    a.click();
}

// ── E-mail ────────────────────────────────────────────────────
function abrirEmail() {
    var cliente = document.getElementById('psCliente').value.trim() || '[Cliente]';
    var agencia = getAgencia();
    var periodo = getPeriodo() || '[Período]';
    var lista = selecao.map(function(id){ return pontosData[id]; }).filter(Boolean);
    lista.sort(function(a,b){ return (parseInt(a.numero)||0)-(parseInt(b.numero)||0); });
    var linhas = lista.map(function(p,i) {
        var local = p.logradouro+(p.cidade?', '+p.cidade:'')+(p.regiao?' – '+p.regiao:'');
        var tipo  = p.tipo?' ('+p.tipo+(p.formato?' '+p.formato:'')+')':'';
        var url   = window.location.origin+'/gestor/pontos/detalhes?id='+p.id;
        return (i+1)+'. Ponto '+(p.numero||'')+' – '+local+tipo+'\n   '+url;
    });
    var dest = cliente+(agencia&&agencia!=='Cliente direto'?' / '+agencia:'');
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
        setTimeout(function(){ btn.textContent='📋 Copiar'; btn.classList.remove('copiado'); }, 2000);
    });
}

document.addEventListener('keydown', function(e) {
    if (e.key==='Escape') fecharEmail();
});

// ── Init ──────────────────────────────────────────────────────
carregarPontos();
</script>

</body>
</html>
