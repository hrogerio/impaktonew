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

// ── Marcar contratos vencidos como 'Vencido' (silencioso) ──────
// Não encerra automaticamente: fica aguardando decisão manual de
// renovar ou encerrar (o ponto continua Ocupado nesse meio-tempo).
$autoProcessados = 0;
try {
    $stVenc = $pdo->query("
        SELECT c.id AS camp_id, c.ponto_id
        FROM campanhas c
        WHERE c.ativo = 1 AND c.situacao = 'Ocupado'
          AND c.fim IS NOT NULL AND CAST(c.fim AS CHAR) != '0000-00-00' AND DATE(c.fim) < CURDATE()
    ");
    $paraProcessar = $stVenc ? $stVenc->fetchAll(PDO::FETCH_ASSOC) : [];
    if (!empty($paraProcessar)) {
        try {
            $pdo->beginTransaction();
            foreach ($paraProcessar as $v) {
                $pdo->prepare("UPDATE campanhas SET situacao='Vencido' WHERE id=?")
                    ->execute([$v['camp_id']]);
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

// ── Vencidos + vencendo no mês vigente (campanhas ativas) ──
$stmtVenc = $pdo->query("
    SELECT p.id, p.numero, p.logradouro, p.cidade, p.tipo, p.situacao,
           c.cliente AS cliente, c.campanha AS campanha, c.agencia AS agencia, c.inicio AS inicio_contrato,
           COALESCE(
               CASE WHEN CAST(c.fim AS CHAR) NOT IN ('0000-00-00','') THEN c.fim ELSE NULL END,
               CASE WHEN CAST(p.fim_contrato AS CHAR) NOT IN ('0000-00-00','') THEN p.fim_contrato ELSE NULL END
           ) AS fim_contrato,
           DATEDIFF(COALESCE(
               CASE WHEN CAST(c.fim AS CHAR) NOT IN ('0000-00-00','') THEN c.fim ELSE NULL END,
               CASE WHEN CAST(p.fim_contrato AS CHAR) NOT IN ('0000-00-00','') THEN p.fim_contrato ELSE NULL END
           ), CURDATE()) AS dias_restantes,
           EXISTS(SELECT 1 FROM checking_fotos cf WHERE cf.ponto_id = p.id AND cf.situacao = 'Ocupado') AS tem_checking
    FROM pontos p
    LEFT JOIN campanhas c ON c.ponto_id = p.id AND c.ativo = 1 AND c.situacao IN ('Ocupado','Vencido')
    WHERE (p.ativo=1 OR p.ativo IS NULL)
      AND p.situacao IN ('Ocupado','Vencido')
      AND (
               COALESCE(
                   CASE WHEN CAST(c.fim AS CHAR) NOT IN ('0000-00-00','') THEN c.fim ELSE NULL END,
                   CASE WHEN CAST(p.fim_contrato AS CHAR) NOT IN ('0000-00-00','') THEN p.fim_contrato ELSE NULL END
               ) < CURDATE()
            OR
               (
                   YEAR(COALESCE(
                       CASE WHEN CAST(c.fim AS CHAR) NOT IN ('0000-00-00','') THEN c.fim ELSE NULL END,
                       CASE WHEN CAST(p.fim_contrato AS CHAR) NOT IN ('0000-00-00','') THEN p.fim_contrato ELSE NULL END
                   )) = YEAR(CURDATE())
               AND
                   MONTH(COALESCE(
                       CASE WHEN CAST(c.fim AS CHAR) NOT IN ('0000-00-00','') THEN c.fim ELSE NULL END,
                       CASE WHEN CAST(p.fim_contrato AS CHAR) NOT IN ('0000-00-00','') THEN p.fim_contrato ELSE NULL END
                   )) = MONTH(CURDATE())
               )
          )
    ORDER BY fim_contrato ASC
");
$vencimentosPontos = $stmtVenc->fetchAll(PDO::FETCH_ASSOC);

// Agrupa por contrato (cliente+campanha+agência+período), somando os pontos vinculados —
// um mesmo contrato pode cobrir vários pontos e não deve virar um card por ponto.
$vencGrupos = [];
foreach ($vencimentosPontos as $v) {
    $chave = mb_strtolower(trim(implode('|', [
        $v['cliente'] ?? '', $v['campanha'] ?? '', $v['agencia'] ?? '',
        $v['inicio_contrato'] ?? '', $v['fim_contrato'] ?? '',
    ])));
    if (!isset($vencGrupos[$chave])) {
        $vencGrupos[$chave] = $v;
        $vencGrupos[$chave]['qtd_pontos'] = 0;
        $vencGrupos[$chave]['tem_checking'] = false;
    }
    $vencGrupos[$chave]['qtd_pontos']++;
    if ($v['tem_checking']) $vencGrupos[$chave]['tem_checking'] = true;
}
$vencimentos  = array_slice(array_values($vencGrupos), 0, 15);
$venc7dias    = array_filter($vencimentos, function($v) { $d = (int)$v['dias_restantes']; return $d >= 0 && $d <= 7; });
$venc7count   = count($venc7dias);
$venc30count  = count($vencimentos);

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
$mesesAbrev    = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
$mesesCompletos = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
$agora = new DateTime();
$dataHoraTopo = $agora->format('d') . ' ' . $mesesAbrev[(int)$agora->format('n') - 1] . ', ' . $agora->format('H') . 'h';

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
        .db-page {
            --ok:#1a9059;      --ok-soft:#e3f5ec;
            --warn:#b06a00;    --warn-soft:#fdf1dc;
            --crit:#c0392b;    --crit-soft:#fbe4e1;
            --accent-soft:#fdeae7; --accent-ink:#b3271b;
            --db-shadow: 0 1px 2px rgba(26,29,35,0.06), 0 8px 24px -12px rgba(26,29,35,0.14);
            --db-radius: 14px;
            max-width:1280px; margin:0 auto; padding:1.75rem 1.75rem 3.5rem;
            display:flex; flex-direction:column; gap:1.5rem;
        }
        .db-page .num { font-variant-numeric: tabular-nums; }

        /* ── Alerta login ── */
        .db-alert {
            background:#dcfce7; border:1px solid #86efac; border-radius:8px;
            padding:0.75rem 1rem;
            font-size:0.83rem; font-weight:600; color:#166534;
        }

        /* ── Cabeçalho ── */
        .db-header { display:flex; align-items:baseline; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
        .db-header-title { display:flex; align-items:baseline; gap:0.75rem; flex-wrap:wrap; }
        .db-header h1 { font-size:1.5rem; font-weight:800; letter-spacing:-0.02em; color:var(--color-text-dark); margin:0; }
        .db-header-time { font-size:0.85rem; color:var(--color-text-muted); font-weight:600; }
        .db-live-pill {
            display:inline-flex; align-items:center; gap:0.4rem;
            font-size:0.72rem; font-weight:700; color:var(--ok);
            background:var(--ok-soft); padding:0.35rem 0.7rem; border-radius:20px;
        }
        .db-live-dot { width:6px; height:6px; border-radius:50%; background:var(--ok); box-shadow:0 0 0 3px var(--ok-soft); }

        /* ── Grid de KPIs ── */
        .db-kpis { display:grid; grid-template-columns:repeat(6,1fr); gap:0.85rem; }
        @media(max-width:1100px) { .db-kpis { grid-template-columns:repeat(3,1fr); } }
        @media(max-width:600px)  { .db-kpis { grid-template-columns:repeat(2,1fr); } }

        .kpi {
            background:white; border:1px solid var(--color-border); border-radius:var(--db-radius);
            box-shadow:var(--db-shadow);
            padding:1rem 1.1rem; display:flex; flex-direction:column; gap:0.5rem;
        }
        .kpi-top { display:flex; align-items:center; justify-content:space-between; }
        .kpi-label { font-size:0.66rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:0.07em; }
        .kpi-icon { font-size:0.9rem; opacity:0.85; }
        .kpi-valor { font-size:2rem; font-weight:800; letter-spacing:-0.03em; line-height:1; }
        .kpi-sub   { font-size:0.74rem; color:var(--color-text-muted); font-weight:600; }
        .kpi-sub b { font-weight:800; }
        .kpi-bar   { height:5px; border-radius:3px; background:var(--color-bg-primary); overflow:hidden; margin-top:0.1rem; }
        .kpi-bar > span { display:block; height:100%; border-radius:3px; }

        .kpi-total      .kpi-valor { color:#1a1a1a; }
        .kpi-disponivel .kpi-valor { color:var(--ok); }
        .kpi-ocupado    .kpi-valor { color:var(--crit); }
        .kpi-reservado  .kpi-valor { color:var(--warn); }
        .kpi-pendente   .kpi-valor { color:var(--warn); }

        .kpi.kpi-hero {
            background:var(--color-text-dark); border-color:var(--color-text-dark); color:white;
        }
        .kpi.kpi-hero .kpi-label { color:rgba(255,255,255,0.62); }
        .kpi.kpi-hero .kpi-valor { color:#fff; }
        .kpi.kpi-hero .kpi-sub   { color:rgba(255,255,255,0.72); }
        .kpi.kpi-hero.kpi-hero-crit { background:var(--crit); border-color:var(--crit); }
        .kpi.kpi-hero.kpi-hero-crit .kpi-label { color:rgba(255,255,255,0.75); }
        .kpi.kpi-hero.kpi-hero-crit .kpi-sub   { color:rgba(255,255,255,0.85); }

        /* ── Atalhos ── */
        .db-atalhos { display:grid; grid-template-columns:repeat(4,1fr); gap:0.85rem; }
        @media(max-width:700px) { .db-atalhos { grid-template-columns:repeat(2,1fr); } }
        .atalho {
            display:flex; align-items:center; gap:0.85rem;
            background:white; border:1px solid var(--color-border); border-radius:var(--db-radius);
            padding:0.95rem 1.05rem; text-decoration:none;
            transition:border-color 0.15s, transform 0.15s, box-shadow 0.15s;
        }
        .atalho:hover { border-color:var(--color-accent-primary); transform:translateY(-2px); box-shadow:var(--db-shadow); }
        .atalho-icon {
            width:38px; height:38px; border-radius:10px; flex-shrink:0;
            display:flex; align-items:center; justify-content:center; font-size:1.05rem;
            background:var(--accent-soft); color:var(--accent-ink);
        }
        .atalho-label { font-size:0.85rem; font-weight:800; color:var(--color-text-dark); }
        .atalho-sub   { font-size:0.72rem; color:var(--color-text-muted); font-weight:600; margin-top:1px; }

        /* ── Grid 2 colunas ── */
        .db-grid { display:grid; grid-template-columns:1.15fr 1fr; gap:1.1rem; align-items:start; }
        @media(max-width:940px) { .db-grid { grid-template-columns:1fr; } }

        /* ── Card genérico ── */
        .db-card { background:white; border:1px solid var(--color-border); border-radius:var(--db-radius); box-shadow:var(--db-shadow); overflow:hidden; }
        .db-card-header {
            padding:0.9rem 1.15rem;
            border-bottom:1px solid var(--color-border);
            display:flex; align-items:center; justify-content:space-between;
        }
        .db-card-title { font-size:0.82rem; font-weight:800; color:var(--color-text-dark); display:flex; align-items:center; gap:0.5rem; }
        .db-card-title .chip {
            font-size:0.62rem; font-weight:800; padding:2px 7px; border-radius:20px;
            background:var(--warn-soft); color:var(--warn); text-transform:uppercase; letter-spacing:0.04em;
        }
        .db-card-title .chip.chip-crit { background:var(--crit-soft); color:var(--crit); }
        .db-card-link  { font-size:0.76rem; font-weight:700; color:var(--color-accent-primary); text-decoration:none; }
        .db-card-link:hover { text-decoration:underline; }
        .db-card-body  { padding:1.1rem 1.15rem; }

        /* ── Regiões ── */
        .reg-item { margin-bottom:1rem; }
        .reg-item:last-child { margin-bottom:0; }
        .reg-header { display:flex; justify-content:space-between; align-items:baseline; margin-bottom:0.4rem; }
        .reg-nome   { font-size:0.85rem; font-weight:800; color:var(--color-text-dark); }
        .reg-nums   { font-size:0.74rem; color:var(--color-text-muted); font-weight:600; }
        .reg-bar    { height:9px; background:var(--color-bg-primary); border-radius:5px; overflow:hidden; display:flex; }
        .reg-bar-ocup { background:var(--crit); }
        .reg-bar-disp { background:var(--ok); }

        /* ── Vencimentos: seção + cards horizontais ── */
        .db-section-head { display:flex; align-items:center; justify-content:space-between; }
        .db-section-head h2 { font-size:1rem; font-weight:800; color:var(--color-text-dark); margin:0; }
        .db-section-head .db-card-link { font-size:0.8rem; }

        .venc-scroll { display:flex; gap:0.9rem; overflow-x:auto; overflow-y:visible; padding-top:0.25rem; padding-bottom:0.35rem; scroll-snap-type:x proximity; }
        .venc-card {
            scroll-snap-align:start; flex:0 0 240px;
            background:white; border:1px solid var(--color-border); border-radius:var(--db-radius);
            box-shadow:var(--db-shadow); padding:1rem 1.05rem;
            display:flex; flex-direction:column; gap:0.5rem;
            text-decoration:none; color:var(--color-text-dark);
            transition:border-color 0.15s, transform 0.15s;
        }
        .venc-card:hover { border-color:var(--color-accent-primary); transform:translateY(-2px); }
        .venc-card-top { display:flex; align-items:center; justify-content:space-between; }
        .venc-tag { font-size:0.66rem; font-weight:700; color:var(--color-text-muted); background:var(--color-bg-primary); padding:2px 8px; border-radius:20px; text-transform:uppercase; letter-spacing:0.04em; }
        .venc-card-title { font-size:0.95rem; font-weight:800; }
        .venc-card-sub { font-size:0.76rem; color:var(--color-text-muted); font-weight:600; }
        .venc-card-qty { font-size:0.74rem; color:var(--color-text-muted); }
        .venc-card-chips { display:flex; gap:0.4rem; flex-wrap:wrap; }
        .chip-mini { font-size:0.65rem; font-weight:800; padding:3px 8px; border-radius:20px; text-transform:uppercase; letter-spacing:0.03em; }
        .chip-checking { background:var(--color-bg-primary); color:var(--color-text-muted); }
        .dias-urgente { background:var(--crit-soft); color:var(--crit); }
        .dias-atencao { background:var(--warn-soft); color:var(--warn); }
        .dias-ok      { background:var(--ok-soft); color:var(--ok); }
        .venc-card-cta { font-size:0.78rem; font-weight:800; color:var(--color-accent-primary); margin-top:0.2rem; }

        /* ── Reservas recentes ── */
        .res-item {
            display:flex; align-items:center; gap:0.8rem;
            padding:0.65rem 0; border-bottom:1px solid var(--color-border);
        }
        .res-item:last-child { border-bottom:none; }
        .res-avatar {
            width:34px; height:34px; border-radius:9px; flex-shrink:0;
            background:var(--color-bg-primary); display:flex; align-items:center; justify-content:center;
            font-weight:800; font-size:0.78rem; color:var(--color-text-dark);
        }
        .res-info { flex:1; min-width:0; }
        .res-cli  { font-size:0.83rem; font-weight:800; color:var(--color-text-dark); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .res-sub  { font-size:0.72rem; color:var(--color-text-muted); font-weight:600; margin-top:1px; }
        .res-pts  { font-size:0.75rem; font-weight:800; color:var(--color-text-dark); white-space:nowrap; }

        .status-badge { display:inline-block; padding:2px 8px; border-radius:20px; font-size:0.63rem; font-weight:800; text-transform:uppercase; letter-spacing:0.03em; }
        .status-rascunho { background:var(--color-bg-primary); color:var(--color-text-muted); }
        .status-enviada  { background:var(--warn-soft); color:var(--warn); }
        .status-aprovada { background:var(--ok-soft); color:var(--ok); }
        .status-recusada { background:var(--crit-soft); color:var(--crit); }

        .db-empty { padding:1.5rem; text-align:center; color:var(--color-text-muted); font-size:0.82rem; }
        .db-section-label { font-size:0.63rem; font-weight:800; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:0.08em; padding-bottom:0.4rem; border-bottom:1px dashed var(--color-border); margin-bottom:0.5rem; }

        /* ── Banners de alerta urgente ── */
        .db-alertas { display:flex; flex-direction:column; gap:0.6rem; }
        .db-alerta-banner {
            border-radius:10px; padding:0.85rem 1.1rem; border-left:5px solid var(--crit);
            display:flex; align-items:center; gap:0.85rem;
            font-size:0.86rem; font-weight:600; background:var(--crit-soft); color:var(--color-text-dark);
        }
        .db-alerta-banner a { font-weight:800; color:var(--crit); border-bottom:1.5px solid currentColor; text-decoration:none; }
        .db-alerta-banner.alerta-pendente { border-left-color:var(--warn); background:var(--warn-soft); }
        .db-alerta-banner.alerta-pendente a { color:var(--warn); }
        .db-alerta-badge {
            flex-shrink:0; width:26px; height:26px; border-radius:7px;
            display:flex; align-items:center; justify-content:center;
            background:var(--crit); color:#fff; font-weight:800; font-size:0.78rem;
        }
        .db-alerta-banner.alerta-pendente .db-alerta-badge { background:var(--warn); }
    </style>
</head>
<body>

<?php require __DIR__ . '/../app/Views/layouts/_nav.php'; ?>

<div class="db-page">

    <?php if ($loginSucesso): ?>
    <div class="db-alert">✅ Login realizado com sucesso! Bem-vindo ao sistema de gestão.</div>
    <?php endif; ?>

    <?php if ($autoProcessados > 0): ?>
    <div class="db-alert" style="background:#fff7ed;border-color:#fdba74;color:#9a3412">
        ⚠️ <strong><?= $autoProcessados ?> contrato<?= $autoProcessados > 1 ? 's' : '' ?> vencido<?= $autoProcessados > 1 ? 's' : '' ?></strong>
        <?= $autoProcessados > 1 ? 'aguardam decisão' : 'aguarda decisão' ?> — renove ou encerre manualmente em Campanhas.
    </div>
    <?php endif; ?>

    <div class="db-header">
        <div class="db-header-title">
            <h1>Dashboard</h1>
            <span class="db-header-time"><?= $dataHoraTopo ?> · hoje em Recife</span>
        </div>
        <span class="db-live-pill"><span class="db-live-dot"></span>Atualizado agora</span>
    </div>

    <!-- ── Alertas urgentes ── -->
    <?php if ($venc7count > 0 || !empty($reservasPendentes)): ?>
    <div class="db-alertas">
        <?php if ($venc7count > 0): ?>
        <div class="db-alerta-banner">
            <div class="db-alerta-badge">!</div>
            <span>
                <strong><?= $venc7count ?> contrato<?= $venc7count > 1 ? 's' : '' ?></strong>
                <?= $venc7count > 1 ? 'vencem' : 'vence' ?> nos próximos <strong>7 dias</strong>. Renove ou encerre antes que o ponto fique parado.
                <a href="#vencimentos">Ver detalhes ↓</a>
            </span>
        </div>
        <?php endif; ?>
        <?php if (!empty($reservasPendentes)): ?>
        <?php $nPend = count($reservasPendentes); ?>
        <div class="db-alerta-banner alerta-pendente">
            <div class="db-alerta-badge">⏳</div>
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
    <?php
        $pctDisp = $totalPontos > 0 ? round($disponivel/$totalPontos*100) : 0;
        $pctOcup = $totalPontos > 0 ? round($ocupado/$totalPontos*100)   : 0;
        $pctRes  = $totalPontos > 0 ? round($reservado/$totalPontos*100) : 0;
    ?>
    <div class="db-kpis">
        <div class="kpi kpi-total">
            <div class="kpi-top"><span class="kpi-label">Total de pontos</span><span class="kpi-icon">📍</span></div>
            <div class="kpi-valor num"><?= $totalPontos ?></div>
            <div class="kpi-sub">ativos no sistema</div>
        </div>
        <div class="kpi kpi-disponivel">
            <div class="kpi-top"><span class="kpi-label">Disponíveis</span><span class="kpi-icon">✓</span></div>
            <div class="kpi-valor num"><?= $disponivel ?></div>
            <div class="kpi-sub"><?= $pctDisp ?>% do total</div>
            <div class="kpi-bar"><span style="width:<?= $pctDisp ?>%;background:var(--ok)"></span></div>
        </div>
        <div class="kpi kpi-ocupado">
            <div class="kpi-top"><span class="kpi-label">Ocupados</span><span class="kpi-icon">●</span></div>
            <div class="kpi-valor num"><?= $ocupado ?></div>
            <div class="kpi-sub"><?= $pctOcup ?>% do total</div>
            <div class="kpi-bar"><span style="width:<?= $pctOcup ?>%;background:var(--crit)"></span></div>
        </div>
        <div class="kpi kpi-reservado">
            <div class="kpi-top"><span class="kpi-label">Reservados</span><span class="kpi-icon">◐</span></div>
            <div class="kpi-valor num"><?= $reservado ?></div>
            <div class="kpi-sub"><?= $pctRes ?>% do total</div>
        </div>
    </div>

    <!-- ── Pontos com contrato vencendo ── -->
    <?php if (!empty($vencimentos)): ?>
    <div id="vencimentos" style="display:flex;flex-direction:column;gap:0.9rem;">
        <div class="db-section-head">
            <h2>Contratos com vencimento <span style="color:var(--color-text-muted);font-weight:600;font-size:0.82rem">(<?= $venc30count ?> vencidos ou vencendo em <?= $mesesCompletos[(int)date('n') - 1] ?>)</span></h2>
            <a href="/gestor/pontos?situacao=Ocupado" class="db-card-link">Ver todos →</a>
        </div>
        <div class="venc-scroll">
            <?php foreach ($vencimentos as $v):
                $dias = (int)$v['dias_restantes'];
                if ($dias < 0) { $cls = 'dias-urgente'; $label = 'Expirado'; }
                elseif ($dias <= 7) { $cls = 'dias-urgente'; $label = $dias === 0 ? 'Vence hoje' : "Vence em {$dias}d"; }
                elseif ($dias <= 15) { $cls = 'dias-atencao'; $label = "Vence em {$dias}d"; }
                else { $cls = 'dias-ok'; $label = 'Em dia'; }
            ?>
            <a href="/gestor/pontos/detalhes?id=<?= $v['id'] ?>" class="venc-card">
                <div class="venc-card-top">
                    <span class="venc-tag"><?= htmlspecialchars($v['tipo'] ?: 'Ponto') ?></span>
                </div>
                <div class="venc-card-title"><?= htmlspecialchars($v['cliente'] ?: ('Ponto ' . $v['numero'])) ?></div>
                <div class="venc-card-sub"><?= htmlspecialchars($v['logradouro']) ?> · <?= htmlspecialchars($v['cidade'] ?? '—') ?></div>
                <div class="venc-card-qty"><?= $v['qtd_pontos'] ?> <?= $v['qtd_pontos'] == 1 ? 'ponto' : 'pontos' ?></div>
                <div class="venc-card-chips">
                    <?php if ($v['tem_checking']): ?><span class="chip-mini chip-checking">Checking</span><?php endif; ?>
                    <span class="chip-mini <?= $cls ?>"><?= $label ?></span>
                </div>
                <span class="venc-card-cta">Renovar contrato →</span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Regiões + Reservas ── -->
    <div class="db-grid">

        <!-- Ocupação por região -->
        <div class="db-card">
            <div class="db-card-header">
                <span class="db-card-title">Ocupação por Região</span>
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
                    Pré-Seleções
                    <?php if (!empty($reservasPendentes)): ?>
                    <span class="chip"><?= count($reservasPendentes) ?> pendente<?= count($reservasPendentes) > 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                </span>
                <a href="/gestor/reservas" class="db-card-link">Ver todas →</a>
            </div>
            <div class="db-card-body">
                <?php
                    function db_iniciais($nome) {
                        $partes = preg_split('/\s+/', trim($nome));
                        $ini = strtoupper(mb_substr($partes[0] ?? '', 0, 1));
                        if (count($partes) > 1) $ini .= strtoupper(mb_substr(end($partes), 0, 1));
                        return $ini ?: '—';
                    }
                ?>
                <?php if (!empty($reservasPendentes)): ?>
                <!-- pendentes primeiro, destacadas -->
                <div class="db-section-label">⏳ Aguardando confirmação</div>
                <?php foreach ($reservasPendentes as $res): ?>
                <div class="res-item" style="background:var(--warn-soft);margin:0 -1.15rem;padding:0.65rem 1.15rem;">
                    <div class="res-avatar"><?= db_iniciais($res['cliente']) ?></div>
                    <div class="res-info">
                        <div class="res-cli"><?= htmlspecialchars($res['cliente']) ?></div>
                        <div class="res-sub">
                            Enviada <?= date('d/m/Y', strtotime($res['criado_em'])) ?>
                            <?php
                                $diasEsperando = (int)floor((time() - strtotime($res['criado_em'])) / 86400);
                            ?>
                            <?php if ($diasEsperando >= 3): ?>
                            · <span style="color:var(--crit);font-weight:700"><?= $diasEsperando ?>d aguardando</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="res-pts num"><?= $res['total_pontos'] ?> pt<?= $res['total_pontos'] != 1 ? 's' : '' ?></div>
                    <a href="/gestor/reservas/ver?id=<?= $res['id'] ?>"
                       style="font-size:0.75rem;font-weight:700;color:var(--color-accent-primary);text-decoration:none">Ver →</a>
                </div>
                <?php endforeach; ?>
                <?php if (!empty($reservasRecentes)): ?>
                <div class="db-section-label" style="margin-top:0.9rem">Histórico recente</div>
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
                    <div class="res-avatar"><?= db_iniciais($res['cliente']) ?></div>
                    <div class="res-info">
                        <div class="res-cli"><?= htmlspecialchars($res['cliente']) ?></div>
                        <div class="res-sub">
                            <?= date('d/m/Y', strtotime($res['criado_em'])) ?>
                            &nbsp;·&nbsp;
                            <span class="status-badge status-<?= $status ?>"><?= $statusLabel ?></span>
                        </div>
                    </div>
                    <div class="res-pts num"><?= $res['total_pontos'] ?> pt<?= $res['total_pontos'] != 1 ? 's' : '' ?></div>
                    <a href="/gestor/reservas/ver?id=<?= $res['id'] ?>"
                       style="font-size:0.75rem;font-weight:700;color:var(--color-accent-primary);text-decoration:none">Ver →</a>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

    </div>

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
