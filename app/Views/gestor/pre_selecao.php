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
    <title>Pré-Seleção · Impakto</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css?v=2">
    <style>
        .ps-page { max-width:clamp(1400px, 90vw, 1920px); margin:0 auto; padding:1.5rem 1.5rem 3rem; }

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

        /* thumbnail */
        .ps-td-foto { padding:4px 6px !important; width:72px; }
        .ps-thumb { width:64px; height:52px; border-radius:5px; overflow:hidden; background:#f0f0f0; display:flex; align-items:center; justify-content:center; cursor:zoom-in; flex-shrink:0; }
        .ps-thumb img { width:100%; height:100%; object-fit:cover; }
        .ps-thumb-vazio { font-size:1.2rem; color:#ccc; }

        /* botão remover */
        .ps-remove {
            display:flex; align-items:center; justify-content:center;
            width:28px; height:28px; border-radius:50%;
            background:#fee2e2; border:none; color:#c0392b;
            font-size:0.85rem; font-weight:800; cursor:pointer;
            transition:all 0.15s; line-height:1;
        }
        .ps-remove:hover { background:#c0392b; color:white; transform:scale(1.1); }

        /* lightbox */
        .ps-lb { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:2000; align-items:center; justify-content:center; cursor:zoom-out; }
        .ps-lb.aberto { display:flex; }
        .ps-lb img { max-width:90vw; max-height:88vh; border-radius:10px; box-shadow:0 20px 60px rgba(0,0,0,0.5); }

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

        /* ── Barra exportação ── */
        .ps-export-bar {
            display:none; margin-top:0.75rem;
            border:1.5px solid var(--color-accent-primary);
            border-radius:8px; overflow:hidden;
        }
        .ps-export-bar.visivel { display:block; }
        .ps-export-titulo {
            padding:0.5rem 0.75rem;
            font-size:0.72rem; font-weight:700; color:var(--color-accent-primary);
            background:#fff8f7; border-bottom:1px solid #fcd5d0;
            line-height:1.4;
        }
        .ps-export-acoes {
            display:flex; gap:0;
        }
        .btn-acao {
            flex:1; padding:0.5rem 0.25rem;
            font-family:'Montserrat',sans-serif; font-size:0.75rem; font-weight:700;
            cursor:pointer; border:none; border-right:1px solid var(--color-border);
            background:white; transition:background 0.15s; text-decoration:none;
            display:flex; align-items:center; justify-content:center; gap:0.3rem;
        }
        .btn-acao:last-child { border-right:none; }
        .btn-imprimir { color:#3498db; }
        .btn-imprimir:hover { background:#ebf5fb; }
        .btn-csv      { color:#27ae60; }
        .btn-csv:hover { background:#eafaf1; }
        .btn-email    { color:#6c3483; }
        .btn-email:hover { background:#f5eef8; }
        .btn-pdf-foto { color:#c0392b; }
        .btn-pdf-foto:hover { background:#fff0ee; }

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

        /* ── Reservas recentes ── */
        .ps-reservas-section {
            margin-top:1.5rem;
            background:white; border:1px solid var(--color-border); border-radius:10px; overflow:hidden;
        }
        .ps-reservas-header {
            padding:0.65rem 1rem;
            background:var(--color-bg-primary); border-bottom:1px solid var(--color-border);
            display:flex; align-items:center; justify-content:space-between;
            font-size:0.8rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:0.4px;
        }
        .ps-reservas-loading { padding:1.5rem; text-align:center; color:var(--color-text-muted); font-size:0.82rem; }
        .ps-reservas-empty   { padding:1.5rem; text-align:center; color:var(--color-text-muted); font-size:0.82rem; }
        .ps-res-table { width:100%; border-collapse:collapse; font-size:0.81rem; }
        .ps-res-table th {
            background:var(--color-bg-primary); padding:0.4rem 0.85rem;
            font-size:0.65rem; font-weight:700; color:var(--color-text-muted);
            text-transform:uppercase; letter-spacing:0.4px;
            border-bottom:1.5px solid var(--color-border); text-align:left;
        }
        .ps-res-table td { padding:0.55rem 0.85rem; border-bottom:1px solid var(--color-border); vertical-align:middle; }
        .ps-res-table tbody tr:last-child td { border-bottom:none; }
        .ps-res-table tbody tr:hover { background:#fafafa; }
        .ps-res-cli  { font-weight:700; color:var(--color-text-dark); }
        .ps-res-ag   { font-size:0.7rem; color:var(--color-text-muted); margin-top:1px; }
        .ps-res-num  { font-weight:700; color:var(--color-accent-primary); text-align:center; }
        .ps-res-data { font-size:0.73rem; color:var(--color-text-muted); white-space:nowrap; }
        .ps-res-acoes { display:flex; gap:0.35rem; }
        .btn-res-reabrir {
            padding:0.25rem 0.6rem; background:#fff3f3;
            border:1px solid #fca5a5; border-radius:5px;
            font-size:0.72rem; font-weight:700; color:var(--color-accent-primary);
            cursor:pointer; transition:all 0.15s; white-space:nowrap;
            font-family:'Montserrat',sans-serif;
        }
        .btn-res-reabrir:hover { background:var(--color-accent-primary); color:white; border-color:var(--color-accent-primary); }
        .btn-res-ver {
            padding:0.25rem 0.6rem; background:#f8f9fa;
            border:1px solid var(--color-border); border-radius:5px;
            font-size:0.72rem; font-weight:700; color:var(--color-text-muted);
            text-decoration:none; white-space:nowrap; transition:all 0.15s;
        }
        .btn-res-ver:hover { background:#f3f4f6; color:var(--color-text-dark); }
        .btn-res-excluir {
            padding:0.25rem 0.5rem; background:#fff; border:1px solid #fca5a5;
            border-radius:5px; font-size:0.72rem; font-weight:700; color:#c0392b;
            cursor:pointer; transition:all 0.15s; font-family:'Montserrat',sans-serif;
        }
        .btn-res-excluir:hover { background:#c0392b; color:white; border-color:#c0392b; }

        /* ── Impressão ── */
        .print-view { display:none; }
        @media print {
            /* Esconde tudo, mostra só o print-view */
            body > *                { display:none !important; }
            body > #printView       { display:block !important; }

            /* Reset do body para impressão */
            body { overflow:visible !important; margin:0; padding:0; background:white; }
            #printView {
                font-family:'Montserrat',Arial,sans-serif;
                padding:1.5cm 1.8cm;
                color:#1a1a1a;
                print-color-adjust:exact;
                -webkit-print-color-adjust:exact;
            }

            /* Cabeçalho */
            .pv-cabecalho {
                display:flex; align-items:flex-start; justify-content:space-between;
                border-bottom:2.5px solid #c0392b; padding-bottom:0.6rem; margin-bottom:1.2rem;
            }
            .pv-titulo  { font-size:0.95rem; font-weight:700; margin-top:0.2rem; color:#1a1a1a; }
            .pv-sub     { font-size:0.8rem; color:#555; margin-top:0.15rem; }
            .pv-data-box { font-size:0.72rem; color:#888; text-align:right; }

            /* Grupo/região */
            .pv-grupo {
                background:#f0f0f0 !important; padding:0.25rem 0.6rem;
                font-size:0.7rem; font-weight:800; text-transform:uppercase;
                letter-spacing:0.7px; color:#222;
                border-left:4px solid #c0392b; margin:1rem 0 0.1rem;
                page-break-after:avoid;
            }

            /* Tabela */
            .pv-table { width:100%; border-collapse:collapse; font-size:0.78rem; }
            .pv-table th {
                border-bottom:1.5px solid #555; padding:0.3rem 0.5rem;
                text-align:left; font-size:0.65rem; font-weight:700;
                color:#555; text-transform:uppercase; letter-spacing:0.4px;
            }
            .pv-table td { padding:0.32rem 0.5rem; border-bottom:1px solid #e8e8e8; vertical-align:middle; }
            .pv-table tbody tr:last-child td { border-bottom:none; }
            .pv-num  { font-weight:800; color:#c0392b; width:36px; }
            .pv-sit  { white-space:nowrap; font-size:0.72rem; font-weight:700; text-transform:uppercase; }
            .pv-link { width:40px; text-align:center; }

            /* Badge de situação no print */
            .badge-sit { padding:1px 6px; border-radius:8px; font-size:0.65rem; font-weight:800; }

            /* Total */
            .pv-total {
                border-top:2px solid #333; margin-top:0.8rem; padding-top:0.4rem;
                font-size:0.82rem; font-weight:800;
            }

            /* Rodapé */
            .pv-rodape {
                margin-top:1.5rem; font-size:0.68rem; color:#aaa;
                border-top:1px solid #e0e0e0; padding-top:0.4rem;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../partials/env_banner.php'; ?>


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

        </div>

        <!-- Coluna direita: formulário + reservas recentes -->
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

                <!-- Barra de exportação (aparece após gerar) -->
                <div class="ps-export-bar" id="psExportBar">
                    <div class="ps-export-titulo" id="psTitulo"></div>
                    <div class="ps-export-acoes">
                        <button class="btn-acao btn-imprimir" onclick="window.print()">🖨️ Imprimir</button>
                        <button class="btn-acao btn-csv"      onclick="exportarCSV()">📊 CSV</button>
                        <button class="btn-acao btn-email"    onclick="abrirEmail()">✉️ E-mail</button>
                        <button class="btn-acao btn-pdf-foto" onclick="gerarPDFFotos()">📷 PDF c/ Fotos</button>
                        <a class="btn-acao" href="/gestor/reservas" style="color:#555">📋 Reservas</a>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <!-- ── Reservas recentes ──────────────────────────────────── -->
    <div class="ps-reservas-section no-print" id="psReservasSection">
        <div class="ps-reservas-header">
            <span>📋 Reservas recentes</span>
            <a href="/gestor/reservas" style="font-size:0.75rem;font-weight:700;color:var(--color-accent-primary);text-decoration:none">Ver todas →</a>
        </div>
        <div id="psReservasLista">
            <div class="ps-reservas-loading">Carregando...</div>
        </div>
    </div>

</div>

<!-- View de impressão (invisível na tela, aparece só no print) -->
<div class="print-view" id="printView">
    <div class="pv-cabecalho">
        <div>
            <img src="/public/assets/img/logo.png" alt="Impakto Mídia OOH" style="height:42px;width:auto;margin-bottom:0.4rem;display:block;">
            <div class="pv-titulo" id="pvTitulo"></div>
            <div class="pv-sub"    id="pvSub"></div>
        </div>
        <div class="pv-data-box">Emitido em<br><span id="pvData"></span></div>
    </div>
    <div id="pvConteudo"></div>
    <div class="pv-rodape">Impakto Mídia · impaktomidia.com.br</div>
</div>

<!-- Toast -->
<div id="psToast" style="
    position:fixed;top:50%;left:50%;z-index:9999;
    background:#1a9059;color:white;padding:0.9rem 1.5rem;
    border-radius:10px;font-size:0.95rem;font-weight:700;
    box-shadow:0 8px 32px rgba(0,0,0,0.3);
    transform:translate(-50%,-50%) scale(0.9);opacity:0;transition:all 0.3s ease;
    pointer-events:none;max-width:90vw;text-align:center;
" class=""></div>
<style>
#psToast.show { transform:translate(-50%,-50%) scale(1) !important; opacity:1 !important; pointer-events:auto !important; }
</style>

<!-- Lightbox foto -->
<div class="ps-lb" id="psLb" onclick="psFecharLb()">
    <img id="psLbImg" src="" alt="">
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
var removidosExclusivos = 0; // qtde removida da seleção por serem exclusivos não liberados

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
            var idsRetornados = data.map(function(p){ return String(p.id); });
            var removidos = selecao.filter(function(id){ return idsRetornados.indexOf(String(id)) === -1; });
            data.forEach(function(p){ pontosData[String(p.id)] = p; });
            if (removidos.length > 0) {
                selecao = selecao.filter(function(id){ return idsRetornados.indexOf(String(id)) !== -1; });
                localStorage.setItem(CART_KEY, JSON.stringify(selecao));
                removidosExclusivos = removidos.length;
            }
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

    // Verificar pontos com conflito
    var conflitos = selecao.filter(function(id) {
        var p = pontosData[id];
        return p && (p.campanha_situacao === 'Reservado' || p.campanha_situacao === 'Ocupado' || p.situacao === 'Ocupado');
    });

    var avisoHtml = '';
    if (removidosExclusivos > 0) {
        avisoHtml += '<div style="background:#1e293b;border:1px solid #334155;border-radius:8px;padding:0.65rem 1rem;margin-bottom:0.75rem;font-size:0.8rem;color:#f8fafc;">'
            + '<strong>🔒 Aviso:</strong> '
            + removidosExclusivos + ' ponto' + (removidosExclusivos > 1 ? 's' : '')
            + ' foi' + (removidosExclusivos > 1 ? 'ram' : '') + ' removido' + (removidosExclusivos > 1 ? 's' : '')
            + ' automaticamente da seleção por ' + (removidosExclusivos > 1 ? 'serem' : 'ser') + ' exclusivo' + (removidosExclusivos > 1 ? 's' : '') + ' de outro cliente.'
            + '</div>';
    }
    if (conflitos.length > 0) {
        avisoHtml = '<div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;padding:0.65rem 1rem;margin-bottom:0.75rem;font-size:0.8rem;color:#92400e;">'
            + '<strong>⚠️ Atenção:</strong> '
            + conflitos.length + ' ponto' + (conflitos.length > 1 ? 's' : '')
            + ' desta seleção já ' + (conflitos.length > 1 ? 'estão' : 'está') + ' ocupado(s) ou reservado(s).'
            + ' Verifique a disponibilidade antes de enviar ao cliente.'
            + '</div>';
    }

    var html = '<table class="ps-table"><thead><tr>'
        +'<th class="no-print" style="width:72px"></th>'
        +'<th style="width:50px">Nº</th>'
        +'<th>Logradouro</th>'
        +'<th style="width:160px">Cidade / Região</th>'
        +'<th style="width:110px">Situação</th>'
        +'<th class="no-print" style="width:44px"></th>'
        +'</tr></thead><tbody>';
    selecao.forEach(function(id) {
        var p = pontosData[id] || { id:id, numero:'#'+id, logradouro:'Carregando...', cidade:'', regiao:'', situacao:'', foto:'' };
        var temConflito = p.campanha_situacao === 'Reservado' || p.campanha_situacao === 'Ocupado' || p.situacao === 'Ocupado';
        var rowStyle = temConflito ? ' style="background:#fffbeb"' : '';
        var fotoHtml = p.foto
            ? '<div class="ps-thumb" onclick="psAbrirLb(\''+esc(p.foto)+'\')"><img src="/'+esc(p.foto)+'" loading="lazy" onerror="this.parentElement.innerHTML=\'<span class=\\\'ps-thumb-vazio\\\'>📷</span>\'"></div>'
            : '<div class="ps-thumb" style="cursor:default"><span class="ps-thumb-vazio">📷</span></div>';
        var conflictoInfo = '';
        if (p.campanha_situacao === 'Reservado' && p.campanha_cliente) {
            conflictoInfo = '<div style="font-size:0.68rem;color:#92400e;margin-top:2px;">⚠️ Reservado: <strong>'+esc(p.campanha_cliente)+'</strong></div>';
        } else if (p.campanha_situacao === 'Ocupado' && p.campanha_cliente) {
            conflictoInfo = '<div style="font-size:0.68rem;color:#991b1b;margin-top:2px;">🔴 Ocupado: <strong>'+esc(p.campanha_cliente)+'</strong></div>';
        } else if (p.situacao === 'Ocupado') {
            conflictoInfo = '<div style="font-size:0.68rem;color:#991b1b;margin-top:2px;">🔴 Ponto ocupado</div>';
        }
        html += '<tr'+rowStyle+'>';
        html += '<td class="ps-td-foto no-print">'+fotoHtml+'</td>';
        html += '<td class="ps-num">'+esc(p.numero)+'</td>';
        html += '<td><div class="ps-local">'+esc(p.logradouro)+'</div>'+(p.descricao?'<div class="ps-sub">'+esc(p.descricao)+'</div>':'')+conflictoInfo+'</td>';
        html += '<td><div>'+esc(p.cidade||'—')+'</div>'+(p.regiao?'<div class="ps-sub">'+esc(p.regiao)+'</div>':'')+'</td>';
        html += '<td>'+badgeSit(p.situacao)+'</td>';
        html += '<td class="no-print" style="text-align:center"><button class="ps-remove" onclick="remover(\''+id+'\')" title="Remover ponto">✕</button></td>';
        html += '</tr>';
    });
    html += '</tbody></table>';
    lista.innerHTML = avisoHtml + html;
}

function remover(id) {
    selecao = selecao.filter(function(i){ return i !== id; });
    localStorage.setItem(CART_KEY, JSON.stringify(selecao));
    renderLista();
    document.getElementById('psExportBar').classList.remove('visivel');
}
function limparTudo() {
    if (!confirm('Limpar toda a seleção?')) return;
    selecao = [];
    localStorage.setItem(CART_KEY, JSON.stringify(selecao));
    renderLista();
    document.getElementById('psExportBar').classList.remove('visivel');
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

// ── Helper: agrupar por região ────────────────────────────────
function agruparPorRegiao(lista) {
    var grupos = {}, ordem = [];
    lista.forEach(function(p) {
        var reg = (p.regiao||'').trim() || 'Sem região';
        if (!grupos[reg]) { grupos[reg] = []; ordem.push(reg); }
        grupos[reg].push(p);
    });
    ordem.sort(function(a, b) {
        if (a === 'Sem região') return 1;
        if (b === 'Sem região') return -1;
        return a.localeCompare(b);
    });
    return { grupos: grupos, ordem: ordem };
}

// ── Gerar ─────────────────────────────────────────────────────
function gerarPreSelecao() {
    if (selecao.length === 0) return;
    var cliente = document.getElementById('psCliente').value.trim() || 'Cliente';
    var agencia = getAgencia();
    var periodo = getPeriodo();

    var titulo = '📋 '+cliente+(agencia?' / '+agencia:'')+(periodo?' · '+periodo:'');
    document.getElementById('psTitulo').textContent = titulo;
    document.getElementById('psExportBar').classList.add('visivel');

    // ── Salvar no banco ───────────────────────────────────────
    var semPeriodo = document.getElementById('semPeriodo').checked;
    var payload = {
        cliente:     cliente,
        agencia:     (agencia === 'Cliente direto' ? '' : agencia),
        periodo_ini: semPeriodo ? null : (document.getElementById('psDataInicio').value || null),
        periodo_fim: semPeriodo ? null : (document.getElementById('psDataFim').value || null),
        sem_periodo: semPeriodo,
        pontos_ids:  selecao.map(Number)
    };
    fetch('/gestor/pre-selecao/salvar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) {
            carregarReservas();
            mostrarToast('✅ Reserva #'+data.id+' salva! <a href="/gestor/reservas/ver?id='+data.id+'" style="color:white;text-decoration:underline">Ver →</a>');
        }
    })
    .catch(function() { /* silencioso — não bloqueia o fluxo */ });

    // Gera view de impressão agrupada por região
    var lista = selecao.map(function(id){ return pontosData[id]; }).filter(Boolean);
    lista.sort(function(a,b){ return (parseInt(a.numero)||0)-(parseInt(b.numero)||0); });
    var ag = agruparPorRegiao(lista);

    document.getElementById('pvTitulo').textContent = 'Pré-Seleção — '+cliente+(agencia?' / '+agencia:'');
    document.getElementById('pvSub').textContent    = periodo ? 'Período: '+periodo : '';
    document.getElementById('pvData').textContent   = new Date().toLocaleDateString('pt-BR');

    var n = 0, html = '';
    ag.ordem.forEach(function(reg) {
        var pts = ag.grupos[reg];
        html += '<div class="pv-grupo">'+esc(reg)+' &nbsp;('+pts.length+' ponto'+(pts.length>1?'s':'')+')</div>';
        html += '<table class="pv-table"><thead><tr><th>Nº</th><th>Logradouro</th><th>Cidade</th><th>Situação</th><th>Link</th></tr></thead><tbody>';
        pts.forEach(function(p) {
            n++;
            var url = window.location.origin+'/public/p.php?id='+p.id;
            html += '<tr>';
            html += '<td class="pv-num">'+esc(p.numero)+'</td>';
            html += '<td><div style="font-weight:600">'+esc(p.logradouro)+'</div>'+(p.descricao?'<div style="font-size:0.7rem;color:#666">'+esc(p.descricao)+'</div>':'')+'</td>';
            html += '<td>'+esc(p.cidade||'—')+'</td>';
            html += '<td class="pv-sit">'+badgeSit(p.situacao)+'</td>';
            html += '<td class="pv-link"><a href="'+url+'" style="color:#c0392b;font-size:0.68rem;font-weight:700;text-decoration:none;">+Info</a></td>';
            html += '</tr>';
        });
        html += '</tbody></table>';
    });
    html += '<div class="pv-total">TOTAL: '+lista.length+' ponto'+(lista.length>1?'s':'')+'</div>';
    document.getElementById('pvConteudo').innerHTML = html;
}

// ── PDF com Fotos ─────────────────────────────────────────────
function gerarPDFFotos() {
    if (selecao.length === 0) return;
    var cliente = document.getElementById('psCliente').value.trim() || 'Proposta';
    var agencia = document.getElementById('clienteDireto').checked ? '' : document.getElementById('psAgencia').value.trim();
    var semPer  = document.getElementById('semPeriodo').checked;
    var inicio  = semPer ? '' : (document.getElementById('psDataInicio').value || '');
    var fim     = semPer ? '' : (document.getElementById('psDataFim').value || '');

    var params = new URLSearchParams();
    params.set('cliente', cliente);
    if (agencia) params.set('agencia', agencia);
    if (inicio)  params.set('inicio', inicio);
    if (fim)     params.set('fim', fim);
    if (semPer)  params.set('sem_periodo', '1');
    selecao.forEach(function(id) { params.append('pontoIds[]', id); });

    window.open('/gestor/pre-selecao/pdf?' + params.toString(), '_blank');
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
    var ag = agruparPorRegiao(lista);
    var dest = cliente+(agencia&&agencia!=='Cliente direto'?' / '+agencia:'');

    var n = 0, secoes = [];
    ag.ordem.forEach(function(reg) {
        var linhas = [reg.toUpperCase()+' ('+ag.grupos[reg].length+' ponto'+(ag.grupos[reg].length>1?'s':'')+')'];
        ag.grupos[reg].forEach(function(p) {
            n++;
            var local = p.logradouro+(p.cidade?', '+p.cidade:'');
            var url   = window.location.origin+'/public/p.php?id='+p.id;
            linhas.push(n+'. Ponto '+(p.numero||'')+' – '+local+'\n   '+url);
        });
        secoes.push(linhas.join('\n'));
    });

    var txt = 'Prezado(a),\n\nEncaminhamos a pré-seleção de mídia exterior para '+dest
        +' referente ao período de '+periodo+'.\n\nPONTOS SELECIONADOS:\n\n'
        +secoes.join('\n\n')
        +'\n\nTOTAL: '+lista.length+' ponto'+(lista.length>1?'s':'')+'.'
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

// ── Toast ─────────────────────────────────────────────────────
function mostrarToast(html) {
    var t = document.getElementById('psToast');
    t.innerHTML = html;
    t.classList.add('show');
    clearTimeout(t._timer);
    t._timer = setTimeout(function() { t.classList.remove('show'); }, 4000);
}

document.addEventListener('keydown', function(e) {
    if (e.key==='Escape') { fecharEmail(); psFecharLb(); }
});

// ── Lightbox ─────────────────────────────────────────────────
function psAbrirLb(foto) {
    document.getElementById('psLbImg').src = '/'+foto;
    document.getElementById('psLb').classList.add('aberto');
}
function psFecharLb() {
    document.getElementById('psLb').classList.remove('aberto');
    document.getElementById('psLbImg').src = '';
}

// ── Reservas recentes ─────────────────────────────────────────
function carregarReservas() {
    fetch('/gestor/reservas/recentes')
        .then(function(r) { return r.json(); })
        .then(function(lista) {
            var div = document.getElementById('psReservasLista');
            if (!lista || lista.length === 0) {
                div.innerHTML = '<div class="ps-reservas-empty">Nenhuma reserva registrada ainda.</div>';
                return;
            }
            var html = '<table class="ps-res-table"><thead><tr>'
                + '<th>Cliente / Agência</th><th style="width:130px">Período</th>'
                + '<th style="width:50px;text-align:center">Pts</th>'
                + '<th style="width:110px">Data</th>'
                + '<th style="width:120px"></th>'
                + '</tr></thead><tbody>';
            lista.forEach(function(ps) {
                var per = ps.sem_periodo ? 'Sem período'
                    : (ps.periodo_ini && ps.periodo_fim
                        ? fmtData(ps.periodo_ini) + ' – ' + fmtData(ps.periodo_fim)
                        : (ps.periodo_ini ? 'A partir de ' + fmtData(ps.periodo_ini) : '—'));
                html += '<tr>';
                html += '<td><div class="ps-res-cli">'+esc(ps.cliente)+'</div>'+(ps.agencia?'<div class="ps-res-ag">'+esc(ps.agencia)+'</div>':'')+'</td>';
                html += '<td style="font-size:0.75rem;color:var(--color-text-muted)">'+esc(per)+'</td>';
                html += '<td class="ps-res-num">'+ps.total_pontos+'</td>';
                html += '<td class="ps-res-data">'+fmtDatetime(ps.criado_em)+'</td>';
                html += '<td><div class="ps-res-acoes">'
                    + '<a href="/gestor/reservas/ver?id='+ps.id+'" class="btn-res-ver">👁 Ver</a>'
                    + '<button class="btn-res-reabrir" onclick="reabrirReserva('+ps.id+')">↩ Reabrir</button>'
                    + '<button class="btn-res-excluir" onclick="excluirReserva('+ps.id+', this)" title="Excluir">🗑</button>'
                    + '</div></td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            div.innerHTML = html;
        })
        .catch(function() {
            document.getElementById('psReservasLista').innerHTML =
                '<div class="ps-reservas-empty" style="color:#c0392b">Erro ao carregar reservas.</div>';
        });
}

function reabrirReserva(id) {
    fetch('/gestor/pre-selecao/dados?id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.erro) { alert('Erro ao carregar a reserva.'); return; }
            selecao = data.pontos_ids;
            localStorage.setItem(CART_KEY, JSON.stringify(selecao));
            pontosData = {};
            carregarPontos();
            mostrarToast('✅ ' + selecao.length + ' ponto(s) carregado(s) da reserva!');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        })
        .catch(function() { alert('Erro de comunicação.'); });
}

function excluirReserva(id, btn) {
    if (!confirm('Excluir esta reserva? Esta ação não pode ser desfeita.')) return;
    btn.disabled = true;
    fetch('/gestor/reservas/excluir', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) {
            var tr = btn.closest('tr');
            tr.style.transition = 'opacity 0.3s';
            tr.style.opacity = '0';
            setTimeout(function() { tr.remove(); }, 300);
            mostrarToast('🗑 Reserva excluída.');
        } else {
            btn.disabled = false;
            alert('Erro ao excluir.');
        }
    })
    .catch(function() { btn.disabled = false; alert('Erro de comunicação.'); });
}

function fmtData(val) {
    if (!val) return '';
    var p = val.split('-');
    return p.length === 3 ? p[2]+'/'+p[1]+'/'+p[0] : val;
}
function fmtDatetime(val) {
    if (!val) return '';
    var parts = val.split(' ');
    return fmtData(parts[0]) + (parts[1] ? ' ' + parts[1].slice(0,5) : '');
}

// ── Init ──────────────────────────────────────────────────────
carregarPontos();
carregarReservas();
</script>

</body>
</html>
