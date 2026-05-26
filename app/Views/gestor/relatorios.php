<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

$paginaAtual = 'relatorios';

try {
    require_once __DIR__ . '/../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// ============================================================
// RELATÓRIO 1: Ocupação por Região/Cidade
// — sem "outros", adicionado "vencidos" (fim_contrato < hoje)
// ============================================================
$sqlOcupacaoRegiao = "
    SELECT
        COALESCE(NULLIF(TRIM(regiao), ''), 'Sem Região') AS regiao,
        COUNT(*) AS total,
        SUM(CASE WHEN LOWER(situacao) = 'ocupado' THEN 1 ELSE 0 END) AS ocupados,
        SUM(CASE WHEN LOWER(situacao) IN ('disponivel','disponível') THEN 1 ELSE 0 END) AS disponiveis,
        SUM(CASE WHEN LOWER(situacao) = 'reservado' THEN 1 ELSE 0 END) AS reservados,
        SUM(CASE WHEN fim_contrato IS NOT NULL AND fim_contrato != '0000-00-00' AND fim_contrato < CURDATE() THEN 1 ELSE 0 END) AS vencidos
    FROM pontos
    WHERE ativo = 1 OR ativo IS NULL
    GROUP BY regiao
    ORDER BY total DESC
";

// Todas as cidades, sem LIMIT
$sqlOcupacaoCidade = "
    SELECT
        COALESCE(NULLIF(TRIM(cidade), ''), 'Sem Cidade') AS cidade,
        COUNT(*) AS total,
        SUM(CASE WHEN LOWER(situacao) = 'ocupado' THEN 1 ELSE 0 END) AS ocupados,
        SUM(CASE WHEN LOWER(situacao) IN ('disponivel','disponível') THEN 1 ELSE 0 END) AS disponiveis,
        SUM(CASE WHEN LOWER(situacao) = 'reservado' THEN 1 ELSE 0 END) AS reservados,
        SUM(CASE WHEN fim_contrato IS NOT NULL AND fim_contrato != '0000-00-00' AND fim_contrato < CURDATE() THEN 1 ELSE 0 END) AS vencidos
    FROM pontos
    WHERE ativo = 1 OR ativo IS NULL
    GROUP BY cidade
    ORDER BY cidade ASC
";

$ocupacaoRegiao = $pdo->query($sqlOcupacaoRegiao)->fetchAll(PDO::FETCH_ASSOC);
$ocupacaoCidade = $pdo->query($sqlOcupacaoCidade)->fetchAll(PDO::FETCH_ASSOC);

$totalGeralPontos = array_sum(array_column($ocupacaoRegiao, 'total'));
$totalOcupados    = array_sum(array_column($ocupacaoRegiao, 'ocupados'));
$totalDisponiveis = array_sum(array_column($ocupacaoRegiao, 'disponiveis'));
$totalReservados  = array_sum(array_column($ocupacaoRegiao, 'reservados'));
$totalVencidos    = array_sum(array_column($ocupacaoRegiao, 'vencidos'));

// ============================================================
// RELATÓRIO 2: Contratos Vencendo por Período
// — pills de 15 dias a 12 meses
// — contratos vencidos: TODOS, sem LIMIT
// ============================================================
$periodoMeses = isset($_GET['periodo']) ? $_GET['periodo'] : '3m';
$periodoOpcoes = array('15d', '1m', '3m', '6m', '12m');
if (!in_array($periodoMeses, $periodoOpcoes)) $periodoMeses = '3m';

// Converte período para SQL
if ($periodoMeses === '15d') {
    $intervalSQL = "INTERVAL 15 DAY";
    $periodoLabel = "15 dias";
} elseif ($periodoMeses === '1m') {
    $intervalSQL = "INTERVAL 1 MONTH";
    $periodoLabel = "1 mês";
} elseif ($periodoMeses === '3m') {
    $intervalSQL = "INTERVAL 3 MONTH";
    $periodoLabel = "3 meses";
} elseif ($periodoMeses === '6m') {
    $intervalSQL = "INTERVAL 6 MONTH";
    $periodoLabel = "6 meses";
} else {
    $intervalSQL = "INTERVAL 12 MONTH";
    $periodoLabel = "12 meses";
}

$sqlContratosVencendo = "
    SELECT
        p.numero, p.logradouro, p.cidade, p.regiao, p.contato, p.tipo, p.situacao,
        COALESCE(c.cliente, p.cliente) AS cliente,
        COALESCE(c.agencia, p.agencia) AS agencia,
        COALESCE(DATE(c.fim), DATE(p.fim_contrato)) AS fim_contrato,
        DATEDIFF(COALESCE(DATE(c.fim), DATE(p.fim_contrato)), CURDATE()) AS dias_restantes
    FROM pontos p
    LEFT JOIN campanhas c ON c.ponto_id = p.id AND c.ativo = 1 AND c.situacao = 'Ocupado'
    WHERE
        COALESCE(DATE(c.fim), DATE(p.fim_contrato)) IS NOT NULL
        AND COALESCE(DATE(c.fim), DATE(p.fim_contrato)) != '0000-00-00'
        AND COALESCE(DATE(c.fim), DATE(p.fim_contrato)) >= CURDATE()
        AND COALESCE(DATE(c.fim), DATE(p.fim_contrato)) <= DATE_ADD(CURDATE(), $intervalSQL)
        AND (p.ativo = 1 OR p.ativo IS NULL)
    ORDER BY fim_contrato ASC
";
$contratosVencendo = $pdo->query($sqlContratosVencendo)->fetchAll(PDO::FETCH_ASSOC);

// TODOS os contratos vencidos, sem LIMIT
$sqlVencidos = "
    SELECT
        p.numero, p.logradouro, p.cidade, p.regiao, p.contato,
        COALESCE(c.cliente, p.cliente) AS cliente,
        COALESCE(c.agencia, p.agencia) AS agencia,
        COALESCE(DATE(c.fim), DATE(p.fim_contrato)) AS fim_contrato,
        DATEDIFF(CURDATE(), COALESCE(DATE(c.fim), DATE(p.fim_contrato))) AS dias_vencido
    FROM pontos p
    LEFT JOIN campanhas c ON c.ponto_id = p.id AND c.ativo = 1 AND c.situacao = 'Ocupado'
    WHERE
        COALESCE(DATE(c.fim), DATE(p.fim_contrato)) IS NOT NULL
        AND COALESCE(DATE(c.fim), DATE(p.fim_contrato)) != '0000-00-00'
        AND COALESCE(DATE(c.fim), DATE(p.fim_contrato)) < CURDATE()
        AND (p.ativo = 1 OR p.ativo IS NULL)
    ORDER BY fim_contrato DESC
";
$contratosVencidos = $pdo->query($sqlVencidos)->fetchAll(PDO::FETCH_ASSOC);

// Agrupamento por mês para timeline
$vencendoPorMes = array();
foreach ($contratosVencendo as $c) {
    $dt = new DateTime($c['fim_contrato']);
    $mes = $dt->format('Y-m');
    $vencendoPorMes[$mes] = isset($vencendoPorMes[$mes]) ? $vencendoPorMes[$mes] + 1 : 1;
}
ksort($vencendoPorMes);

// ============================================================
// RELATÓRIO 3: Pontos por Cliente/Agência
// — sem Sem Agência, ordem alfabética de cliente e agência
// — sem top10, "Pontos com Contrato Ativo" no lugar de "Alocados"
// ============================================================
$sqlClientes = "
    SELECT
        TRIM(COALESCE(c.cliente, p.cliente)) AS cliente,
        CASE
            WHEN COALESCE(NULLIF(TRIM(c.agencia),''), NULLIF(TRIM(p.agencia),'')) IS NULL
              OR COALESCE(NULLIF(TRIM(c.agencia),''), NULLIF(TRIM(p.agencia),'')) = '-'
            THEN 'Cliente direto'
            ELSE TRIM(COALESCE(c.agencia, p.agencia))
        END AS agencia,
        COUNT(*) AS total_pontos,
        SUM(CASE WHEN LOWER(p.situacao) = 'ocupado' THEN 1 ELSE 0 END) AS ocupados,
        MIN(COALESCE(DATE(c.inicio), DATE(p.inicio_contrato))) AS inicio_mais_antigo,
        MAX(COALESCE(DATE(c.fim), DATE(p.fim_contrato))) AS fim_mais_recente
    FROM pontos p
    LEFT JOIN campanhas c ON c.ponto_id = p.id AND c.ativo = 1 AND c.situacao = 'Ocupado'
    WHERE
        COALESCE(NULLIF(TRIM(c.cliente),''), NULLIF(TRIM(p.cliente),'')) IS NOT NULL
        AND COALESCE(NULLIF(TRIM(c.cliente),''), NULLIF(TRIM(p.cliente),'')) != '-'
        AND (p.ativo = 1 OR p.ativo IS NULL)
    GROUP BY
        TRIM(COALESCE(c.cliente, p.cliente)),
        CASE
            WHEN COALESCE(NULLIF(TRIM(c.agencia),''), NULLIF(TRIM(p.agencia),'')) IS NULL
              OR COALESCE(NULLIF(TRIM(c.agencia),''), NULLIF(TRIM(p.agencia),'')) = '-'
            THEN 'Cliente direto'
            ELSE TRIM(COALESCE(c.agencia, p.agencia))
        END
    ORDER BY cliente ASC, agencia ASC
";
$clientesData = $pdo->query($sqlClientes)->fetchAll(PDO::FETCH_ASSOC);

// Resumo por agência (inclui "Cliente direto")
$sqlAgencias = "
    SELECT
        CASE
            WHEN COALESCE(NULLIF(TRIM(c.agencia),''), NULLIF(TRIM(p.agencia),'')) IS NULL
              OR COALESCE(NULLIF(TRIM(c.agencia),''), NULLIF(TRIM(p.agencia),'')) = '-'
            THEN 'Cliente direto'
            ELSE TRIM(COALESCE(c.agencia, p.agencia))
        END AS agencia,
        COUNT(DISTINCT NULLIF(TRIM(COALESCE(c.cliente, p.cliente)),'')) AS total_clientes,
        COUNT(*) AS total_pontos
    FROM pontos p
    LEFT JOIN campanhas c ON c.ponto_id = p.id AND c.ativo = 1 AND c.situacao = 'Ocupado'
    WHERE
        COALESCE(NULLIF(TRIM(c.cliente),''), NULLIF(TRIM(p.cliente),'')) IS NOT NULL
        AND COALESCE(NULLIF(TRIM(c.cliente),''), NULLIF(TRIM(p.cliente),'')) != '-'
        AND (p.ativo = 1 OR p.ativo IS NULL)
    GROUP BY
        CASE
            WHEN COALESCE(NULLIF(TRIM(c.agencia),''), NULLIF(TRIM(p.agencia),'')) IS NULL
              OR COALESCE(NULLIF(TRIM(c.agencia),''), NULLIF(TRIM(p.agencia),'')) = '-'
            THEN 'Cliente direto'
            ELSE TRIM(COALESCE(c.agencia, p.agencia))
        END
    ORDER BY total_pontos DESC
";
$agenciasData = $pdo->query($sqlAgencias)->fetchAll(PDO::FETCH_ASSOC);

$totalPontosComContrato = array_sum(array_column($clientesData, 'total_pontos'));

// ============================================================
// Qualidade de dados — alertas operacionais
// ============================================================
$semFoto = (int)$pdo->query("
    SELECT COUNT(*) FROM pontos p
    WHERE (p.ativo = 1 OR p.ativo IS NULL)
      AND (p.foto IS NULL OR p.foto = '')
      AND NOT EXISTS (SELECT 1 FROM ponto_fotos pf WHERE pf.ponto_id = p.id)
")->fetchColumn();

$semCoord = (int)$pdo->query("
    SELECT COUNT(*) FROM pontos
    WHERE (ativo = 1 OR ativo IS NULL)
      AND (latitude IS NULL OR latitude = 0 OR longitude IS NULL OR longitude = 0)
")->fetchColumn();

// ============================================================
// Helpers
// ============================================================
function pct($valor, $total) {
    return $total > 0 ? round(($valor / $total) * 100, 1) : 0;
}

function diasClass($dias) {
    if ($dias <= 15) return 'urgente';
    if ($dias <= 30) return 'atencao';
    return 'ok';
}

function mesLabel($mesStr) {
    $meses = array('01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
                   '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez');
    list($ano, $m) = explode('-', $mesStr);
    return (isset($meses[$m]) ? $meses[$m] : $m) . '/' . substr($ano, 2);
}

function fmtData($data) {
    if (!$data || $data === '0000-00-00') return '-';
    try {
        $d = new DateTime($data);
        return $d->format('d/m/Y');
    } catch (Exception $e) { return '-'; }
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
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="container" style="padding-top:0.75rem; padding-bottom:2rem;">

    <div class="welcome" style="margin-bottom:1rem;">
        <h2>📊 Relatórios</h2>
        <p>Análise de ocupação, contratos e distribuição por cliente — atualizado em tempo real.</p>
    </div>

    <div class="tabs-nav" id="tabsNav">
        <button class="tab-btn active" onclick="switchTab('ocupacao',this)">🗺️ Ocupação por Região/Cidade</button>
        <button class="tab-btn" onclick="switchTab('contratos',this)">📅 Contratos Vencendo</button>
        <button class="tab-btn" onclick="switchTab('clientes',this)">🏢 Pontos por Cliente</button>
    </div>

    <!-- ============================================================ -->
    <!-- ABA 1: OCUPAÇÃO                                               -->
    <!-- ============================================================ -->
    <div class="tab-content active" id="tab-ocupacao">

        <div class="export-bar">
            <button class="btn-export btn-pdf" onclick="exportPDF('tab-ocupacao','relatorio-ocupacao')">⬇ PDF</button>
            <button class="btn-export btn-csv" onclick="exportCSV('tbl-regiao-hidden','ocupacao-regiao')">⬇ CSV</button>
        </div>

        <!-- KPIs -->
        <div class="kpi-grid">
            <div class="kpi-card kpi-total">
                <div class="kpi-icon">📍</div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= number_format($totalGeralPontos) ?></div>
                    <div class="kpi-label">Total de Pontos</div>
                </div>
            </div>
            <div class="kpi-card kpi-ocup">
                <div class="kpi-icon">🔴</div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= number_format($totalOcupados) ?></div>
                    <div class="kpi-label">Ocupados</div>
                    <div class="kpi-sub"><?= pct($totalOcupados, $totalGeralPontos) ?>% do total</div>
                </div>
            </div>
            <div class="kpi-card kpi-disp">
                <div class="kpi-icon">🟢</div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= number_format($totalDisponiveis) ?></div>
                    <div class="kpi-label">Disponíveis</div>
                    <div class="kpi-sub"><?= pct($totalDisponiveis, $totalGeralPontos) ?>% do total</div>
                </div>
            </div>
            <div class="kpi-card kpi-res">
                <div class="kpi-icon">🟡</div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= number_format($totalReservados) ?></div>
                    <div class="kpi-label">Reservados</div>
                    <div class="kpi-sub"><?= pct($totalReservados, $totalGeralPontos) ?>% do total</div>
                </div>
            </div>
            <div class="kpi-card kpi-venc">
                <div class="kpi-icon">🟣</div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= number_format($totalVencidos) ?></div>
                    <div class="kpi-label">Ctr. Vencidos</div>
                    <div class="kpi-sub"><?= pct($totalVencidos, $totalGeralPontos) ?>% do total</div>
                </div>
            </div>
        </div>

        <!-- Donut + legenda -->
        <div class="donut-wrapper">
            <svg class="donut-svg" width="130" height="130" viewBox="0 0 130 130" id="donutSvg"></svg>
            <div class="donut-legend">
                <div class="legend-item">
                    <div class="legend-dot" style="background:#e34c3e"></div>
                    <span class="legend-name">Ocupados</span>
                    <span class="legend-val"><?= $totalOcupados ?></span>
                    <span class="legend-pct"><?= pct($totalOcupados,$totalGeralPontos) ?>%</span>
                </div>
                <div class="legend-item">
                    <div class="legend-dot" style="background:#27ae60"></div>
                    <span class="legend-name">Disponíveis</span>
                    <span class="legend-val"><?= $totalDisponiveis ?></span>
                    <span class="legend-pct"><?= pct($totalDisponiveis,$totalGeralPontos) ?>%</span>
                </div>
                <div class="legend-item">
                    <div class="legend-dot" style="background:#f39c12"></div>
                    <span class="legend-name">Reservados</span>
                    <span class="legend-val"><?= $totalReservados ?></span>
                    <span class="legend-pct"><?= pct($totalReservados,$totalGeralPontos) ?>%</span>
                </div>
                <div class="legend-item" style="margin-top:0.35rem;padding-top:0.35rem;border-top:1px dashed var(--color-border);">
                    <div class="legend-dot" style="background:#8e44ad"></div>
                    <span class="legend-name" style="color:var(--color-text-muted);font-size:0.78rem;">Ctr. Vencidos *</span>
                    <span class="legend-val" style="color:#8e44ad"><?= $totalVencidos ?></span>
                    <span class="legend-pct" style="font-size:0.72rem;"><?= pct($totalVencidos,$totalGeralPontos) ?>%</span>
                </div>
                <p style="font-size:0.68rem;color:var(--color-text-muted);margin:0.25rem 0 0;">* incluídos nas situações acima</p>
            </div>
        </div>

        <!-- ===== POR REGIÃO ===== -->
        <div class="bloco-section">
            <div class="bloco-label">Por Região</div>

            <!-- Legenda das cores -->
            <div class="bar-legenda">
                <span><span class="dot-ocup"></span>Ocupado</span>
                <span><span class="dot-disp"></span>Disponível</span>
                <span><span class="dot-res"></span>Reservado</span>
                <span><span class="dot-venc"></span>Ctr. Vencido</span>
            </div>

            <?php if (empty($ocupacaoRegiao)): ?>
                <p style="color:var(--color-text-muted);font-size:0.85rem;">Nenhum dado encontrado.</p>
            <?php else: ?>
                <div class="bars-clean">
                    <?php foreach ($ocupacaoRegiao as $r): ?>
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

        <!-- ===== POR CIDADES ===== -->
        <div class="bloco-section">
            <div class="bloco-label">Por Cidades <span class="bloco-count"><?= count($ocupacaoCidade) ?></span></div>

            <?php if (empty($ocupacaoCidade)): ?>
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
                        <?php foreach ($ocupacaoCidade as $c): ?>
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

        <!-- Qualidade de dados -->
        <?php if ($semFoto > 0 || $semCoord > 0): ?>
        <div class="bloco-section" style="margin-top:1rem;">
            <div class="bloco-label">⚠️ Qualidade dos Dados</div>
            <div class="kpi-grid" style="margin-top:0.75rem;">
                <?php if ($semFoto > 0): ?>
                <div class="kpi-card" style="border-left:3px solid #f59e0b;">
                    <div class="kpi-icon" style="background:#fef3c7;">📷</div>
                    <div class="kpi-body">
                        <div class="kpi-value" style="color:#92400e"><?= $semFoto ?></div>
                        <div class="kpi-label">Pontos sem foto</div>
                        <div class="kpi-sub"><a href="/gestor/pontos" style="color:#92400e;font-weight:700;">Ver lista →</a></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($semCoord > 0): ?>
                <div class="kpi-card" style="border-left:3px solid #f59e0b;">
                    <div class="kpi-icon" style="background:#fef3c7;">📍</div>
                    <div class="kpi-body">
                        <div class="kpi-value" style="color:#92400e"><?= $semCoord ?></div>
                        <div class="kpi-label">Sem coordenadas</div>
                        <div class="kpi-sub"><a href="/gestor/mapa" style="color:#92400e;font-weight:700;">Ver mapa →</a></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tabela oculta para exportação CSV -->
        <table id="tbl-regiao-hidden" style="display:none">
            <thead><tr><th>Região</th><th>Total</th><th>Ocupados</th><th>Disponíveis</th><th>Reservados</th><th>Ctr. Vencidos</th><th>% Ocupação</th></tr></thead>
            <tbody>
                <?php foreach ($ocupacaoRegiao as $r): ?>
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
    <!-- ABA 2: CONTRATOS                                             -->
    <!-- ============================================================ -->
    <div class="tab-content" id="tab-contratos">

        <div class="export-bar">
            <button class="btn-export btn-pdf" onclick="exportPDF('tab-contratos','relatorio-contratos')">⬇ PDF</button>
            <button class="btn-export btn-csv" onclick="exportCSV('tbl-contratos','contratos-vencendo')">⬇ CSV</button>
        </div>

        <!-- Pills: 15 dias até 12 meses -->
        <div class="periodo-pills">
            <span style="font-size:0.82rem;font-weight:700;color:var(--color-text-muted);">Vencendo em:</span>
            <a href="?periodo=15d#contratos" class="pill <?= $periodoMeses=='15d'?'active':'' ?>">15 dias</a>
            <a href="?periodo=1m#contratos"  class="pill <?= $periodoMeses=='1m' ?'active':'' ?>">1 mês</a>
            <a href="?periodo=3m#contratos"  class="pill <?= $periodoMeses=='3m' ?'active':'' ?>">3 meses</a>
            <a href="?periodo=6m#contratos"  class="pill <?= $periodoMeses=='6m' ?'active':'' ?>">6 meses</a>
            <a href="?periodo=12m#contratos" class="pill <?= $periodoMeses=='12m'?'active':'' ?>">12 meses</a>
        </div>

        <!-- KPIs -->
        <?php
        $urgentes = count(array_filter($contratosVencendo, function($c) { return $c['dias_restantes'] <= 15; }));
        $atencao  = count(array_filter($contratosVencendo, function($c) { return $c['dias_restantes'] > 15 && $c['dias_restantes'] <= 30; }));
        $tranq    = count($contratosVencendo) - $urgentes - $atencao;
        ?>
        <div class="kpi-grid" style="margin-bottom:1.25rem;">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#f0f4ff;">📅</div>
                <div class="kpi-body">
                    <div class="kpi-value" style="color:#1a237e"><?= count($contratosVencendo) ?></div>
                    <div class="kpi-label">Vencendo em <?= $periodoLabel ?></div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#fff0ef;">⚠️</div>
                <div class="kpi-body">
                    <div class="kpi-value" style="color:#c0392b"><?= $urgentes ?></div>
                    <div class="kpi-label">Urgente (≤ 15 dias)</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#fffbf0;">🔶</div>
                <div class="kpi-body">
                    <div class="kpi-value" style="color:#856404"><?= $atencao ?></div>
                    <div class="kpi-label">Atenção (16–30 dias)</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#f0faf4;">✅</div>
                <div class="kpi-body">
                    <div class="kpi-value" style="color:#27ae60"><?= $tranq ?></div>
                    <div class="kpi-label">Tranquilo (&gt; 30 dias)</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#f9f0ff;">🔴</div>
                <div class="kpi-body">
                    <div class="kpi-value" style="color:#8e44ad"><?= count($contratosVencidos) ?></div>
                    <div class="kpi-label">Já Vencidos</div>
                </div>
            </div>
        </div>

        <!-- Timeline por mês -->
        <?php if (!empty($vencendoPorMes)): ?>
        <div class="panel" style="margin-bottom:1.25rem;">
            <div class="panel-title">Distribuição por mês</div>
            <?php $maxMes = max($vencendoPorMes); ?>
            <div class="timeline-bars">
                <?php foreach ($vencendoPorMes as $mes => $qtd): ?>
                <div class="tl-col">
                    <div class="tl-bar-count"><?= $qtd ?></div>
                    <div class="tl-bar" style="height:<?= max(4,round(($qtd/$maxMes)*60)) ?>px;"></div>
                    <div class="tl-label"><?= mesLabel($mes) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tabela vencendo -->
        <?php if (empty($contratosVencendo)): ?>
            <div class="empty-state"><div class="empty-state-icon">🎉</div><p>Nenhum contrato vencendo em <?= $periodoLabel ?>.</p></div>
        <?php else: ?>
            <div class="table-container">
                <table class="rel-table" id="tbl-contratos">
                    <thead>
                        <tr>
                            <th>Nº</th>
                            <th>Logradouro</th>
                            <th>Cidade</th>
                            <th>Cliente</th>
                            <th>Agência</th>
                            <th>Contato</th>
                            <th>Vencimento</th>
                            <th>Dias</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contratosVencendo as $c):
                            $cls = diasClass($c['dias_restantes']); ?>
                        <tr>
                            <td><strong style="color:var(--color-accent-primary)"><?= htmlspecialchars($c['numero']) ?></strong></td>
                            <td><?= htmlspecialchars($c['logradouro'] ?? '') ?></td>
                            <td><?= htmlspecialchars($c['cidade'] ?? '') ?></td>
                            <td><?= htmlspecialchars($c['cliente'] ?? '-') ?></td>
                            <td style="color:var(--color-text-muted)"><?= htmlspecialchars($c['agencia'] ?? '-') ?></td>
                            <td style="font-size:0.78rem"><?= htmlspecialchars($c['contato'] ?? '-') ?></td>
                            <td><?= fmtData($c['fim_contrato']) ?></td>
                            <td><strong><?= $c['dias_restantes'] ?></strong></td>
                            <td>
                                <span class="tag-<?= $cls ?>">
                                    <?= $cls==='urgente' ? '⚠️ Urgente' : ($cls==='atencao' ? '🔶 Atenção' : '✅ OK') ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Contratos Vencidos (todos) -->
        <div class="section-title" style="margin-top:1.5rem">
            🔴 Contratos Vencidos (<?= count($contratosVencidos) ?>)
        </div>
        <?php if (empty($contratosVencidos)): ?>
            <div class="empty-state"><p>Nenhum contrato vencido encontrado.</p></div>
        <?php else: ?>
        <div class="table-container">
            <table class="rel-table" id="tbl-vencidos">
                <thead>
                    <tr>
                        <th>Nº</th>
                        <th>Logradouro</th>
                        <th>Cidade</th>
                        <th>Região</th>
                        <th>Cliente</th>
                        <th>Agência</th>
                        <th>Contato</th>
                        <th>Venceu em</th>
                        <th>Dias Vencido</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contratosVencidos as $c): ?>
                    <tr class="row-vencida">
                        <td><strong style="color:#8e44ad"><?= htmlspecialchars($c['numero']) ?></strong></td>
                        <td><?= htmlspecialchars($c['logradouro'] ?? '') ?></td>
                        <td><?= htmlspecialchars($c['cidade'] ?? '') ?></td>
                        <td><?= htmlspecialchars($c['regiao'] ?? '') ?></td>
                        <td><?= htmlspecialchars($c['cliente'] ?? '-') ?></td>
                        <td style="color:var(--color-text-muted)"><?= htmlspecialchars($c['agencia'] ?? '-') ?></td>
                        <td style="font-size:0.78rem"><?= htmlspecialchars($c['contato'] ?? '-') ?></td>
                        <td><?= fmtData($c['fim_contrato']) ?></td>
                        <td><span class="tag-vencido"><?= $c['dias_vencido'] ?> dias</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div><!-- /tab-contratos -->


    <!-- ============================================================ -->
    <!-- ABA 3: PONTOS POR CLIENTE                                    -->
    <!-- ============================================================ -->
    <div class="tab-content" id="tab-clientes">

        <div class="export-bar">
            <button class="btn-export btn-pdf" onclick="exportPDF('tab-clientes','relatorio-clientes')">⬇ PDF</button>
            <button class="btn-export btn-csv" onclick="exportCSV('tbl-clientes','pontos-por-cliente')">⬇ CSV</button>
        </div>

        <!-- KPIs -->
        <div class="kpi-grid" style="margin-bottom:1.25rem;">
            <div class="kpi-card kpi-total">
                <div class="kpi-icon">🏢</div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= count($clientesData) ?></div>
                    <div class="kpi-label">Clientes</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#eef6ff;">🏛️</div>
                <div class="kpi-body">
                    <div class="kpi-value" style="color:#3498db"><?= count($agenciasData) ?></div>
                    <div class="kpi-label">Agências</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#fff0ef;">📌</div>
                <div class="kpi-body">
                    <div class="kpi-value" style="color:var(--color-accent-primary)"><?= $totalPontosComContrato ?></div>
                    <div class="kpi-label">Pontos c/ Contrato</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#f0faf4;">📊</div>
                <div class="kpi-body">
                    <div class="kpi-value" style="color:#27ae60">
                        <?= count($clientesData) > 0 ? round($totalPontosComContrato / count($clientesData), 1) : 0 ?>
                    </div>
                    <div class="kpi-label">Média por Cliente</div>
                </div>
            </div>
            <div class="kpi-card kpi-ocup">
                <div class="kpi-icon">🔴</div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= array_sum(array_column($clientesData, 'ocupados')) ?></div>
                    <div class="kpi-label">Total Ocupados</div>
                </div>
            </div>
        </div>

        <!-- Tabela clientes em ordem alfabética -->
        <div class="section-title">📋 Todos os Clientes (<?= count($clientesData) ?>)</div>
        <?php if (empty($clientesData)): ?>
            <div class="empty-state"><div class="empty-state-icon">🏢</div><p>Nenhum cliente encontrado.</p></div>
        <?php else: ?>
        <div class="table-container">
            <table class="rel-table" id="tbl-clientes">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cliente</th>
                        <th>Agência</th>
                        <th>Pontos</th>
                        <th>Ocupados</th>
                        <th>Início</th>
                        <th>Fim Contrato</th>
                        <th>% dos alocados</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientesData as $i => $cl): ?>
                    <tr>
                        <td style="color:var(--color-text-muted)"><?= $i+1 ?></td>
                        <td><strong><?= htmlspecialchars($cl['cliente']) ?></strong></td>
                        <td style="color:var(--color-text-muted)"><?= htmlspecialchars($cl['agencia']) ?></td>
                        <td><strong style="color:var(--color-accent-primary)"><?= $cl['total_pontos'] ?></strong></td>
                        <td><?= $cl['ocupados'] ?></td>
                        <td style="color:var(--color-text-muted);font-size:0.78rem;">
                            <?php
                            if ($cl['inicio_mais_antigo'] && $cl['inicio_mais_antigo'] !== '0000-00-00') {
                                try { echo (new DateTime($cl['inicio_mais_antigo']))->format('m/Y'); }
                                catch(Exception $e) { echo '-'; }
                            } else { echo '-'; }
                            ?>
                        </td>
                        <td style="font-size:0.78rem;"><?= fmtData($cl['fim_mais_recente']) ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:0.4rem;">
                                <div style="flex:1;height:8px;background:#f0f0f0;border-radius:4px;overflow:hidden;">
                                    <div style="width:<?= pct($cl['total_pontos'],$totalPontosComContrato) ?>%;height:100%;background:var(--color-accent-primary);border-radius:4px;"></div>
                                </div>
                                <span style="font-size:0.75rem;font-weight:700;width:38px;text-align:right;"><?= pct($cl['total_pontos'],$totalPontosComContrato) ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Resumo por agência — ordem alfabética, sem Sem Agência -->
        <?php if (!empty($agenciasData)): ?>
        <div class="section-title" style="margin-top:1.5rem">🏛️ Resumo por Agência (<?= count($agenciasData) ?>)</div>
        <div class="table-container">
            <table class="rel-table" id="tbl-agencias">
                <thead>
                    <tr>
                        <th>Agência</th>
                        <th>Clientes</th>
                        <th>Total de Pontos</th>
                        <th>% do total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agenciasData as $ag): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($ag['agencia']) ?></strong></td>
                        <td><?= $ag['total_clientes'] ?></td>
                        <td><strong style="color:var(--color-accent-primary)"><?= $ag['total_pontos'] ?></strong></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:0.4rem;">
                                <div style="flex:1;height:8px;background:#f0f0f0;border-radius:4px;overflow:hidden;">
                                    <div style="width:<?= pct($ag['total_pontos'],$totalPontosComContrato) ?>%;height:100%;background:#3498db;border-radius:4px;"></div>
                                </div>
                                <span style="font-size:0.75rem;font-weight:700;width:36px;text-align:right;"><?= pct($ag['total_pontos'],$totalPontosComContrato) ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div><!-- /tab-clientes -->

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
    var tabs = ['ocupacao','contratos','clientes'];
    if (tabs.indexOf(hash) !== -1) {
        document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); });
        document.querySelectorAll('.tab-content').forEach(function(t){ t.classList.remove('active'); });
        document.getElementById('tab-' + hash).classList.add('active');
        document.querySelectorAll('.tab-btn')[tabs.indexOf(hash)].classList.add('active');
    }
})();

// Donut — 4 fatias: ocupado, disponível, reservado, vencido
(function(){
    var data = [
        { val: <?= $totalOcupados ?>,    color: '#e34c3e' },
        { val: <?= $totalDisponiveis ?>, color: '#27ae60' },
        { val: <?= $totalReservados ?>,  color: '#f39c12' }
    ];
    var total = 0;
    for (var i=0; i<data.length; i++) total += data[i].val;
    if (total === 0) return;

    var cx=65, cy=65, r=46, stroke=20;
    var circ = 2 * Math.PI * r;
    var offset = 0;
    var svg = document.getElementById('donutSvg');

    var bg = document.createElementNS('http://www.w3.org/2000/svg','circle');
    bg.setAttribute('cx',cx); bg.setAttribute('cy',cy); bg.setAttribute('r',r);
    bg.setAttribute('fill','none'); bg.setAttribute('stroke','#f0f0f0'); bg.setAttribute('stroke-width',stroke);
    svg.appendChild(bg);

    for (var i=0; i<data.length; i++) {
        if (data[i].val === 0) continue;
        var pct = data[i].val / total;
        var dash = pct * circ;
        var circle = document.createElementNS('http://www.w3.org/2000/svg','circle');
        circle.setAttribute('cx',cx); circle.setAttribute('cy',cy); circle.setAttribute('r',r);
        circle.setAttribute('fill','none');
        circle.setAttribute('stroke', data[i].color);
        circle.setAttribute('stroke-width', stroke);
        circle.setAttribute('stroke-dasharray', dash + ' ' + circ);
        circle.setAttribute('stroke-dashoffset', -offset * circ);
        circle.setAttribute('transform','rotate(-90 ' + cx + ' ' + cy + ')');
        svg.appendChild(circle);
        offset += pct;
    }

    var t1 = document.createElementNS('http://www.w3.org/2000/svg','text');
    t1.setAttribute('x',cx); t1.setAttribute('y',cy-4);
    t1.setAttribute('text-anchor','middle'); t1.setAttribute('font-size','18');
    t1.setAttribute('font-weight','800'); t1.setAttribute('fill','#1f2736');
    t1.textContent = total;
    svg.appendChild(t1);

    var t2 = document.createElementNS('http://www.w3.org/2000/svg','text');
    t2.setAttribute('x',cx); t2.setAttribute('y',cy+12);
    t2.setAttribute('text-anchor','middle'); t2.setAttribute('font-size','9');
    t2.setAttribute('fill','#7f8c8d'); t2.setAttribute('font-weight','600');
    t2.textContent = 'PONTOS';
    svg.appendChild(t2);
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
    var blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename + '_' + new Date().toISOString().slice(0,10) + '.csv';
    link.click();
}

function exportPDF(tabId, filename) {
    var content = document.getElementById(tabId).innerHTML;
    var win = window.open('', '_blank');
    win.document.write('<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>' + filename + '</title>'
        + '<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">'
        + '<style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:Montserrat,sans-serif;font-size:11px;color:#1f2736;padding:20px}'
        + 'table{width:100%;border-collapse:collapse;margin-bottom:16px}'
        + 'th{background:#e34c3e;color:white;padding:5px 8px;font-size:10px;text-align:left}'
        + 'td{padding:5px 8px;border-bottom:1px solid #e9ecef;font-size:10px}'
        + 'tr.row-vencida td{background:#fdf4ff}'
        + '.kpi-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:16px}'
        + '.kpi-card{border:1px solid #e9ecef;border-radius:6px;padding:8px;text-align:center}'
        + '.kpi-value{font-size:16px;font-weight:800}.kpi-label{font-size:9px;font-weight:600;color:#7f8c8d;text-transform:uppercase}'
        + '.btn-export,.export-bar,.periodo-pills,.chart-bars,.timeline-bars,.donut-wrapper,.panel{display:none!important}'
        + '.section-title{font-weight:800;font-size:11px;border-bottom:2px solid #e34c3e;padding-bottom:3px;margin:12px 0 6px}'
        + '.tag-urgente{color:#c0392b;font-weight:700}.tag-atencao{color:#856404;font-weight:700}'
        + '.tag-ok{color:#065f46;font-weight:700}.tag-vencido{color:#6b21a8;font-weight:700}'
        + '</style></head><body>'
        + '<div style="margin-bottom:16px;border-bottom:2px solid #e34c3e;padding-bottom:8px;">'
        + '<strong style="font-size:16px;color:#e34c3e;">Impakto Mídia</strong>'
        + '<span style="font-size:11px;color:#7f8c8d;margin-left:8px;">Relatório gerado em '
        + new Date().toLocaleDateString('pt-BR') + ' às ' + new Date().toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'})
        + '</span></div>' + content + '</body></html>');
    win.document.close();
    setTimeout(function(){ win.print(); }, 600);
}
</script>

</body>
</html>