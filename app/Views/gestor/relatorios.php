<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: ../../../../public/index.php?erro=nao_logado");
    exit;
}

$paginaAtual = 'relatorios';

// Conectar ao banco
try {
    require_once __DIR__ . '/../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// ============================================================
// RELATÓRIO 1: Ocupação por Região/Cidade
// ============================================================
$sqlOcupacaoRegiao = "
    SELECT 
        COALESCE(NULLIF(TRIM(regiao), ''), 'Sem Região') AS regiao,
        COUNT(*) AS total,
        SUM(CASE WHEN LOWER(situacao) = 'ocupado' THEN 1 ELSE 0 END) AS ocupados,
        SUM(CASE WHEN LOWER(situacao) = 'disponivel' OR LOWER(situacao) = 'disponível' THEN 1 ELSE 0 END) AS disponiveis,
        SUM(CASE WHEN LOWER(situacao) = 'reservado' THEN 1 ELSE 0 END) AS reservados,
        SUM(CASE WHEN LOWER(situacao) NOT IN ('ocupado','disponivel','disponível','reservado') THEN 1 ELSE 0 END) AS outros
    FROM pontos
    WHERE ativo = 1 OR ativo IS NULL
    GROUP BY regiao
    ORDER BY total DESC
";

$sqlOcupacaoCidade = "
    SELECT 
        COALESCE(NULLIF(TRIM(cidade), ''), 'Sem Cidade') AS cidade,
        COUNT(*) AS total,
        SUM(CASE WHEN LOWER(situacao) = 'ocupado' THEN 1 ELSE 0 END) AS ocupados,
        SUM(CASE WHEN LOWER(situacao) = 'disponivel' OR LOWER(situacao) = 'disponível' THEN 1 ELSE 0 END) AS disponiveis,
        SUM(CASE WHEN LOWER(situacao) = 'reservado' THEN 1 ELSE 0 END) AS reservados
    FROM pontos
    WHERE ativo = 1 OR ativo IS NULL
    GROUP BY cidade
    ORDER BY total DESC
    LIMIT 20
";

$ocupacaoRegiao = $pdo->query($sqlOcupacaoRegiao)->fetchAll(PDO::FETCH_ASSOC);
$ocupacaoCidade = $pdo->query($sqlOcupacaoCidade)->fetchAll(PDO::FETCH_ASSOC);

// Totais gerais para donut
$totalGeralPontos = array_sum(array_column($ocupacaoRegiao, 'total'));
$totalOcupados    = array_sum(array_column($ocupacaoRegiao, 'ocupados'));
$totalDisponiveis = array_sum(array_column($ocupacaoRegiao, 'disponiveis'));
$totalReservados  = array_sum(array_column($ocupacaoRegiao, 'reservados'));
$totalOutros      = $totalGeralPontos - $totalOcupados - $totalDisponiveis - $totalReservados;

// ============================================================
// RELATÓRIO 2: Contratos Vencendo por Período
// ============================================================
$periodoMeses = isset($_GET['periodo']) ? (int)$_GET['periodo'] : 3;
if (!in_array($periodoMeses, [1, 3, 6, 12])) $periodoMeses = 3;

$sqlContratosVencendo = "
    SELECT 
        numero, logradouro, cidade, regiao, cliente, agencia, tipo, situacao,
        DATE(fim_contrato) AS fim_contrato,
        DATEDIFF(fim_contrato, CURDATE()) AS dias_restantes
    FROM pontos
    WHERE 
        fim_contrato IS NOT NULL 
        AND fim_contrato != '0000-00-00'
        AND fim_contrato != ''
        AND fim_contrato >= CURDATE()
        AND fim_contrato <= DATE_ADD(CURDATE(), INTERVAL :meses MONTH)
        AND (ativo = 1 OR ativo IS NULL)
    ORDER BY fim_contrato ASC
";
$stmtContratos = $pdo->prepare($sqlContratosVencendo);
$stmtContratos->bindValue(':meses', $periodoMeses, PDO::PARAM_INT);
$stmtContratos->execute();
$contratosVencendo = $stmtContratos->fetchAll(PDO::FETCH_ASSOC);

// Contratos já vencidos
$sqlVencidos = "
    SELECT 
        numero, logradouro, cidade, cliente, situacao,
        DATE(fim_contrato) AS fim_contrato,
        DATEDIFF(CURDATE(), fim_contrato) AS dias_vencido
    FROM pontos
    WHERE 
        fim_contrato IS NOT NULL 
        AND fim_contrato != '0000-00-00'
        AND fim_contrato < CURDATE()
        AND (ativo = 1 OR ativo IS NULL)
    ORDER BY fim_contrato DESC
    LIMIT 15
";
$contratosVencidos = $pdo->query($sqlVencidos)->fetchAll(PDO::FETCH_ASSOC);

// Agrupamento por mês para os que vencerão
$vencendoPorMes = [];
foreach ($contratosVencendo as $c) {
    $mes = (new DateTime($c['fim_contrato']))->format('Y-m');
    $vencendoPorMes[$mes] = ($vencendoPorMes[$mes] ?? 0) + 1;
}
ksort($vencendoPorMes);

// ============================================================
// RELATÓRIO 3: Pontos por Cliente/Agência
// ============================================================
$sqlClientes = "
    SELECT 
        COALESCE(NULLIF(TRIM(cliente), ''), 'Sem Cliente') AS cliente,
        COALESCE(NULLIF(TRIM(agencia), ''), '-') AS agencia,
        COUNT(*) AS total_pontos,
        SUM(CASE WHEN LOWER(situacao) = 'ocupado' THEN 1 ELSE 0 END) AS ocupados,
        MIN(DATE(inicio_contrato)) AS inicio_mais_antigo,
        MAX(DATE(fim_contrato)) AS fim_mais_recente
    FROM pontos
    WHERE 
        cliente IS NOT NULL AND cliente != ''
        AND (ativo = 1 OR ativo IS NULL)
    GROUP BY cliente, agencia
    ORDER BY total_pontos DESC
    LIMIT 50
";
$clientesData = $pdo->query($sqlClientes)->fetchAll(PDO::FETCH_ASSOC);

// Top 10 clientes para gráfico de barras
$top10Clientes = array_slice($clientesData, 0, 10);

// Resumo por agência
$sqlAgencias = "
    SELECT 
        COALESCE(NULLIF(TRIM(agencia), ''), 'Sem Agência') AS agencia,
        COUNT(DISTINCT NULLIF(TRIM(cliente),'')) AS total_clientes,
        COUNT(*) AS total_pontos
    FROM pontos
    WHERE ativo = 1 OR ativo IS NULL
    GROUP BY agencia
    ORDER BY total_pontos DESC
    LIMIT 15
";
$agenciasData = $pdo->query($sqlAgencias)->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// Helpers
// ============================================================
function pct($valor, $total) {
    return $total > 0 ? round(($valor / $total) * 100, 1) : 0;
}

function diasClass($dias) {
    if ($dias <= 30)  return 'urgente';
    if ($dias <= 60)  return 'atencao';
    return 'ok';
}

function mesLabel($mesStr) {
    $meses = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
              '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];
    [$ano, $m] = explode('-', $mesStr);
    return ($meses[$m] ?? $m) . '/' . substr($ano, 2);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Impakto Mídia</title>
    <link rel="icon" href="/impaktonew/public/assets/img/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/impaktonew/public/assets/css/gestor.css">
    <style>
        /* ===== ABAS ===== */
        .tabs-nav {
            display: flex;
            gap: 0;
            background: var(--color-bg-white);
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            border: 1px solid var(--color-border);
            border-bottom: none;
            overflow: hidden;
        }

        .tab-btn {
            flex: 1;
            padding: 0.85rem 1rem;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--color-text-muted);
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }

        .tab-btn:hover {
            color: var(--color-accent-primary);
            background: #fef9f8;
        }

        .tab-btn.active {
            color: var(--color-accent-primary);
            border-bottom-color: var(--color-accent-primary);
            background: #fff8f7;
        }

        .tab-content {
            display: none;
            background: var(--color-bg-white);
            border: 1px solid var(--color-border);
            border-top: none;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
            padding: 1.5rem;
            animation: fadeIn 0.25s ease;
        }

        .tab-content.active { display: block; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }

        /* ===== KPI CARDS ===== */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .kpi-card {
            background: var(--color-bg-primary);
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            padding: 1rem;
            text-align: center;
        }

        .kpi-card .kpi-value {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 0.2rem;
        }

        .kpi-card .kpi-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--color-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .kpi-card .kpi-sub {
            font-size: 0.8rem;
            color: var(--color-text-muted);
            margin-top: 0.2rem;
        }

        .kpi-total  .kpi-value { color: var(--color-text-dark); }
        .kpi-ocup   .kpi-value { color: #e34c3e; }
        .kpi-disp   .kpi-value { color: #27ae60; }
        .kpi-res    .kpi-value { color: #f39c12; }

        /* ===== GRÁFICO DE BARRAS SIMPLES ===== */
        .chart-bars {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .bar-row {
            display: grid;
            grid-template-columns: 130px 1fr 60px;
            align-items: center;
            gap: 0.5rem;
        }

        .bar-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--color-text-dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .bar-track {
            height: 20px;
            background: #f0f0f0;
            border-radius: 3px;
            overflow: hidden;
            display: flex;
        }

        .bar-fill-ocup  { background: #e34c3e; height: 100%; transition: width 0.6s ease; }
        .bar-fill-disp  { background: #27ae60; height: 100%; transition: width 0.6s ease; }
        .bar-fill-res   { background: #f39c12; height: 100%; transition: width 0.6s ease; }
        .bar-fill-outro { background: #bdc3c7;  height: 100%; transition: width 0.6s ease; }

        .bar-total {
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--color-text-dark);
            text-align: right;
        }

        /* ===== DONUT SVG ===== */
        .donut-wrapper {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--color-bg-primary);
            border-radius: var(--border-radius);
            border: 1px solid var(--color-border);
        }

        .donut-svg { flex-shrink: 0; }

        .donut-legend {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            flex: 1;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
        }

        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .legend-name { flex: 1; font-weight: 600; }
        .legend-val  { font-weight: 800; color: var(--color-text-dark); }
        .legend-pct  { color: var(--color-text-muted); font-size: 0.8rem; }

        /* ===== SEÇÃO CIDADES ===== */
        .section-title {
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--color-text-dark);
            margin: 1.5rem 0 0.75rem;
            padding-bottom: 0.4rem;
            border-bottom: 2px solid var(--color-accent-primary);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        /* ===== CONTRATOS ===== */
        .periodo-pills {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }

        .pill {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-decoration: none;
            border: 2px solid var(--color-border);
            color: var(--color-text-muted);
            transition: all 0.2s;
            background: var(--color-bg-white);
        }

        .pill:hover, .pill.active {
            background: var(--color-accent-primary);
            color: white;
            border-color: var(--color-accent-primary);
        }

        .timeline-bars {
            display: flex;
            gap: 0.5rem;
            align-items: flex-end;
            height: 80px;
            margin-bottom: 0.25rem;
        }

        .tl-col {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.2rem;
        }

        .tl-bar {
            width: 100%;
            background: var(--color-accent-primary);
            border-radius: 3px 3px 0 0;
            min-height: 4px;
            transition: height 0.5s ease;
            position: relative;
        }

        .tl-bar-count {
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--color-accent-primary);
        }

        .tl-label {
            font-size: 0.7rem;
            color: var(--color-text-muted);
            font-weight: 600;
        }

        /* Urgência */
        .tag-urgente { background: #fde8e8; color: #c0392b; border-radius: 4px; padding: 2px 7px; font-size: 0.75rem; font-weight: 700; }
        .tag-atencao { background: #fef3cd; color: #856404; border-radius: 4px; padding: 2px 7px; font-size: 0.75rem; font-weight: 700; }
        .tag-ok      { background: #d1fae5; color: #065f46; border-radius: 4px; padding: 2px 7px; font-size: 0.75rem; font-weight: 700; }
        .tag-vencido { background: #e9ecef; color: #495057; border-radius: 4px; padding: 2px 7px; font-size: 0.75rem; font-weight: 700; }

        /* ===== CLIENTES ===== */
        .clientes-bar-row {
            display: grid;
            grid-template-columns: 160px 1fr 50px;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.4rem;
        }

        .clientes-bar-fill {
            height: 22px;
            background: linear-gradient(90deg, #e34c3e, #ff7b73);
            border-radius: 3px;
            transition: width 0.6s ease;
            display: flex;
            align-items: center;
            padding-left: 6px;
        }

        .clientes-bar-fill span {
            font-size: 0.7rem;
            color: white;
            font-weight: 700;
            white-space: nowrap;
        }

        /* ===== TABELA COMPACTA DENTRO DAS ABAS ===== */
        .rel-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }

        .rel-table th {
            background: var(--color-accent-primary);
            color: white;
            padding: 0.5rem 0.75rem;
            text-align: left;
            font-weight: 700;
            font-size: 0.78rem;
        }

        .rel-table td {
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid var(--color-border);
            vertical-align: middle;
        }

        .rel-table tbody tr:hover { background: #fafafa; }

        /* ===== EXPORTAÇÃO ===== */
        .export-bar {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .btn-export {
            padding: 0.45rem 1rem;
            border-radius: var(--border-radius);
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            border: 1.5px solid;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.2s;
            font-family: 'Montserrat', sans-serif;
            text-decoration: none;
        }

        .btn-pdf {
            color: #c0392b;
            border-color: #c0392b;
            background: white;
        }

        .btn-pdf:hover {
            background: #c0392b;
            color: white;
        }

        .btn-csv {
            color: #27ae60;
            border-color: #27ae60;
            background: white;
        }

        .btn-csv:hover {
            background: #27ae60;
            color: white;
        }

        /* ===== GRID 2 COLS ===== */
        .two-col-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }

        .panel {
            background: var(--color-bg-primary);
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            padding: 1rem;
        }

        .panel-title {
            font-size: 0.82rem;
            font-weight: 800;
            color: var(--color-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.75rem;
        }

        /* ===== PRINT ===== */
        @media print {
            .header, .tabs-nav, .export-bar, .periodo-pills, .btn-logout { display: none !important; }
            .tab-content { display: block !important; border: none; padding: 0; }
            .tabs-nav + .tab-content { page-break-before: always; }
            body { font-size: 11px; }
        }

        @media (max-width: 768px) {
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .two-col-grid { grid-template-columns: 1fr; }
            .bar-row { grid-template-columns: 90px 1fr 45px; }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<div class="header">
    <div class="header-content">
        <div class="logo">
            <img src="/impaktonew/public/assets/img/logo.png" alt="Impakto Mídia" class="logo-img">
        </div>
        <nav class="main-nav">
            <a href="/impaktonew/gestor/index.php" class="nav-link">Dashboard</a>
            <a href="/impaktonew/app/Views/gestor/listar_ponto.php" class="nav-link">Pontos</a>
            <a href="/impaktonew/app/Views/gestor/relatorios/pre_selecao.php" class="nav-link">Pré-Seleção</a>
            <a href="/impaktonew/app/Views/gestor/relatorios/relatorios.php" class="nav-link active">Relatórios</a>
        </nav>
        <div class="user-info">
            <a href="/impaktonew/gestor/index.php?logout=1" class="btn-logout"
               onclick="return confirm('Tem certeza que deseja sair?')">Sair</a>
        </div>
    </div>
</div>

<div class="container" style="padding-top: 0.75rem; padding-bottom: 2rem;">

    <!-- Título da página -->
    <div class="welcome" style="margin-bottom: 1rem;">
        <h2>📊 Relatórios</h2>
        <p>Análise de ocupação, contratos e distribuição por cliente — atualizado em tempo real.</p>
    </div>

    <!-- Abas -->
    <div class="tabs-nav" id="tabsNav">
        <button class="tab-btn active" onclick="switchTab('ocupacao', this)">
            🗺️ Ocupação por Região/Cidade
        </button>
        <button class="tab-btn" onclick="switchTab('contratos', this)">
            📅 Contratos Vencendo
        </button>
        <button class="tab-btn" onclick="switchTab('clientes', this)">
            🏢 Pontos por Cliente
        </button>
    </div>

    <!-- ============================================================ -->
    <!-- ABA 1: OCUPAÇÃO                                               -->
    <!-- ============================================================ -->
    <div class="tab-content active" id="tab-ocupacao">

        <div class="export-bar">
            <button class="btn-export btn-pdf" onclick="exportPDF('tab-ocupacao', 'relatorio-ocupacao')">
                ⬇ PDF
            </button>
            <button class="btn-export btn-csv" onclick="exportCSV('tbl-regiao', 'ocupacao-regiao')">
                ⬇ CSV Regiões
            </button>
        </div>

        <!-- KPIs -->
        <div class="kpi-grid">
            <div class="kpi-card kpi-total">
                <div class="kpi-value"><?= number_format($totalGeralPontos) ?></div>
                <div class="kpi-label">Total de Pontos</div>
            </div>
            <div class="kpi-card kpi-ocup">
                <div class="kpi-value"><?= number_format($totalOcupados) ?></div>
                <div class="kpi-label">Ocupados</div>
                <div class="kpi-sub"><?= pct($totalOcupados, $totalGeralPontos) ?>% do total</div>
            </div>
            <div class="kpi-card kpi-disp">
                <div class="kpi-value"><?= number_format($totalDisponiveis) ?></div>
                <div class="kpi-label">Disponíveis</div>
                <div class="kpi-sub"><?= pct($totalDisponiveis, $totalGeralPontos) ?>% do total</div>
            </div>
            <div class="kpi-card kpi-res">
                <div class="kpi-value"><?= number_format($totalReservados) ?></div>
                <div class="kpi-label">Reservados</div>
                <div class="kpi-sub"><?= pct($totalReservados, $totalGeralPontos) ?>% do total</div>
            </div>
        </div>

        <!-- Donut + legenda -->
        <div class="donut-wrapper">
            <svg class="donut-svg" width="140" height="140" viewBox="0 0 140 140" id="donutSvg">
                <!-- Gerado via JS abaixo -->
            </svg>
            <div class="donut-legend">
                <div class="legend-item">
                    <div class="legend-dot" style="background:#e34c3e"></div>
                    <span class="legend-name">Ocupados</span>
                    <span class="legend-val"><?= $totalOcupados ?></span>
                    <span class="legend-pct">(<?= pct($totalOcupados, $totalGeralPontos) ?>%)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-dot" style="background:#27ae60"></div>
                    <span class="legend-name">Disponíveis</span>
                    <span class="legend-val"><?= $totalDisponiveis ?></span>
                    <span class="legend-pct">(<?= pct($totalDisponiveis, $totalGeralPontos) ?>%)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-dot" style="background:#f39c12"></div>
                    <span class="legend-name">Reservados</span>
                    <span class="legend-val"><?= $totalReservados ?></span>
                    <span class="legend-pct">(<?= pct($totalReservados, $totalGeralPontos) ?>%)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-dot" style="background:#bdc3c7"></div>
                    <span class="legend-name">Outros</span>
                    <span class="legend-val"><?= $totalOutros ?></span>
                    <span class="legend-pct">(<?= pct($totalOutros, $totalGeralPontos) ?>%)</span>
                </div>
            </div>
        </div>

        <!-- Gráfico de barras por região -->
        <div class="section-title">📍 Por Região</div>

        <?php if (empty($ocupacaoRegiao)): ?>
            <div class="empty-state"><div class="empty-state-icon">🗂️</div><p>Nenhum dado de região encontrado.</p></div>
        <?php else: ?>
            <div class="chart-bars" style="margin-bottom: 1.5rem;">
                <?php foreach ($ocupacaoRegiao as $r): ?>
                <?php $maxR = max(array_column($ocupacaoRegiao, 'total')); ?>
                <div class="bar-row">
                    <div class="bar-label" title="<?= htmlspecialchars($r['regiao']) ?>">
                        <?= htmlspecialchars($r['regiao']) ?>
                    </div>
                    <div class="bar-track">
                        <div class="bar-fill-ocup"  style="width: <?= pct($r['ocupados'], $r['total']) ?>%"></div>
                        <div class="bar-fill-disp"  style="width: <?= pct($r['disponiveis'], $r['total']) ?>%"></div>
                        <div class="bar-fill-res"   style="width: <?= pct($r['reservados'], $r['total']) ?>%"></div>
                        <div class="bar-fill-outro" style="width: <?= pct($r['outros'], $r['total']) ?>%"></div>
                    </div>
                    <div class="bar-total"><?= $r['total'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Tabela por região -->
            <div class="table-container">
                <table class="rel-table" id="tbl-regiao">
                    <thead>
                        <tr>
                            <th>Região</th>
                            <th>Total</th>
                            <th>Ocupados</th>
                            <th>Disponíveis</th>
                            <th>Reservados</th>
                            <th>Outros</th>
                            <th>% Ocupação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ocupacaoRegiao as $r): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['regiao']) ?></strong></td>
                            <td><strong><?= $r['total'] ?></strong></td>
                            <td style="color:#e34c3e; font-weight:700"><?= $r['ocupados'] ?></td>
                            <td style="color:#27ae60; font-weight:700"><?= $r['disponiveis'] ?></td>
                            <td style="color:#f39c12; font-weight:700"><?= $r['reservados'] ?></td>
                            <td style="color:#bdc3c7"><?= $r['outros'] ?></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:0.4rem;">
                                    <div style="flex:1; height:8px; background:#f0f0f0; border-radius:4px; overflow:hidden;">
                                        <div style="width:<?= pct($r['ocupados'],$r['total']) ?>%; height:100%; background:#e34c3e; border-radius:4px;"></div>
                                    </div>
                                    <span style="font-size:0.75rem; font-weight:700; width:36px; text-align:right;"><?= pct($r['ocupados'],$r['total']) ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Por cidade -->
        <div class="section-title">🏙️ Por Cidade (Top 20)</div>

        <?php if (empty($ocupacaoCidade)): ?>
            <div class="empty-state"><div class="empty-state-icon">🏙️</div><p>Nenhum dado de cidade encontrado.</p></div>
        <?php else: ?>
            <div class="chart-bars">
                <?php $maxC = max(array_column($ocupacaoCidade, 'total')); ?>
                <?php foreach ($ocupacaoCidade as $c): ?>
                <div class="bar-row">
                    <div class="bar-label" title="<?= htmlspecialchars($c['cidade']) ?>">
                        <?= htmlspecialchars($c['cidade']) ?>
                    </div>
                    <div class="bar-track">
                        <div class="bar-fill-ocup" style="width: <?= pct($c['ocupados'], $maxC) ?>%"></div>
                        <div class="bar-fill-disp" style="width: <?= pct($c['disponiveis'], $maxC) ?>%"></div>
                        <div class="bar-fill-res"  style="width: <?= pct($c['reservados'], $maxC) ?>%"></div>
                    </div>
                    <div class="bar-total"><?= $c['total'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div><!-- /tab-ocupacao -->


    <!-- ============================================================ -->
    <!-- ABA 2: CONTRATOS VENCENDO                                    -->
    <!-- ============================================================ -->
    <div class="tab-content" id="tab-contratos">

        <div class="export-bar">
            <button class="btn-export btn-pdf" onclick="exportPDF('tab-contratos', 'relatorio-contratos')">
                ⬇ PDF
            </button>
            <button class="btn-export btn-csv" onclick="exportCSV('tbl-contratos', 'contratos-vencendo')">
                ⬇ CSV
            </button>
        </div>

        <!-- Filtro de período -->
        <div class="periodo-pills">
            <span style="font-size:0.82rem; font-weight:700; color:var(--color-text-muted); align-self:center;">Vencendo em:</span>
            <a href="?periodo=1#contratos" class="pill <?= $periodoMeses == 1 ? 'active' : '' ?>">30 dias</a>
            <a href="?periodo=3#contratos" class="pill <?= $periodoMeses == 3 ? 'active' : '' ?>">3 meses</a>
            <a href="?periodo=6#contratos" class="pill <?= $periodoMeses == 6 ? 'active' : '' ?>">6 meses</a>
            <a href="?periodo=12#contratos" class="pill <?= $periodoMeses == 12 ? 'active' : '' ?>">12 meses</a>
        </div>

        <!-- KPIs de contratos -->
        <?php
        $urgentes = count(array_filter($contratosVencendo, fn($c) => $c['dias_restantes'] <= 30));
        $atencao  = count(array_filter($contratosVencendo, fn($c) => $c['dias_restantes'] > 30 && $c['dias_restantes'] <= 60));
        $tranq    = count($contratosVencendo) - $urgentes - $atencao;
        ?>
        <div class="kpi-grid" style="margin-bottom: 1.25rem;">
            <div class="kpi-card">
                <div class="kpi-value" style="color:var(--color-text-dark)"><?= count($contratosVencendo) ?></div>
                <div class="kpi-label">Vencendo em <?= $periodoMeses ?> mês<?= $periodoMeses > 1 ? 'es' : '' ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" style="color:#c0392b"><?= $urgentes ?></div>
                <div class="kpi-label">⚠️ Urgente (&lt; 30 dias)</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" style="color:#856404"><?= $atencao ?></div>
                <div class="kpi-label">🔶 Atenção (31–60 dias)</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" style="color:#27ae60"><?= $tranq ?></div>
                <div class="kpi-label">✅ Tranquilo (&gt; 60 dias)</div>
            </div>
        </div>

        <!-- Mini gráfico de timeline por mês -->
        <?php if (!empty($vencendoPorMes)): ?>
        <div class="panel" style="margin-bottom: 1.25rem;">
            <div class="panel-title">Distribuição por mês</div>
            <?php $maxMes = max($vencendoPorMes); ?>
            <div class="timeline-bars">
                <?php foreach ($vencendoPorMes as $mes => $qtd): ?>
                <div class="tl-col">
                    <div class="tl-bar-count"><?= $qtd ?></div>
                    <div class="tl-bar" style="height: <?= max(4, round(($qtd / $maxMes) * 60)) ?>px;"></div>
                    <div class="tl-label"><?= mesLabel($mes) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tabela de contratos vencendo -->
        <?php if (empty($contratosVencendo)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🎉</div>
                <p>Nenhum contrato vencendo nos próximos <?= $periodoMeses ?> meses.</p>
            </div>
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
                            <th>Vencimento</th>
                            <th>Dias Restantes</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contratosVencendo as $c): ?>
                        <?php $cls = diasClass($c['dias_restantes']); ?>
                        <tr>
                            <td><strong style="color:var(--color-accent-primary)"><?= htmlspecialchars($c['numero']) ?></strong></td>
                            <td><?= htmlspecialchars($c['logradouro'] ?? '') ?></td>
                            <td><?= htmlspecialchars($c['cidade'] ?? '') ?></td>
                            <td><?= htmlspecialchars($c['cliente'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($c['agencia'] ?? '-') ?></td>
                            <td>
                                <?php
                                try {
                                    $d = new DateTime($c['fim_contrato']);
                                    echo $d->format('d/m/Y');
                                } catch(Exception $e) { echo '-'; }
                                ?>
                            </td>
                            <td><strong><?= $c['dias_restantes'] ?></strong></td>
                            <td>
                                <span class="tag-<?= $cls ?>">
                                    <?= $cls === 'urgente' ? '⚠️ Urgente' : ($cls === 'atencao' ? '🔶 Atenção' : '✅ OK') ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Contratos já vencidos -->
        <?php if (!empty($contratosVencidos)): ?>
        <div class="section-title" style="margin-top:1.5rem">🔴 Contratos Já Vencidos (últimos 15)</div>
        <div class="table-container">
            <table class="rel-table">
                <thead>
                    <tr>
                        <th>Nº</th>
                        <th>Logradouro</th>
                        <th>Cidade</th>
                        <th>Cliente</th>
                        <th>Venceu em</th>
                        <th>Dias Vencido</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contratosVencidos as $c): ?>
                    <tr>
                        <td><strong style="color:var(--color-accent-primary)"><?= htmlspecialchars($c['numero']) ?></strong></td>
                        <td><?= htmlspecialchars($c['logradouro'] ?? '') ?></td>
                        <td><?= htmlspecialchars($c['cidade'] ?? '') ?></td>
                        <td><?= htmlspecialchars($c['cliente'] ?? '-') ?></td>
                        <td>
                            <?php
                            try { echo (new DateTime($c['fim_contrato']))->format('d/m/Y'); }
                            catch(Exception $e) { echo '-'; }
                            ?>
                        </td>
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
            <button class="btn-export btn-pdf" onclick="exportPDF('tab-clientes', 'relatorio-clientes')">
                ⬇ PDF
            </button>
            <button class="btn-export btn-csv" onclick="exportCSV('tbl-clientes', 'pontos-por-cliente')">
                ⬇ CSV
            </button>
        </div>

        <!-- KPI clientes -->
        <?php $totalPontosComCliente = array_sum(array_column($clientesData, 'total_pontos')); ?>
        <div class="kpi-grid" style="margin-bottom:1.25rem;">
            <div class="kpi-card kpi-total">
                <div class="kpi-value"><?= count($clientesData) ?></div>
                <div class="kpi-label">Clientes Ativos</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" style="color:#3498db"><?= count($agenciasData) ?></div>
                <div class="kpi-label">Agências</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" style="color:var(--color-accent-primary)"><?= $totalPontosComCliente ?></div>
                <div class="kpi-label">Pontos Alocados</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" style="color:#27ae60">
                    <?= count($clientesData) > 0 ? round($totalPontosComCliente / count($clientesData), 1) : 0 ?>
                </div>
                <div class="kpi-label">Média por Cliente</div>
            </div>
        </div>

        <!-- Top 10 clientes — barras -->
        <div class="section-title">🏆 Top 10 Clientes por Pontos</div>
        <?php if (!empty($top10Clientes)): ?>
        <?php $maxCli = max(array_column($top10Clientes, 'total_pontos')); ?>
        <div style="margin-bottom: 1.5rem;">
            <?php foreach ($top10Clientes as $i => $cl): ?>
            <div class="clientes-bar-row">
                <div class="bar-label" title="<?= htmlspecialchars($cl['cliente']) ?>">
                    <?= $i+1 ?>. <?= htmlspecialchars($cl['cliente']) ?>
                </div>
                <div style="background:#f0f0f0; border-radius:3px; overflow:hidden; height:22px;">
                    <div class="clientes-bar-fill" style="width: <?= pct($cl['total_pontos'], $maxCli) ?>%">
                        <?php if (pct($cl['total_pontos'], $maxCli) > 15): ?>
                            <span><?= $cl['total_pontos'] ?> pts</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="bar-total"><?= $cl['total_pontos'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Tabela completa clientes -->
        <div class="section-title">📋 Todos os Clientes</div>
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
                        <td style="color:var(--color-text-muted); font-size:0.78rem;">
                            <?php
                            if ($cl['inicio_mais_antigo'] && $cl['inicio_mais_antigo'] !== '0000-00-00') {
                                try { echo (new DateTime($cl['inicio_mais_antigo']))->format('m/Y'); }
                                catch(Exception $e) { echo '-'; }
                            } else { echo '-'; }
                            ?>
                        </td>
                        <td style="font-size:0.78rem;">
                            <?php
                            if ($cl['fim_mais_recente'] && $cl['fim_mais_recente'] !== '0000-00-00') {
                                try { echo (new DateTime($cl['fim_mais_recente']))->format('m/Y'); }
                                catch(Exception $e) { echo '-'; }
                            } else { echo '-'; }
                            ?>
                        </td>
                        <td>
                            <div style="display:flex; align-items:center; gap:0.4rem;">
                                <div style="flex:1; height:8px; background:#f0f0f0; border-radius:4px; overflow:hidden;">
                                    <div style="width:<?= pct($cl['total_pontos'], $totalPontosComCliente) ?>%; height:100%; background:var(--color-accent-primary); border-radius:4px;"></div>
                                </div>
                                <span style="font-size:0.75rem; font-weight:700; width:36px; text-align:right;"><?= pct($cl['total_pontos'], $totalPontosComCliente) ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Tabela por agência -->
        <?php if (!empty($agenciasData)): ?>
        <div class="section-title" style="margin-top:1.5rem">🏛️ Resumo por Agência</div>
        <div class="table-container">
            <table class="rel-table">
                <thead>
                    <tr>
                        <th>Agência</th>
                        <th>Clientes</th>
                        <th>Total de Pontos</th>
                        <th>% do total alocado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agenciasData as $ag): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($ag['agencia']) ?></strong></td>
                        <td><?= $ag['total_clientes'] ?></td>
                        <td><strong style="color:var(--color-accent-primary)"><?= $ag['total_pontos'] ?></strong></td>
                        <td>
                            <div style="display:flex; align-items:center; gap:0.4rem;">
                                <div style="flex:1; height:8px; background:#f0f0f0; border-radius:4px; overflow:hidden;">
                                    <div style="width:<?= pct($ag['total_pontos'], $totalPontosComCliente) ?>%; height:100%; background:#3498db; border-radius:4px;"></div>
                                </div>
                                <span style="font-size:0.75rem; font-weight:700; width:36px; text-align:right;"><?= pct($ag['total_pontos'], $totalPontosComCliente) ?>%</span>
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
// ===== ABAS =====
function switchTab(name, btn) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}

// Abre aba via hash
(function(){
    const hash = location.hash.replace('#','');
    if (['ocupacao','contratos','clientes'].includes(hash)) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.getElementById('tab-' + hash).classList.add('active');
        const idx = ['ocupacao','contratos','clientes'].indexOf(hash);
        document.querySelectorAll('.tab-btn')[idx].classList.add('active');
    }
})();

// ===== DONUT CHART =====
(function(){
    const data = [
        { val: <?= $totalOcupados ?>,    color: '#e34c3e' },
        { val: <?= $totalDisponiveis ?>, color: '#27ae60' },
        { val: <?= $totalReservados ?>,  color: '#f39c12' },
        { val: <?= max(0, $totalOutros) ?>,      color: '#bdc3c7' }
    ];
    const total = data.reduce((s, d) => s + d.val, 0);
    if (total === 0) return;

    const cx = 70, cy = 70, r = 50, stroke = 22;
    const circ = 2 * Math.PI * r;
    let offset = 0;
    const svg = document.getElementById('donutSvg');

    // Background circle
    const bg = document.createElementNS('http://www.w3.org/2000/svg','circle');
    bg.setAttribute('cx', cx); bg.setAttribute('cy', cy); bg.setAttribute('r', r);
    bg.setAttribute('fill','none'); bg.setAttribute('stroke','#f0f0f0'); bg.setAttribute('stroke-width', stroke);
    svg.appendChild(bg);

    data.forEach(d => {
        if (d.val === 0) return;
        const pct = d.val / total;
        const dash = pct * circ;
        const circle = document.createElementNS('http://www.w3.org/2000/svg','circle');
        circle.setAttribute('cx', cx); circle.setAttribute('cy', cy); circle.setAttribute('r', r);
        circle.setAttribute('fill','none');
        circle.setAttribute('stroke', d.color);
        circle.setAttribute('stroke-width', stroke);
        circle.setAttribute('stroke-dasharray', `${dash} ${circ}`);
        circle.setAttribute('stroke-dashoffset', -offset * circ);
        circle.setAttribute('transform', `rotate(-90 ${cx} ${cy})`);
        circle.style.transition = 'stroke-dasharray 0.6s ease';
        svg.appendChild(circle);
        offset += pct;
    });

    // Centro texto
    const t1 = document.createElementNS('http://www.w3.org/2000/svg','text');
    t1.setAttribute('x', cx); t1.setAttribute('y', cy - 4);
    t1.setAttribute('text-anchor','middle'); t1.setAttribute('font-size','18');
    t1.setAttribute('font-weight','800'); t1.setAttribute('fill','#1f2736');
    t1.textContent = total;
    svg.appendChild(t1);

    const t2 = document.createElementNS('http://www.w3.org/2000/svg','text');
    t2.setAttribute('x', cx); t2.setAttribute('y', cy + 12);
    t2.setAttribute('text-anchor','middle'); t2.setAttribute('font-size','9');
    t2.setAttribute('fill','#7f8c8d'); t2.setAttribute('font-weight','600');
    t2.textContent = 'PONTOS';
    svg.appendChild(t2);
})();

// ===== EXPORTAR CSV =====
function exportCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) { alert('Tabela não encontrada.'); return; }
    let csv = '';
    table.querySelectorAll('tr').forEach(row => {
        const cells = [...row.querySelectorAll('th, td')].map(c => {
            let txt = c.innerText.trim().replace(/\n/g, ' ');
            if (txt.includes(',') || txt.includes('"')) txt = '"' + txt.replace(/"/g, '""') + '"';
            return txt;
        });
        csv += cells.join(',') + '\n';
    });
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename + '_' + new Date().toISOString().slice(0,10) + '.csv';
    link.click();
}

// ===== EXPORTAR PDF =====
function exportPDF(tabId, filename) {
    // Abre janela de impressão com apenas o conteúdo da aba ativa
    const content = document.getElementById(tabId).innerHTML;
    const win = window.open('', '_blank');
    win.document.write(`
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <title>${filename}</title>
            <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
            <style>
                * { margin:0; padding:0; box-sizing:border-box; }
                body { font-family:'Montserrat',sans-serif; font-size:11px; color:#1f2736; padding:20px; }
                h2, h3 { color:#1f2736; margin-bottom:8px; }
                table { width:100%; border-collapse:collapse; margin-bottom:16px; }
                th { background:#e34c3e; color:white; padding:5px 8px; font-size:10px; text-align:left; }
                td { padding:5px 8px; border-bottom:1px solid #e9ecef; font-size:10px; }
                .kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin-bottom:16px; }
                .kpi-card { border:1px solid #e9ecef; border-radius:6px; padding:8px; text-align:center; }
                .kpi-value { font-size:18px; font-weight:800; }
                .kpi-label { font-size:9px; font-weight:600; color:#7f8c8d; text-transform:uppercase; }
                .btn-export, .btn-pdf, .btn-csv, .export-bar, .periodo-pills { display:none !important; }
                .section-title { font-weight:800; font-size:11px; border-bottom:2px solid #e34c3e; padding-bottom:3px; margin:12px 0 6px; }
                .tag-urgente { color:#c0392b; font-weight:700; }
                .tag-atencao { color:#856404; font-weight:700; }
                .tag-ok      { color:#065f46; font-weight:700; }
                .tag-vencido { color:#495057; font-weight:700; }
                .bar-row, .clientes-bar-row, .chart-bars, .timeline-bars { display:none; }
                .donut-wrapper { display:none; }
                .panel { border:1px solid #e9ecef; padding:8px; border-radius:6px; margin-bottom:12px; }
                .panel-title { font-weight:800; font-size:10px; color:#7f8c8d; text-transform:uppercase; margin-bottom:4px; }
            </style>
        </head>
        <body>
            <div style="margin-bottom:16px; border-bottom:2px solid #e34c3e; padding-bottom:8px;">
                <strong style="font-size:16px; color:#e34c3e;">impakto</strong>
                <span style="font-size:11px; color:#7f8c8d; margin-left:8px;">Relatório gerado em ${new Date().toLocaleDateString('pt-BR')} às ${new Date().toLocaleTimeString('pt-BR', {hour:'2-digit',minute:'2-digit'})}</span>
            </div>
            ${content}
        </body>
        </html>
    `);
    win.document.close();
    setTimeout(() => { win.print(); }, 600);
}
</script>

</body>
</html>