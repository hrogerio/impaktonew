<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

$paginaAtual = 'relatorios';

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../Controllers/RelatorioController.php';

$controller = new RelatorioController();

$ocupacao  = $controller->dadosOcupacao();
$contratos = $controller->dadosContratos();
$clientes  = $controller->dadosClientes();
$documentosPorGrupo = $controller->documentosPorGrupo();

// ============================================================
// Helpers de apresentação
// ============================================================
function pct($valor, $total) {
    return $total > 0 ? round(($valor / $total) * 100, 1) : 0;
}


function mesLabel($mesStr) {
    $meses = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
              '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];
    list($ano, $m) = explode('-', $mesStr);
    return (isset($meses[$m]) ? $meses[$m] : $m) . '/' . substr($ano, 2);
}

function fmtData($data) {
    if (!$data || $data === '0000-00-00') return '-';
    try { return (new DateTime($data))->format('d/m/Y'); }
    catch (Exception $e) { return '-'; }
}

function fmtDuracao($dias) {
    if ($dias === null) return '-';
    $dias = (int)$dias;
    if ($dias < 30) {
        return $dias . ' dias';
    }
    $meses = round($dias / 30);
    return $meses . ' meses';
}

/** Tabela padrão de campanhas (Cliente/Campanha/Agência/Início/Fim/Duração/Pontos), reutilizada em Contratos Ativos e Vencendo por mês */
function tabelaCampanhas(array $lista) {
    global $documentosPorGrupo;
    if (empty($lista)) {
        echo '<div class="empty-state"><p>Nenhum contrato encontrado.</p></div>';
        return;
    }
    ?>
    <div class="table-container">
        <table class="rel-table">
            <thead>
                <tr><th>Cliente</th><th>Campanha</th><th>Agência</th><th>Contato</th><th>Início</th><th>Fim</th><th>Duração</th><th style="text-align:right">Pontos</th></tr>
            </thead>
            <tbody>
                <?php foreach ($lista as $c):
                    $docChave = md5(trim($c['cliente'] ?? '') . '|' . trim($c['agencia'] ?? '') . '|' . trim($c['campanha'] ?? '') . '|' . ($c['inicio_contrato'] ?? '') . '|' . ($c['fim_contrato'] ?? ''));
                    $dadosContrato = json_encode([
                        'cliente'         => $c['cliente'] ?? '-',
                        'agencia'         => $c['agencia'] ?? '',
                        'campanha'        => $c['campanha'] ?? '-',
                        'situacao'        => $c['situacao'] ?? 'Ocupado',
                        'inicio_contrato' => $c['inicio_contrato'] ?? null,
                        'fim_contrato'    => $c['fim_contrato'] ?? null,
                        'inicio_fmt'      => fmtData($c['inicio_contrato'] ?? null),
                        'fim_fmt'         => fmtData($c['fim_contrato'] ?? null),
                        'pontos'          => $c['pontos'] ?? [],
                        'documentos'      => $documentosPorGrupo[$docChave] ?? [],
                    ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>
                <tr class="rel-row-clicavel" onclick='abrirDetalhesContrato(<?= $dadosContrato ?>)' title="Ver detalhes do contrato">
                    <td><strong><?= htmlspecialchars($c['cliente'] ?? '-') ?></strong></td>
                    <td><?= htmlspecialchars($c['campanha'] ?? '-') ?></td>
                    <td style="color:var(--color-text-muted)"><?= htmlspecialchars($c['agencia'] ?? '-') ?></td>
                    <td style="font-size:0.78rem"><?= htmlspecialchars($c['contato'] ?? '-') ?></td>
                    <td><?= fmtData($c['inicio_contrato']) ?></td>
                    <td><?= fmtData($c['fim_contrato']) ?></td>
                    <td><?= fmtDuracao($c['duracao_dias']) ?></td>
                    <td style="text-align:right"><strong style="color:var(--color-accent-primary)"><?= $c['qtd_pontos'] ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Impakto Mídia</title>
    <link rel="icon" href="/public/assets/img/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
    <link rel="stylesheet" href="/public/assets/css/relatorios.css">
    <style>
        .rel-row-clicavel { cursor:pointer; }
        .rel-row-clicavel:hover { background:#f9fafb; }
        .rel-row-clicavel:hover td { color:var(--color-accent-primary) !important; font-weight:700; }
        .rel-row-clicavel:hover td:first-child strong { text-decoration:underline; }

        .cp-modal-overlay {
            display:none; position:fixed; inset:0;
            background:rgba(0,0,0,0.45); z-index:1000;
            align-items:center; justify-content:center;
        }
        .cp-modal-overlay.aberto { display:flex; }
        .cp-card {
            background:#fff; border:1px solid var(--color-border); border-radius:12px;
            overflow:hidden; display:flex; flex-direction:column;
        }
        .cp-card.cp-modal {
            width:480px; max-width:95vw; max-height:90vh; overflow-y:auto;
            box-shadow:0 20px 60px rgba(0,0,0,0.3); border-radius:14px;
            position:relative;
        }
        .rel-modal-fechar {
            position:absolute; top:0.75rem; right:0.75rem; z-index:1;
            width:28px; height:28px; border-radius:50%;
            background:#fff; border:2px solid #dc3545; color:#dc3545;
            font-size:0.85rem; font-weight:800; line-height:1;
            cursor:pointer; display:flex; align-items:center; justify-content:center;
            transition:all 0.15s;
        }
        .rel-modal-fechar:hover { background:#dc3545; color:#fff; }
        .cp-card-head { padding:0.85rem 1rem 0.6rem; border-bottom:1px solid #f0f2f5; }
        .cp-card-top { display:flex; align-items:center; gap:0.5rem; margin-bottom:0.35rem; flex-wrap:wrap; }
        .sit-badge {
            display:inline-block; padding:2px 9px; border-radius:10px;
            font-size:0.6rem; font-weight:800; text-transform:uppercase;
            letter-spacing:0.4px; color:white; white-space:nowrap; flex-shrink:0;
        }
        .cp-card-nome { font-size:0.75rem; font-weight:600; color:var(--color-text-muted); flex:1; min-width:0; }
        .cp-card-cliente { font-size:1rem; font-weight:800; color:var(--color-text-dark); }
        .cp-card-agencia { font-size:0.72rem; color:var(--color-text-muted); font-weight:600; }
        .cp-card-meta { display:flex; align-items:center; gap:0.5rem; margin-top:0.4rem; flex-wrap:wrap; }
        .cp-card-periodo { font-size:0.75rem; color:var(--color-text-muted); font-weight:600; }

        .cp-card-paineis { max-height:260px; overflow-y:auto; }
        .cp-painel-row { display:flex; align-items:center; gap:0.6rem; padding:0.5rem 1rem; border-bottom:1px solid #f5f5f7; }
        .cp-painel-row:last-child { border-bottom:none; }
        .cp-painel-num { font-weight:800; color:var(--color-accent-primary); font-size:0.78rem; min-width:32px; flex-shrink:0; }
        .cp-painel-end { flex:1; min-width:0; }
        .cp-painel-log { font-size:0.78rem; font-weight:600; color:var(--color-text-dark); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .cp-painel-cid { font-size:0.68rem; color:var(--color-text-muted); margin-top:1px; }
        .cp-painel-link { font-size:0.72rem; font-weight:700; color:var(--color-accent-primary); text-decoration:none; flex-shrink:0; }
        .cp-painel-link:hover { text-decoration:underline; }

        .cp-card-footer {
            padding:0.45rem 0.75rem 0.45rem 1rem; background:#fafbfc;
            border-top:1px solid #f0f2f5; font-size:0.68rem; font-weight:700;
            color:var(--color-text-muted); display:flex; align-items:center;
            justify-content:space-between; gap:0.5rem; flex-wrap:wrap;
        }
        .cp-acoes { display:flex; gap:0.35rem; flex-wrap:wrap; }
        .cp-btn {
            padding:3px 9px; border-radius:5px; font-size:0.7rem; font-weight:700;
            cursor:pointer; border:none; font-family:'Montserrat',sans-serif;
            transition:all 0.15s; white-space:nowrap; text-decoration:none;
            display:inline-flex; align-items:center; gap:0.25rem;
        }
        .cp-btn-checking { background:#fdf4ff; color:#7e22ce; border:1px solid #d8b4fe; }
        .cp-btn-checking:hover { background:#f3e8ff; }
        .cp-btn-docs { background:#eefdf6; color:#0f766e; border:1px solid #99f6e4; }
        .cp-btn-docs:hover { background:#ccfbf1; }
        .cp-btn-docs.desabilitado { background:#f3f4f6; color:#6b7280; border:1px solid #e5e7eb; cursor:not-allowed; pointer-events:none; }
        .cp-btn-editar { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
        .cp-btn-editar:hover { background:#dbeafe; }
        .cp-btn-renovar { background:#f0fdf4; color:#166534; border:1px solid #86efac; }
        .cp-btn-renovar:hover { background:#dcfce7; }
        .cp-btn-cancelar {
            padding:0.55rem 1rem; background:none; color:#666;
            border:1px solid var(--color-border); border-radius:8px;
            font-family:'Montserrat',sans-serif; font-size:0.85rem; cursor:pointer;
        }
    </style>
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="container" style="padding-top:0.75rem; padding-bottom:2rem;">

    <div class="welcome" style="margin-bottom:1rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.75rem;">
        <div>
            <h2>📊 Relatórios</h2>
            <p>Ocupação, contratos, clientes e histórico — visão comercial para apresentação à diretoria.</p>
        </div>
        <a class="btn-export btn-pdf-mensal" href="/gestor/relatorios/pdf" target="_blank">
            📄 Gerar Relatório Mensal (PDF)
        </a>
    </div>

    <div class="tabs-nav" id="tabsNav">
        <button class="tab-btn active" onclick="switchTab('contratos',this)">📅 Contratos</button>
        <button class="tab-btn" onclick="switchTab('clientes',this)">🏢 Clientes</button>
        <button class="tab-btn" onclick="switchTab('agencias',this)">🏛️ Agências</button>
        <button class="tab-btn" onclick="switchTab('ocupacao',this)">🗺️ Ocupação</button>
    </div>

    <!-- ============================================================ -->
    <!-- ABA 1: OCUPAÇÃO                                               -->
    <!-- ============================================================ -->
    <div class="tab-content" id="tab-ocupacao">

        <div class="export-bar">
            <button class="btn-export btn-csv" onclick="exportCSV('tbl-regiao-hidden','ocupacao-regiao')">⬇ CSV</button>
        </div>

        <div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);">
            <div class="kpi-card kpi-total">
                <div class="kpi-icon">📍</div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= number_format($ocupacao['totais']['geral']) ?></div>
                    <div class="kpi-label">Total de Pontos</div>
                </div>
            </div>
            <div class="kpi-card kpi-ocup">
                <div class="kpi-icon">🔴</div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= number_format($ocupacao['totais']['ocupados']) ?></div>
                    <div class="kpi-label">Ocupados</div>
                    <div class="kpi-sub"><?= pct($ocupacao['totais']['ocupados'], $ocupacao['totais']['geral']) ?>% do total</div>
                </div>
            </div>
            <div class="kpi-card kpi-disp">
                <div class="kpi-icon">🟢</div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= number_format($ocupacao['totais']['disponiveis']) ?></div>
                    <div class="kpi-label">Disponíveis</div>
                    <div class="kpi-sub"><?= pct($ocupacao['totais']['disponiveis'], $ocupacao['totais']['geral']) ?>% do total</div>
                </div>
            </div>
        </div>

        <div class="bloco-section">
            <div class="bloco-label">Por Região</div>

            <?php if (empty($ocupacao['ocupacao_regiao'])): ?>
                <p style="color:var(--color-text-muted);font-size:0.85rem;">Nenhum dado encontrado.</p>
            <?php else: ?>
                <div class="bars-clean">
                    <?php foreach ($ocupacao['ocupacao_regiao'] as $r): ?>
                    <div class="bar-clean-row" title="<?= htmlspecialchars($r['regiao']) ?>: <?= $r['ocupados'] ?> ocupados, <?= $r['disponiveis'] ?> disponíveis">
                        <div class="bar-clean-label"><?= htmlspecialchars($r['regiao']) ?></div>
                        <div class="bar-clean-track">
                            <div class="bc-ocup" style="width:<?= pct($r['ocupados'],$r['total']) ?>%"></div>
                            <div class="bc-disp" style="width:<?= pct($r['disponiveis'],$r['total']) ?>%"></div>
                            <div class="bc-res"  style="width:<?= pct($r['reservados'],$r['total']) ?>%"></div>
                            <div class="bc-venc" style="width:<?= pct($r['vencidos'],$r['total']) ?>%"></div>
                        </div>
                        <div class="bar-clean-meta">
                            <span class="bar-clean-total"><?= $r['total'] ?></span>
                            <span class="bar-clean-pct"><?= pct($r['ocupados'],$r['total']) ?>% ocup.</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="bloco-section">
            <div class="bloco-label">Por Cidades <span class="bloco-count"><?= count($ocupacao['ocupacao_cidade']) ?></span></div>

            <?php if (empty($ocupacao['ocupacao_cidade'])): ?>
                <p style="color:var(--color-text-muted);font-size:0.85rem;margin-top:0.5rem;">Nenhuma cidade encontrada.</p>
            <?php else: ?>
            <div class="table-container" style="margin-top:0.75rem;">
                <table class="rel-table">
                    <thead>
                        <tr>
                            <th>Cidade</th>
                            <th style="text-align:right">Total</th>
                            <th style="text-align:right">Ocup.</th>
                            <th style="text-align:right">Disp.</th>
                            <th style="text-align:right">Res.</th>
                            <th style="text-align:right">Venc.</th>
                            <th>% Ocup.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ocupacao['ocupacao_cidade'] as $c): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['cidade']) ?></strong></td>
                            <td style="text-align:right;font-weight:700"><?= $c['total'] ?></td>
                            <td style="text-align:right;color:#e34c3e;font-weight:600"><?= $c['ocupados'] ?></td>
                            <td style="text-align:right;color:#27ae60;font-weight:600"><?= $c['disponiveis'] ?></td>
                            <td style="text-align:right;color:#f39c12;font-weight:600"><?= $c['reservados'] ?></td>
                            <td style="text-align:right;color:#8e44ad;font-weight:600"><?= $c['vencidos'] ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:5px;">
                                    <div style="flex:1;height:6px;background:#f0f0f0;border-radius:3px;overflow:hidden;min-width:40px;">
                                        <div style="width:<?= pct($c['ocupados'],$c['total']) ?>%;height:100%;background:#e34c3e;border-radius:3px;"></div>
                                    </div>
                                    <span style="font-size:0.72rem;font-weight:700;width:32px;text-align:right;color:var(--color-text-muted);"><?= pct($c['ocupados'],$c['total']) ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <table id="tbl-regiao-hidden" style="display:none">
            <thead><tr><th>Região</th><th>Total</th><th>Ocupados</th><th>Disponíveis</th><th>Reservados</th><th>Ctr. Vencidos</th><th>% Ocupação</th></tr></thead>
            <tbody>
                <?php foreach ($ocupacao['ocupacao_regiao'] as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['regiao']) ?></td>
                    <td><?= $r['total'] ?></td>
                    <td><?= $r['ocupados'] ?></td>
                    <td><?= $r['disponiveis'] ?></td>
                    <td><?= $r['reservados'] ?></td>
                    <td><?= $r['vencidos'] ?></td>
                    <td><?= pct($r['ocupados'],$r['total']) ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div><!-- /tab-ocupacao -->


    <!-- ============================================================ -->
    <!-- ABA 2: CONTRATOS & TEMPO DE CONTRATO                          -->
    <!-- ============================================================ -->
    <div class="tab-content active" id="tab-contratos">

        <div class="export-bar">
            <a class="btn-export btn-pdf" href="/gestor/relatorios/contratos/pdf" target="_blank">📄 PDF de Contratos</a>
        </div>

        <div class="kpi-grid" style="grid-template-columns:repeat(2,minmax(180px,220px)); margin-bottom:1.25rem;">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#eef6ff;">📄</div>
                <div class="kpi-body">
                    <div class="kpi-value" style="color:#3498db"><?= count($contratos['campanhas_ativas']) ?></div>
                    <div class="kpi-label">Contratos Ativos</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#f9f0ff;">🔴</div>
                <div class="kpi-body">
                    <div class="kpi-value" style="color:#8e44ad"><?= count($contratos['vencidos']) ?></div>
                    <div class="kpi-label">Já Vencidos</div>
                </div>
            </div>
        </div>

        <?php if (!empty($contratos['ativos_por_mes'])): ?>
        <div class="panel" style="margin-bottom:1.25rem;">
            <div class="panel-title">Histórico Anual — Contratos Ativos por Mês</div>
            <?php $maxAtivos = max(1, max($contratos['ativos_por_mes'])); ?>
            <div class="timeline-bars">
                <?php foreach ($contratos['ativos_por_mes'] as $mes => $qtd): ?>
                <div class="tl-col">
                    <div class="tl-bar-count"><?= $qtd ?></div>
                    <div class="tl-bar" style="height:<?= max(4,round(($qtd/$maxAtivos)*60)) ?>px;"></div>
                    <div class="tl-label"><?= mesLabel($mes) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="section-title">📋 Contratos Ativos por Cliente</div>
        <?php tabelaCampanhas($contratos['campanhas_ativas']); ?>

        <div class="section-title" style="margin-top:1.5rem">🔴 Contratos Vencidos (<?= count($contratos['vencidos_agrupado']) ?>)</div>
        <?php tabelaCampanhas($contratos['vencidos_agrupado']); ?>

        <!-- ===== Contratos Vencendo ===== -->
        <div class="section-title" style="margin-top:1.5rem">📅 Contratos Vencendo</div>

        <?php if (!empty($contratos['vencendo_por_mes'])): ?>
        <div class="panel" style="margin-bottom:1.25rem;">
            <div class="panel-title">Distribuição por mês (Jul a Dez)</div>
            <?php $maxMes = max(1, max($contratos['vencendo_por_mes'])); ?>
            <div class="timeline-bars">
                <?php foreach ($contratos['vencendo_por_mes'] as $mes => $qtd): ?>
                <div class="tl-col">
                    <div class="tl-bar-count"><?= $qtd ?></div>
                    <div class="tl-bar" style="height:<?= max(4,round(($qtd/$maxMes)*60)) ?>px;"></div>
                    <div class="tl-label"><?= mesLabel($mes) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($contratos['vencendo_agrupado'])): ?>
            <div class="empty-state"><div class="empty-state-icon">🎉</div><p>Nenhum contrato vencendo este ano.</p></div>
        <?php else: ?>
            <?php foreach ($contratos['vencendo_agrupado'] as $mes => $campanhas): ?>
            <div class="bloco-section">
                <div class="bloco-label"><?= mesLabel($mes) ?></div>
                <?php tabelaCampanhas($campanhas); ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div><!-- /tab-contratos -->


    <!-- ============================================================ -->
    <!-- ABA 3: CLIENTES                                              -->
    <!-- ============================================================ -->
    <div class="tab-content" id="tab-clientes">

        <div class="export-bar">
            <button class="btn-export btn-csv" onclick="exportCSV('tbl-clientes','pontos-por-cliente')">⬇ CSV</button>
        </div>

        <div class="kpi-grid" style="grid-template-columns:minmax(180px,220px); margin-bottom:1.25rem;">
            <div class="kpi-card kpi-total">
                <div class="kpi-icon">🏢</div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= count($clientes['clientes']) ?></div>
                    <div class="kpi-label">Clientes</div>
                </div>
            </div>
        </div>

        <div class="section-title">📋 Todos os Clientes (<?= count($clientes['clientes']) ?>)</div>
        <?php if (empty($clientes['clientes'])): ?>
            <div class="empty-state"><div class="empty-state-icon">🏢</div><p>Nenhum cliente encontrado.</p></div>
        <?php else: ?>
        <div class="table-container">
            <table class="rel-table" id="tbl-clientes">
                <thead>
                    <tr><th>#</th><th>Cliente</th><th>Agência</th><th>Pontos</th><th>Início</th><th>Fim Contrato</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($clientes['clientes'] as $i => $cl): ?>
                    <tr>
                        <td style="color:var(--color-text-muted)"><?= $i+1 ?></td>
                        <td><strong><?= htmlspecialchars($cl['cliente']) ?></strong></td>
                        <td style="color:var(--color-text-muted)"><?= htmlspecialchars($cl['agencia']) ?></td>
                        <td><strong style="color:var(--color-accent-primary)"><?= $cl['total_pontos'] ?></strong></td>
                        <td style="color:var(--color-text-muted);font-size:0.78rem;">
                            <?php
                            if ($cl['inicio_mais_antigo'] && $cl['inicio_mais_antigo'] !== '0000-00-00') {
                                try { echo (new DateTime($cl['inicio_mais_antigo']))->format('m/Y'); }
                                catch(Exception $e) { echo '-'; }
                            } else { echo '-'; }
                            ?>
                        </td>
                        <td style="font-size:0.78rem;"><?= fmtData($cl['fim_mais_recente']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div><!-- /tab-clientes -->

    <!-- ============================================================ -->
    <!-- ABA 4: AGÊNCIAS                                              -->
    <!-- ============================================================ -->
    <div class="tab-content" id="tab-agencias">

        <div class="export-bar">
            <button class="btn-export btn-csv" onclick="exportCSV('tbl-agencias','resumo-agencias')">⬇ CSV</button>
        </div>

        <div class="kpi-grid" style="grid-template-columns:minmax(180px,220px); margin-bottom:1.25rem;">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#eef6ff;">🏛️</div>
                <div class="kpi-body">
                    <div class="kpi-value" style="color:#3498db"><?= count($clientes['agencias']) ?></div>
                    <div class="kpi-label">Agências</div>
                </div>
            </div>
        </div>

        <div class="section-title">🏛️ Resumo por Agência (<?= count($clientes['agencias']) ?>)</div>
        <?php if (empty($clientes['agencias'])): ?>
            <div class="empty-state"><p>Nenhuma agência encontrada.</p></div>
        <?php else: ?>
        <div class="table-container">
            <table class="rel-table" id="tbl-agencias">
                <thead><tr><th>Agência</th><th>Clientes</th><th>Total de Pontos</th></tr></thead>
                <tbody>
                    <?php foreach ($clientes['agencias'] as $ag): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($ag['agencia']) ?></strong></td>
                        <td><?= $ag['total_clientes'] ?></td>
                        <td><strong style="color:var(--color-accent-primary)"><?= $ag['total_pontos'] ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div><!-- /tab-agencias -->


</div><!-- /container -->

<!-- ── Modal de detalhes do contrato ── -->
<div class="cp-modal-overlay" id="relDetalheOverlay">
    <div class="cp-modal cp-card" style="padding:0;">
        <button type="button" class="rel-modal-fechar" onclick="fecharDetalhesContrato()" title="Fechar">✕</button>
        <div class="cp-card-faixa" id="relDetalheFaixa" style="background:#888"></div>
        <div class="cp-card-head">
            <div class="cp-card-top">
                <span class="sit-badge" id="relDetalheBadge" style="background:#888">Ocupado</span>
                <span class="cp-card-nome" id="relDetalheNome"></span>
            </div>
            <div class="cp-card-cliente" id="relDetalheCliente"></div>
            <div class="cp-card-agencia" id="relDetalheAgencia" style="display:none"></div>
            <div class="cp-card-meta">
                <span class="cp-card-periodo" id="relDetalhePeriodo"></span>
            </div>
        </div>
        <div class="cp-card-paineis" id="relDetalhePontos"></div>
        <div class="cp-card-footer">
            <span id="relDetalheQtd"></span>
            <div class="cp-acoes">
                <a href="#" target="_blank" id="relDetalheChecking" class="cp-btn cp-btn-checking" title="Checking fotográfico desta campanha">📸 Checking</a>
                <a href="#" target="_blank" id="relDetalhePi" class="cp-btn cp-btn-docs desabilitado" title="Nenhum P.I. enviado ainda">📄 P.I.</a>
                <a href="#" target="_blank" id="relDetalhePp" class="cp-btn cp-btn-docs desabilitado" title="Nenhum P.P. enviado ainda">📄 P.P.</a>
            </div>
        </div>
        <div class="cp-card-footer" style="border-top:none; justify-content:flex-end;">
            <div class="cp-acoes">
                <a href="#" target="_blank" id="relDetalheEditar" class="cp-btn cp-btn-editar" title="Editar campanha (abre em Campanhas)">✏️ Editar</a>
                <a href="#" target="_blank" id="relDetalheRenovar" class="cp-btn cp-btn-renovar" title="Renovar campanha (abre em Campanhas)">🔄 Renovar</a>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(name, btn) {
    document.querySelectorAll('.tab-content').forEach(function(t){ t.classList.remove('active'); });
    document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); });
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}

(function(){
    var hash = location.hash.replace('#','');
    var tabs = ['contratos','clientes','agencias','ocupacao'];
    var idx = tabs.indexOf(hash);
    if (idx !== -1) {
        document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); });
        document.querySelectorAll('.tab-content').forEach(function(t){ t.classList.remove('active'); });
        document.getElementById('tab-' + hash).classList.add('active');
        document.querySelectorAll('.tab-btn')[idx].classList.add('active');
    }
})();

function exportCSV(tableId, filename) {
    var table = document.getElementById(tableId);
    if (!table) { alert('Tabela não encontrada.'); return; }
    var csv = '';
    var rows = table.querySelectorAll('tr');
    for (var i=0; i<rows.length; i++) {
        var cells = rows[i].querySelectorAll('th, td');
        var row = [];
        for (var j=0; j<cells.length; j++) {
            var txt = cells[j].innerText.trim().replace(/\n/g,' ');
            if (txt.indexOf(',') !== -1 || txt.indexOf('"') !== -1) txt = '"' + txt.replace(/"/g,'""') + '"';
            row.push(txt);
        }
        csv += row.join(',') + '\n';
    }
    var blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename + '_' + new Date().toISOString().slice(0,10) + '.csv';
    link.click();
}

// ── Modal de detalhes do contrato ─────────────────────────────
var CORES_SITUACAO = {
    'Ocupado': '#dc3545', 'Reservado': '#fd7e14',
    'Permuta': '#51086e', 'Bisemana': '#0284c7',
    'Vencido': '#6c757d'
};

function abrirDetalhesContrato(c) {
    var cor = CORES_SITUACAO[c.situacao] || '#888';

    document.getElementById('relDetalheFaixa').style.background = cor;
    document.getElementById('relDetalheBadge').style.background = cor;
    document.getElementById('relDetalheBadge').textContent = c.situacao || 'Ocupado';
    document.getElementById('relDetalheNome').textContent = c.campanha || 'Sem nome';
    document.getElementById('relDetalheCliente').textContent = c.cliente || '-';

    var agenciaEl = document.getElementById('relDetalheAgencia');
    if (c.agencia) { agenciaEl.textContent = c.agencia; agenciaEl.style.display = ''; }
    else { agenciaEl.style.display = 'none'; }

    document.getElementById('relDetalhePeriodo').textContent =
        (c.inicio_fmt || '?') + ' → ' + (c.fim_fmt || '?');

    var pontos = c.pontos || [];
    var htmlPontos = pontos.map(function(p) {
        var num = String(p.numero || '').padStart(3, '0');
        var cidReg = [p.cidade, p.regiao].filter(Boolean).join(' · ');
        var link = p.ponto_id
            ? '<a href="/gestor/pontos/detalhes?id=' + p.ponto_id + '" class="cp-painel-link" title="Ver ponto" target="_blank">→</a>'
            : '';
        return '<div class="cp-painel-row">' +
            '<span class="cp-painel-num">' + num + '</span>' +
            '<div class="cp-painel-end">' +
                '<div class="cp-painel-log">' + (p.logradouro || '') + '</div>' +
                '<div class="cp-painel-cid">' + cidReg + '</div>' +
            '</div>' + link +
        '</div>';
    }).join('');
    document.getElementById('relDetalhePontos').innerHTML = htmlPontos || '<div class="cp-empty">Nenhum ponto vinculado.</div>';
    document.getElementById('relDetalheQtd').textContent = pontos.length + ' ponto' + (pontos.length > 1 ? 's' : '');

    var pontoIds = pontos.map(function(p){ return p.ponto_id; }).filter(Boolean);
    var params = new URLSearchParams();
    params.set('cliente', c.cliente || '');
    params.set('agencia', c.agencia || '');
    params.set('campanha', c.campanha || '');
    params.set('situacao', c.situacao || 'Ocupado');
    params.set('inicio', (c.inicio_contrato || '').substring(0, 10));
    params.set('fim', (c.fim_contrato || '').substring(0, 10));
    pontoIds.forEach(function(id){ params.append('pontoIds[]', id); });

    document.getElementById('relDetalheChecking').href = '/gestor/campanhas/checking?' + params.toString();

    var documentos = c.documentos || [];
    ['PI', 'PP'].forEach(function(tipo) {
        var docs = documentos.filter(function(d){ return d.tipo === tipo; });
        var el = document.getElementById(tipo === 'PI' ? 'relDetalhePi' : 'relDetalhePp');
        if (docs.length === 0) {
            el.href = '#';
            el.classList.add('desabilitado');
            el.title = 'Nenhum ' + tipo.replace('PI','P.I.').replace('PP','P.P.') + ' enviado ainda';
        } else {
            el.href = '/' + docs[0].caminho;
            el.classList.remove('desabilitado');
            el.title = docs.length > 1 ? (docs.length + ' arquivos — abrindo o mais recente') : 'Ver arquivo';
        }
    });

    var alvoParams = new URLSearchParams();
    alvoParams.set('cliente', c.cliente || '');
    alvoParams.set('agencia', c.agencia || '');
    alvoParams.set('campanha', c.campanha || '');
    alvoParams.set('situacao', c.situacao || '');
    alvoParams.set('inicio', (c.inicio_contrato || '').substring(0, 10));
    alvoParams.set('fim', (c.fim_contrato || '').substring(0, 10));

    document.getElementById('relDetalheEditar').href  = '/gestor/campanhas?acao=editar&'  + alvoParams.toString();
    document.getElementById('relDetalheRenovar').href = '/gestor/campanhas?acao=renovar&' + alvoParams.toString();

    document.getElementById('relDetalheOverlay').classList.add('aberto');
}

function fecharDetalhesContrato() {
    document.getElementById('relDetalheOverlay').classList.remove('aberto');
}

document.getElementById('relDetalheOverlay').addEventListener('click', function(e) {
    if (e.target === this) fecharDetalhesContrato();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') fecharDetalhesContrato();
});
</script>

</body>
</html>
