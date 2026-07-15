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

$periodoHistorico = $_GET['periodo_historico'] ?? '3m';

$ocupacao  = $controller->dadosOcupacao();
$contratos = $controller->dadosContratos();
$clientes  = $controller->dadosClientes();
$historico = $controller->dadosHistorico($periodoHistorico);

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
    if (empty($lista)) {
        echo '<div class="empty-state"><p>Nenhum contrato encontrado.</p></div>';
        return;
    }
    ?>
    <div class="table-container">
        <table class="rel-table">
            <thead>
                <tr><th>Cliente</th><th>Campanha</th><th>Agência</th><th>Início</th><th>Fim</th><th>Duração</th><th style="text-align:right">Pontos</th></tr>
            </thead>
            <tbody>
                <?php foreach ($lista as $c): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($c['cliente'] ?? '-') ?></strong></td>
                    <td><?= htmlspecialchars($c['campanha'] ?? '-') ?></td>
                    <td style="color:var(--color-text-muted)"><?= htmlspecialchars($c['agencia'] ?? '-') ?></td>
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

$periodoOpcoes = ['15d' => '15 dias', '1m' => '1 mês', '3m' => '3 meses', '6m' => '6 meses', '12m' => '12 meses'];
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
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="container" style="padding-top:0.75rem; padding-bottom:2rem;">

    <div class="welcome" style="margin-bottom:1rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.75rem;">
        <div>
            <h2>📊 Relatórios</h2>
            <p>Ocupação, contratos, clientes e histórico — visão comercial para apresentação à diretoria.</p>
        </div>
        <a class="btn-export btn-pdf-mensal" href="/gestor/relatorios/pdf?periodo_historico=<?= urlencode($periodoHistorico) ?>" target="_blank">
            📄 Gerar Relatório Mensal (PDF)
        </a>
    </div>

    <div class="tabs-nav" id="tabsNav">
        <button class="tab-btn active" onclick="switchTab('ocupacao',this)">🗺️ Ocupação</button>
        <button class="tab-btn" onclick="switchTab('contratos',this)">📅 Contratos &amp; Tempo de Contrato</button>
        <button class="tab-btn" onclick="switchTab('clientes',this)">🏢 Clientes &amp; Agências</button>
        <button class="tab-btn" onclick="switchTab('historico',this)">🕒 Histórico / Auditoria</button>
    </div>

    <!-- ============================================================ -->
    <!-- ABA 1: OCUPAÇÃO                                               -->
    <!-- ============================================================ -->
    <div class="tab-content active" id="tab-ocupacao">

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
    <div class="tab-content" id="tab-contratos">

        <div class="kpi-grid" style="grid-template-columns:minmax(180px,220px); margin-bottom:1.25rem;">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#eef6ff;">📄</div>
                <div class="kpi-body">
                    <div class="kpi-value" style="color:#3498db"><?= count($contratos['contratos_com_duracao']) ?></div>
                    <div class="kpi-label">Contratos Ativos</div>
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

        <!-- ===== Contratos Vencendo / Vencidos ===== -->
        <div class="section-title" style="margin-top:1.5rem">📅 Contratos Vencendo</div>

        <div class="kpi-grid" style="grid-template-columns:minmax(180px,220px); margin-bottom:1.25rem;">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#f9f0ff;">🔴</div>
                <div class="kpi-body">
                    <div class="kpi-value" style="color:#8e44ad"><?= count($contratos['vencidos']) ?></div>
                    <div class="kpi-label">Já Vencidos</div>
                </div>
            </div>
        </div>

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

        <div class="section-title" style="margin-top:1.5rem">🔴 Contratos Vencidos (<?= count($contratos['vencidos']) ?>)</div>
        <?php if (empty($contratos['vencidos'])): ?>
            <div class="empty-state"><p>Nenhum contrato vencido encontrado.</p></div>
        <?php else: ?>
        <div class="table-container">
            <table class="rel-table" id="tbl-vencidos">
                <thead>
                    <tr><th>Nº</th><th>Logradouro</th><th>Cidade</th><th>Cliente</th><th>Agência</th><th>Venceu em</th><th>Dias Vencido</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($contratos['vencidos'] as $c): ?>
                    <tr class="row-vencida">
                        <td><strong style="color:#8e44ad"><?= htmlspecialchars($c['numero']) ?></strong></td>
                        <td><?= htmlspecialchars($c['logradouro'] ?? '') ?></td>
                        <td><?= htmlspecialchars($c['cidade'] ?? '') ?></td>
                        <td><?= htmlspecialchars($c['cliente'] ?? '-') ?></td>
                        <td style="color:var(--color-text-muted)"><?= htmlspecialchars($c['agencia'] ?? '-') ?></td>
                        <td><?= fmtData($c['fim_contrato']) ?></td>
                        <td><span class="tag-vencido"><?= $c['dias_vencido'] ?> dias</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (!empty($contratos['duracao_agregada']['por_regiao'])): ?>
        <div class="bloco-section" style="margin-top:1.5rem;">
            <div class="bloco-label">Duração Média por Região</div>
            <div class="table-container" style="margin-top:0.5rem;">
                <table class="rel-table">
                    <thead><tr><th>Região</th><th style="text-align:right">Contratos</th><th style="text-align:right">Duração Média</th></tr></thead>
                    <tbody>
                        <?php foreach ($contratos['duracao_agregada']['por_regiao'] as $regiao => $d): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($regiao) ?></strong></td>
                            <td style="text-align:right"><?= $d['qtd'] ?></td>
                            <td style="text-align:right"><?= fmtDuracao($d['media_dias']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /tab-contratos -->


    <!-- ============================================================ -->
    <!-- ABA 3: CLIENTES & AGÊNCIAS                                   -->
    <!-- ============================================================ -->
    <div class="tab-content" id="tab-clientes">

        <div class="export-bar">
            <button class="btn-export btn-csv" onclick="exportCSV('tbl-clientes','pontos-por-cliente')">⬇ CSV</button>
        </div>

        <div class="kpi-grid" style="grid-template-columns:repeat(2,1fr); margin-bottom:1.25rem;">
            <div class="kpi-card kpi-total">
                <div class="kpi-icon">🏢</div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= count($clientes['clientes']) ?></div>
                    <div class="kpi-label">Clientes</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#eef6ff;">🏛️</div>
                <div class="kpi-body">
                    <div class="kpi-value" style="color:#3498db"><?= count($clientes['agencias']) ?></div>
                    <div class="kpi-label">Agências</div>
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

        <?php if (!empty($clientes['agencias'])): ?>
        <div class="section-title" style="margin-top:1.5rem">🏛️ Resumo por Agência (<?= count($clientes['agencias']) ?>)</div>
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

    </div><!-- /tab-clientes -->


    <!-- ============================================================ -->
    <!-- ABA 4: HISTÓRICO / AUDITORIA                                  -->
    <!-- ============================================================ -->
    <div class="tab-content" id="tab-historico">

        <div class="export-bar">
            <button class="btn-export btn-csv" onclick="exportCSV('tbl-timeline','historico-pontos')">⬇ CSV</button>
        </div>

        <div class="periodo-pills">
            <span style="font-size:0.82rem;font-weight:700;color:var(--color-text-muted);">Período:</span>
            <?php foreach ($periodoOpcoes as $chave => $label): ?>
            <a href="?periodo_historico=<?= $chave ?>#historico" class="pill <?= $periodoHistorico==$chave?'active':'' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>

        <div class="kpi-grid" style="margin-bottom:1.25rem;">
            <div class="kpi-card kpi-total">
                <div class="kpi-icon">🕒</div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= number_format($historico['total_mudancas']) ?></div>
                    <div class="kpi-label">Mudanças em <?= $historico['periodo_label'] ?></div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#f9f0ff;">🔄</div>
                <div class="kpi-body">
                    <div class="kpi-value" style="color:#8e44ad"><?= count($historico['rotatividade']) ?></div>
                    <div class="kpi-label">Pontos com mais giro</div>
                </div>
            </div>
        </div>

        <div class="section-title">🔄 Rotatividade — Pontos com Mais Mudanças de Situação</div>
        <?php if (empty($historico['rotatividade'])): ?>
            <div class="empty-state"><p>Nenhuma mudança de situação registrada em <?= $historico['periodo_label'] ?>.</p></div>
        <?php else: ?>
        <div class="table-container">
            <table class="rel-table" id="tbl-rotatividade">
                <thead><tr><th>Nº</th><th>Logradouro</th><th>Cidade</th><th style="text-align:right">Mudanças de Situação</th></tr></thead>
                <tbody>
                    <?php foreach ($historico['rotatividade'] as $r): ?>
                    <tr>
                        <td><strong style="color:var(--color-accent-primary)"><?= htmlspecialchars($r['numero']) ?></strong></td>
                        <td><?= htmlspecialchars($r['logradouro'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['cidade'] ?? '') ?></td>
                        <td style="text-align:right"><strong><?= $r['total_mudancas'] ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="section-title" style="margin-top:1.5rem">📜 Linha do Tempo — Últimas Alterações</div>
        <?php if (empty($historico['timeline'])): ?>
            <div class="empty-state"><p>Nenhuma alteração registrada em <?= $historico['periodo_label'] ?>.</p></div>
        <?php else: ?>
        <div class="table-container">
            <table class="rel-table" id="tbl-timeline">
                <thead><tr><th>Data/Hora</th><th>Nº</th><th>Logradouro</th><th>Campo</th><th>De</th><th>Para</th></tr></thead>
                <tbody>
                    <?php foreach ($historico['timeline'] as $h): ?>
                    <tr>
                        <td style="font-size:0.78rem;white-space:nowrap;"><?= (new DateTime($h['alterado_em']))->format('d/m/Y H:i') ?></td>
                        <td><strong style="color:var(--color-accent-primary)"><?= htmlspecialchars($h['numero']) ?></strong></td>
                        <td><?= htmlspecialchars($h['logradouro'] ?? '') ?></td>
                        <td><?= htmlspecialchars($h['campo']) ?></td>
                        <td style="color:var(--color-text-muted)"><?= htmlspecialchars($h['valor_antes'] ?? '-') ?></td>
                        <td><strong><?= htmlspecialchars($h['valor_depois'] ?? '-') ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div><!-- /tab-historico -->

</div><!-- /container -->

<script>
function switchTab(name, btn) {
    document.querySelectorAll('.tab-content').forEach(function(t){ t.classList.remove('active'); });
    document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); });
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}

(function(){
    var hash = location.hash.replace('#','');
    var tabs = ['ocupacao','contratos','clientes','historico'];
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
</script>

</body>
</html>
