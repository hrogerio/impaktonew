<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}
$paginaAtual  = 'dashboard';
$loginSucesso = isset($_GET['logado']) && $_GET['logado'] == '1';

try {
    require_once __DIR__ . '/../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("Erro na conexão com o banco de dados.");
}

// ── Auto-processar contratos vencidos (silencioso) ────────────
$autoProcessados = 0;
try {
    $stVenc = $pdo->query("
        SELECT c.id AS camp_id, c.ponto_id
        FROM campanhas c
        WHERE c.ativo = 1 AND c.situacao = 'Ocupado'
          AND c.fim IS NOT NULL AND c.fim != '0000-00-00' AND DATE(c.fim) < CURDATE()
    ");
    $paraProcessar = $stVenc ? $stVenc->fetchAll(PDO::FETCH_ASSOC) : [];
    if (!empty($paraProcessar)) {
        try {
            $pdo->beginTransaction();
            foreach ($paraProcessar as $v) {
                $pdo->prepare("UPDATE campanhas SET ativo=0, encerrado_em=NOW() WHERE id=?")
                    ->execute([$v['camp_id']]);
                $pdo->prepare("UPDATE pontos SET situacao='Disponivel' WHERE id=?")
                    ->execute([$v['ponto_id']]);
            }
            $pdo->commit();
            $autoProcessados = count($paraProcessar);
        } catch (Exception $e) {
            try { $pdo->rollBack(); } catch(Exception $ex) {}
            error_log("dashboard auto-processar vencidos (tx): " . $e->getMessage());
        }
    }
} catch (Exception $e) {
    error_log("dashboard auto-processar vencidos (query): " . $e->getMessage());
}

// ── Totais por situação ───────────────────────────────────────
$stmtSit = $pdo->query("
    SELECT situacao, COUNT(*) as n
    FROM pontos WHERE (ativo=1 OR ativo IS NULL)
    GROUP BY situacao
");
$porSituacao = [];
$totalPontos = 0;
foreach ($stmtSit->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $porSituacao[$r['situacao']] = (int)$r['n'];
    $totalPontos += (int)$r['n'];
}
$disponivel = ($porSituacao['Disponivel'] ?? 0) + ($porSituacao['Disponível'] ?? 0);
$ocupado    = $porSituacao['Ocupado']   ?? 0;
$reservado  = $porSituacao['Reservado'] ?? 0;
$vencido    = $porSituacao['Vencido']   ?? 0;
$permuta    = $porSituacao['Permuta']   ?? 0;
$bisemana   = $porSituacao['Bisemana']  ?? 0;
$ocupacaoTotal = $totalPontos > 0 ? round(($ocupado + $reservado + $bisemana + $permuta) / $totalPontos * 100) : 0;

// ── Ocupação por região ───────────────────────────────────────
$stmtReg = $pdo->query("
    SELECT regiao,
           COUNT(*) as total,
           SUM(CASE WHEN situacao IN ('Ocupado','Reservado','Bisemana','Permuta') THEN 1 ELSE 0 END) as ocupados,
           SUM(CASE WHEN situacao IN ('Disponivel','Disponível') THEN 1 ELSE 0 END) as disponiveis
    FROM pontos
    WHERE (ativo=1 OR ativo IS NULL) AND regiao IS NOT NULL AND regiao != ''
    GROUP BY regiao
    ORDER BY total DESC
");
$regioes = $stmtReg->fetchAll(PDO::FETCH_ASSOC);

// ── Vencimentos próximos 30 dias (campanhas ativas) ──────────
$stmtVenc = $pdo->query("
    SELECT p.id, p.numero, p.logradouro, p.cidade,
           COALESCE(c.cliente, p.cliente) AS cliente,
           COALESCE(DATE(NULLIF(c.fim, '0000-00-00')), DATE(NULLIF(p.fim_contrato, '0000-00-00'))) AS fim_contrato,
           DATEDIFF(COALESCE(DATE(NULLIF(c.fim, '0000-00-00')), DATE(NULLIF(p.fim_contrato, '0000-00-00'))), CURDATE()) AS dias_restantes
    FROM pontos p
    LEFT JOIN campanhas c ON c.ponto_id = p.id AND c.ativo = 1 AND c.situacao = 'Ocupado'
    WHERE (p.ativo=1 OR p.ativo IS NULL)
      AND p.situacao NOT IN ('Disponivel','Disponível')
      AND COALESCE(DATE(NULLIF(c.fim, '0000-00-00')), DATE(NULLIF(p.fim_contrato, '0000-00-00'))) IS NOT NULL
      AND COALESCE(DATE(NULLIF(c.fim, '0000-00-00')), DATE(NULLIF(p.fim_contrato, '0000-00-00'))) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY fim_contrato ASC
    LIMIT 15
");
$vencimentos  = $stmtVenc->fetchAll(PDO::FETCH_ASSOC);
$venc7dias    = array_filter($vencimentos, function($v) { return (int)$v['dias_restantes'] <= 7; });
$venc7count   = count($venc7dias);

// ── Reservas pendentes (pre_selecoes aguardando confirmação) ──
try {
    $stmtPend = $pdo->query("
        SELECT id, cliente, agencia, total_pontos, criado_em
        FROM pre_selecoes
        WHERE status = 'enviada'
        ORDER BY criado_em ASC
    ");
    $reservasPendentes = $stmtPend->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $reservasPendentes = [];
}

// ── Reservas recentes ─────────────────────────────────────────
try {
    $stmtRes = $pdo->query("
        SELECT id, cliente, agencia, status, total_pontos, criado_em
        FROM pre_selecoes
        ORDER BY criado_em DESC LIMIT 5
    ");
    $reservasRecentes = $stmtRes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $reservasRecentes = [];
}

// ── Pontos por região (para o gráfico de barras) ──────────────
$CORES_SIT = [
    'Disponivel' => '#1a9059', 'Disponível' => '#1a9059',
    'Ocupado'    => '#dc3545',
    'Reservado'  => '#fd7e14',
    'Vencido'    => '#6c757d',
    'Permuta'    => '#51086e',
    'Bisemana'   => '#0284c7',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/public/assets/img/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
    <title>Dashboard — Impakto Mídia</title>
    <style>
        .db-page { max-width:1200px; margin:0 auto; padding:1.5rem 1.5rem 3rem; }

        /* ── Alerta login ── */
        .db-alert {
            background:#dcfce7; border:1px solid #86efac; border-radius:8px;
            padding:0.75rem 1rem; margin-bottom:1.25rem;
            font-size:0.83rem; font-weight:600; color:#166534;
        }

        /* ── Título ── */
        .db-header { margin-bottom:1.5rem; }
        .db-header h1 { font-size:1.3rem; font-weight:800; color:var(--color-text-dark); margin:0; }
        .db-header p  { font-size:0.82rem; color:var(--color-text-muted); margin-top:0.25rem; }

        /* ── Grid de KPIs ── */
        .db-kpis { display:grid; grid-template-columns:repeat(6,1fr); gap:0.85rem; margin-bottom:1.5rem; }
        @media(max-width:1100px) { .db-kpis { grid-template-columns:repeat(3,1fr); } }
        @media(max-width:600px)  { .db-kpis { grid-template-columns:repeat(2,1fr); } }

        .kpi {
            background:white; border:1px solid var(--color-border); border-radius:10px;
            padding:1rem; display:flex; flex-direction:column; gap:0.3rem;
            border-top:3px solid transparent;
        }
        .kpi-label { font-size:0.65rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:0.5px; }
        .kpi-valor { font-size:1.9rem; font-weight:800; line-height:1; }
        .kpi-sub   { font-size:0.7rem; color:var(--color-text-muted); }

        .kpi-total     { border-top-color:#1a1a1a; }  .kpi-total .kpi-valor     { color:#1a1a1a; }
        .kpi-disponivel{ border-top-color:#1a9059; }  .kpi-disponivel .kpi-valor{ color:#1a9059; }
        .kpi-ocupado   { border-top-color:#dc3545; }  .kpi-ocupado .kpi-valor   { color:#dc3545; }
        .kpi-reservado { border-top-color:#fd7e14; }  .kpi-reservado .kpi-valor { color:#fd7e14; }
        .kpi-vencido   { border-top-color:#6c757d; }  .kpi-vencido .kpi-valor   { color:#6c757d; }
        .kpi-ocup-pct  { border-top-color:#c0392b; }  .kpi-ocup-pct .kpi-valor  { color:#c0392b; }

        /* ── Grid 2 colunas ── */
        .db-grid { display:grid; grid-template-columns:1fr 380px; gap:1.25rem; margin-bottom:1.25rem; }
        @media(max-width:900px) { .db-grid { grid-template-columns:1fr; } }

        /* ── Card genérico ── */
        .db-card { background:white; border:1px solid var(--color-border); border-radius:10px; overflow:hidden; }
        .db-card-header {
            padding:0.65rem 1rem; background:var(--color-bg-primary);
            border-bottom:1px solid var(--color-border);
            display:flex; align-items:center; justify-content:space-between;
        }
        .db-card-title { font-size:0.78rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:0.5px; }
        .db-card-link  { font-size:0.75rem; font-weight:700; color:var(--color-accent-primary); text-decoration:none; }
        .db-card-link:hover { text-decoration:underline; }
        .db-card-body  { padding:1rem; }

        /* ── Regiões ── */
        .reg-item { margin-bottom:0.85rem; }
        .reg-item:last-child { margin-bottom:0; }
        .reg-header { display:flex; justify-content:space-between; align-items:baseline; margin-bottom:0.3rem; }
        .reg-nome   { font-size:0.8rem; font-weight:700; color:var(--color-text-dark); }
        .reg-nums   { font-size:0.72rem; color:var(--color-text-muted); }
        .reg-bar    { height:8px; background:#f0f0f0; border-radius:4px; overflow:hidden; display:flex; }
        .reg-bar-ocup { background:#dc3545; border-radius:4px 0 0 4px; transition:width 0.5s; }
        .reg-bar-disp { background:#1a9059; transition:width 0.5s; }

        /* ── Vencimentos ── */
        .venc-table { width:100%; border-collapse:collapse; font-size:0.8rem; }
        .venc-table th { padding:0.4rem 0.75rem; font-size:0.65rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; border-bottom:1.5px solid var(--color-border); text-align:left; background:var(--color-bg-primary); }
        .venc-table td { padding:0.5rem 0.75rem; border-bottom:1px solid var(--color-border); vertical-align:middle; }
        .venc-table tbody tr:last-child td { border-bottom:none; }
        .venc-table tbody tr:hover { background:#fafafa; }
        .venc-num { font-weight:800; color:var(--color-accent-primary); }
        .venc-cli { font-size:0.72rem; color:var(--color-text-muted); }
        .dias-badge {
            display:inline-block; padding:2px 7px; border-radius:10px;
            font-size:0.68rem; font-weight:800; white-space:nowrap;
        }
        .dias-urgente { background:#fee2e2; color:#991b1b; }
        .dias-atencao { background:#ffedd5; color:#9a3412; }
        .dias-ok      { background:#dcfce7; color:#166534; }

        /* ── Reservas recentes ── */
        .res-item {
            display:flex; align-items:center; gap:0.75rem;
            padding:0.6rem 0; border-bottom:1px solid var(--color-border);
        }
        .res-item:last-child { border-bottom:none; }
        .res-info { flex:1; min-width:0; }
        .res-cli  { font-size:0.82rem; font-weight:700; color:var(--color-text-dark); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .res-sub  { font-size:0.7rem; color:var(--color-text-muted); margin-top:1px; }
        .res-pts  { font-size:0.72rem; font-weight:700; color:var(--color-text-muted); white-space:nowrap; }

        .status-badge { display:inline-block; padding:2px 7px; border-radius:10px; font-size:0.62rem; font-weight:800; text-transform:uppercase; }
        .status-rascunho { background:#f1f5f9; color:#475569; }
        .status-enviada  { background:#dbeafe; color:#1e40af; }
        .status-aprovada { background:#dcfce7; color:#166534; }
        .status-recusada { background:#fee2e2; color:#991b1b; }

        /* ── Atalhos ── */
        .db-atalhos { display:grid; grid-template-columns:repeat(4,1fr); gap:0.85rem; margin-bottom:1.25rem; }
        @media(max-width:700px) { .db-atalhos { grid-template-columns:repeat(2,1fr); } }
        .atalho {
            background:white; border:1px solid var(--color-border); border-radius:10px;
            padding:1.1rem; text-align:center; text-decoration:none;
            transition:all 0.15s; display:flex; flex-direction:column; align-items:center; gap:0.4rem;
        }
        .atalho:hover { border-color:var(--color-accent-primary); transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,0.08); }
        .atalho-icon { font-size:1.6rem; }
        .atalho-label { font-size:0.78rem; font-weight:700; color:var(--color-text-dark); }
        .atalho-sub   { font-size:0.68rem; color:var(--color-text-muted); }

        .db-empty { padding:1.5rem; text-align:center; color:var(--color-text-muted); font-size:0.82rem; }

        /* ── Banners de alerta urgente ── */
        .db-alertas { display:flex; flex-direction:column; gap:0.6rem; margin-bottom:1.25rem; }
        .db-alerta-banner {
            border-radius:9px; padding:0.75rem 1rem;
            display:flex; align-items:center; gap:0.75rem;
            font-size:0.82rem; font-weight:600;
        }
        .db-alerta-banner a { font-weight:800; text-decoration:underline; }
        .alerta-urgente { background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; }
        .alerta-urgente a { color:#7f1d1d; }
        .alerta-pendente { background:#fffbeb; border:1px solid #fcd34d; color:#92400e; }
        .alerta-pendente a { color:#78350f; }
        .alerta-icon { font-size:1.15rem; flex-shrink:0; }

        /* ── KPI: reservas pendentes ── */
        .kpi-pendente { border-top-color:#d97706; }  .kpi-pendente .kpi-valor { color:#d97706; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../app/Views/layouts/_nav.php'; ?>

<div class="db-page">

    <?php if ($loginSucesso): ?>
    <div class="db-alert">✅ Login realizado com sucesso! Bem-vindo ao sistema de gestão.</div>
    <?php endif; ?>

    <?php if ($autoProcessados > 0): ?>
    <div class="db-alert" style="background:#f0fdf4;border-color:#86efac;color:#166534">
        🔓 <strong><?= $autoProcessados ?> contrato<?= $autoProcessados > 1 ? 's' : '' ?> vencido<?= $autoProcessados > 1 ? 's' : '' ?></strong>
        <?= $autoProcessados > 1 ? 'foram liberados' : 'foi liberado' ?> automaticamente — pontos voltaram a ficar Disponíveis.
    </div>
    <?php endif; ?>

    <div class="db-header">
        <h1>Dashboard</h1>
        <p>Visão geral do inventário — atualizado agora</p>
    </div>

    <!-- ── Alertas urgentes ── -->
    <?php if ($venc7count > 0 || !empty($reservasPendentes)): ?>
    <div class="db-alertas">
        <?php if ($venc7count > 0): ?>
        <div class="db-alerta-banner alerta-urgente">
            <span class="alerta-icon">🔴</span>
            <span>
                <strong><?= $venc7count ?> contrato<?= $venc7count > 1 ? 's' : '' ?></strong>
                <?= $venc7count > 1 ? 'vencem' : 'vence' ?> nos próximos <strong>7 dias</strong>!
                <a href="#vencimentos">Ver detalhes ↓</a>
            </span>
        </div>
        <?php endif; ?>
        <?php if (!empty($reservasPendentes)): ?>
        <?php $nPend = count($reservasPendentes); ?>
        <div class="db-alerta-banner alerta-pendente">
            <span class="alerta-icon">⏳</span>
            <span>
                <strong><?= $nPend ?> pré-seleção<?= $nPend > 1 ? 'ões' : '' ?></strong>
                aguardando confirmação do cliente.
                <a href="/gestor/reservas">Ver reservas →</a>
            </span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── KPIs ── -->
    <div class="db-kpis">
        <div class="kpi kpi-total">
            <div class="kpi-label">Total de pontos</div>
            <div class="kpi-valor"><?= $totalPontos ?></div>
            <div class="kpi-sub">ativos no sistema</div>
        </div>
        <div class="kpi kpi-disponivel">
            <div class="kpi-label">Disponíveis</div>
            <div class="kpi-valor"><?= $disponivel ?></div>
            <div class="kpi-sub"><?= $totalPontos > 0 ? round($disponivel/$totalPontos*100) : 0 ?>% do total</div>
        </div>
        <div class="kpi kpi-ocupado">
            <div class="kpi-label">Ocupados</div>
            <div class="kpi-valor"><?= $ocupado ?></div>
            <div class="kpi-sub"><?= $totalPontos > 0 ? round($ocupado/$totalPontos*100) : 0 ?>% do total</div>
        </div>
        <div class="kpi kpi-reservado">
            <div class="kpi-label">Reservados</div>
            <div class="kpi-valor"><?= $reservado ?></div>
            <div class="kpi-sub"><?= $totalPontos > 0 ? round($reservado/$totalPontos*100) : 0 ?>% do total</div>
        </div>
        <div class="kpi kpi-vencido" style="<?= $venc7count > 0 ? 'border-top-color:#dc3545' : '' ?>">
            <div class="kpi-label">Vencendo em 30d</div>
            <div class="kpi-valor" style="<?= $venc7count > 0 ? 'color:#dc3545' : '' ?>"><?= count($vencimentos) ?></div>
            <div class="kpi-sub">
                <?php if ($venc7count > 0): ?>
                    <span style="color:#dc3545;font-weight:700"><?= $venc7count ?> urgente<?= $venc7count > 1 ? 's' : '' ?> (≤7d)</span>
                <?php else: ?>
                    contratos a vencer
                <?php endif; ?>
            </div>
        </div>
        <div class="kpi kpi-pendente">
            <div class="kpi-label">Aguard. confirmação</div>
            <div class="kpi-valor"><?= count($reservasPendentes) ?></div>
            <div class="kpi-sub"><?= count($reservasPendentes) > 0 ? 'pré-seleções enviadas' : 'nenhuma pendente' ?></div>
        </div>
    </div>

    <!-- ── Atalhos rápidos ── -->
    <div class="db-atalhos">
        <a href="/gestor/pontos" class="atalho">
            <div class="atalho-icon">📍</div>
            <div class="atalho-label">Pontos</div>
            <div class="atalho-sub">Ver todos</div>
        </a>
        <a href="/gestor/pre-selecao" class="atalho">
            <div class="atalho-icon">🛒</div>
            <div class="atalho-label">Pré-Seleção</div>
            <div class="atalho-sub">Nova proposta</div>
        </a>
        <a href="/gestor/reservas" class="atalho">
            <div class="atalho-icon">📋</div>
            <div class="atalho-label">Reservas</div>
            <div class="atalho-sub"><?= count($reservasRecentes) > 0 ? 'Ver histórico' : 'Nenhuma ainda' ?></div>
        </a>
        <a href="/gestor/mapa" class="atalho">
            <div class="atalho-icon">🗺️</div>
            <div class="atalho-label">Mapa</div>
            <div class="atalho-sub">Ver no mapa</div>
        </a>
    </div>

    <!-- ── Regiões + Reservas ── -->
    <div class="db-grid">

        <!-- Ocupação por região -->
        <div class="db-card">
            <div class="db-card-header">
                <span class="db-card-title">📊 Ocupação por Região</span>
                <a href="/gestor/relatorios" class="db-card-link">Relatórios →</a>
            </div>
            <div class="db-card-body">
                <?php if (empty($regioes)): ?>
                <div class="db-empty">Nenhuma região cadastrada.</div>
                <?php else: foreach ($regioes as $reg):
                    $pctOcup = $reg['total'] > 0 ? round($reg['ocupados'] / $reg['total'] * 100) : 0;
                    $pctDisp = $reg['total'] > 0 ? round($reg['disponiveis'] / $reg['total'] * 100) : 0;
                ?>
                <div class="reg-item">
                    <div class="reg-header">
                        <span class="reg-nome"><?= htmlspecialchars($reg['regiao']) ?></span>
                        <span class="reg-nums">
                            <span style="color:#dc3545;font-weight:700"><?= $reg['ocupados'] ?></span>
                            /<?= $reg['total'] ?> pontos
                            · <strong><?= $pctOcup ?>%</strong>
                        </span>
                    </div>
                    <div class="reg-bar">
                        <div class="reg-bar-ocup" style="width:<?= $pctOcup ?>%"></div>
                        <div class="reg-bar-disp" style="width:<?= $pctDisp ?>%"></div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Reservas / Pendentes -->
        <div class="db-card">
            <div class="db-card-header">
                <span class="db-card-title">
                    📋 Pré-Seleções
                    <?php if (!empty($reservasPendentes)): ?>
                    <span style="margin-left:0.4rem;background:#fef3c7;color:#92400e;border-radius:10px;padding:1px 7px;font-size:0.62rem;font-weight:800">
                        <?= count($reservasPendentes) ?> pendente<?= count($reservasPendentes) > 1 ? 's' : '' ?>
                    </span>
                    <?php endif; ?>
                </span>
                <a href="/gestor/reservas" class="db-card-link">Ver todas →</a>
            </div>
            <div class="db-card-body">
                <?php if (!empty($reservasPendentes)): ?>
                <!-- pendentes primeiro, destacadas -->
                <div style="font-size:0.63rem;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.4rem;padding-bottom:0.3rem;border-bottom:1px dashed #fcd34d;">⏳ Aguardando confirmação</div>
                <?php foreach ($reservasPendentes as $res): ?>
                <div class="res-item" style="background:#fffbeb;margin:0 -1rem;padding:0.55rem 1rem;border-bottom:1px solid #fef3c7;">
                    <div class="res-info">
                        <div class="res-cli"><?= htmlspecialchars($res['cliente']) ?></div>
                        <div class="res-sub">
                            Enviada <?= date('d/m/Y', strtotime($res['criado_em'])) ?>
                            <?php
                                $diasEsperando = (int)floor((time() - strtotime($res['criado_em'])) / 86400);
                            ?>
                            <?php if ($diasEsperando >= 3): ?>
                            · <span style="color:#dc3545;font-weight:700"><?= $diasEsperando ?>d aguardando</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="res-pts"><?= $res['total_pontos'] ?> pt<?= $res['total_pontos'] != 1 ? 's' : '' ?></div>
                    <a href="/gestor/reservas/ver?id=<?= $res['id'] ?>"
                       style="font-size:0.75rem;font-weight:700;color:var(--color-accent-primary);text-decoration:none">Ver →</a>
                </div>
                <?php endforeach; ?>
                <?php if (!empty($reservasRecentes)): ?>
                <div style="font-size:0.63rem;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.5px;margin:0.7rem 0 0.4rem;padding-bottom:0.3rem;border-bottom:1px solid var(--color-border);">Histórico recente</div>
                <?php endif; ?>
                <?php endif; ?>

                <?php if (empty($reservasRecentes) && empty($reservasPendentes)): ?>
                <div class="db-empty">Nenhuma reserva gerada ainda.</div>
                <?php else: foreach ($reservasRecentes as $res):
                    $status = $res['status'] ?? 'rascunho';
                    if ($status === 'enviada') continue; // já listadas acima
                    $statusLabel = ['rascunho'=>'Rascunho','aprovada'=>'Aprovada','recusada'=>'Recusada'][$status] ?? $status;
                ?>
                <div class="res-item">
                    <div class="res-info">
                        <div class="res-cli"><?= htmlspecialchars($res['cliente']) ?></div>
                        <div class="res-sub">
                            <?= date('d/m/Y', strtotime($res['criado_em'])) ?>
                            &nbsp;·&nbsp;
                            <span class="status-badge status-<?= $status ?>"><?= $statusLabel ?></span>
                        </div>
                    </div>
                    <div class="res-pts"><?= $res['total_pontos'] ?> pt<?= $res['total_pontos'] != 1 ? 's' : '' ?></div>
                    <a href="/gestor/reservas/ver?id=<?= $res['id'] ?>"
                       style="font-size:0.75rem;font-weight:700;color:var(--color-accent-primary);text-decoration:none">Ver →</a>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

    </div>

    <!-- ── Vencimentos próximos ── -->
    <?php if (!empty($vencimentos)): ?>
    <div class="db-card" id="vencimentos">
        <div class="db-card-header">
            <span class="db-card-title">
                <?= $venc7count > 0 ? '🔴' : '⚠️' ?>
                Contratos vencendo nos próximos 30 dias
                <?php if ($venc7count > 0): ?>
                    <span style="margin-left:0.5rem;background:#fee2e2;color:#991b1b;border-radius:10px;padding:1px 8px;font-size:0.68rem;font-weight:800">
                        <?= $venc7count ?> URGENTE<?= $venc7count > 1 ? 'S' : '' ?>
                    </span>
                <?php endif; ?>
            </span>
            <a href="/gestor/pontos?situacao=Ocupado" class="db-card-link">Ver pontos →</a>
        </div>
        <table class="venc-table">
            <thead>
                <tr>
                    <th style="width:50px">Nº</th>
                    <th>Logradouro</th>
                    <th style="width:140px">Cidade</th>
                    <th style="width:150px">Cliente</th>
                    <th style="width:110px">Vencimento</th>
                    <th style="width:90px;text-align:center">Restam</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($vencimentos as $v):
                $dias = (int)$v['dias_restantes'];
                $cls  = $dias <= 7 ? 'dias-urgente' : ($dias <= 15 ? 'dias-atencao' : 'dias-ok');
            ?>
            <tr <?= $dias <= 7 ? 'style="background:#fff8f8"' : '' ?>>
                <td><a href="/gestor/pontos/detalhes?id=<?= $v['id'] ?>"
                       class="venc-num" style="text-decoration:none"><?= htmlspecialchars($v['numero']) ?></a></td>
                <td><?= htmlspecialchars($v['logradouro']) ?></td>
                <td><?= htmlspecialchars($v['cidade'] ?? '—') ?></td>
                <td><?= htmlspecialchars($v['cliente'] ?? '—') ?></td>
                <td><?= date('d/m/Y', strtotime($v['fim_contrato'])) ?></td>
                <td style="text-align:center">
                    <span class="dias-badge <?= $cls ?>">
                        <?= $dias === 0 ? 'Hoje' : $dias . 'd' ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Remove parâmetro logado da URL após 3s
    if (window.location.search.includes('logado=1')) {
        setTimeout(function() {
            var url = new URL(window.location);
            url.searchParams.delete('logado');
            window.history.replaceState({}, '', url);
        }, 3000);
    }

    // Scroll suave para âncoras internas
    document.querySelectorAll('a[href^="#"]').forEach(function(a) {
        a.addEventListener('click', function(e) {
            var target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
});
</script>

</body>
</html>
