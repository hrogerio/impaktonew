<?php
/**
 * GET /gestor/campanhas/checking/historico
 * Histórico e busca de checkings fotográficos já realizados.
 */
ini_set('display_errors', 0);
ini_set('log_errors', 1);
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

require_once __DIR__ . '/../../../../config/database.php';
$pdo = getDatabase();

$busca = trim($_GET['busca'] ?? '');

$sql = "
    SELECT
        cf.cliente, cf.agencia, cf.campanha, cf.situacao, cf.inicio, cf.fim,
        MAX(c.nome)                 AS nome_projeto,
        COUNT(*)                    AS total_fotos,
        COUNT(DISTINCT cf.ponto_id) AS total_pontos,
        GROUP_CONCAT(DISTINCT cf.ponto_id) AS ponto_ids,
        MIN(cf.criado_em)           AS primeiro_envio,
        MAX(cf.criado_em)           AS ultimo_envio
    FROM checking_fotos cf
    LEFT JOIN campanhas c
        ON c.ponto_id = cf.ponto_id
       AND c.cliente   = cf.cliente
       AND c.campanha  = cf.campanha
       AND c.situacao  = cf.situacao
       AND c.inicio <=> cf.inicio
       AND c.fim    <=> cf.fim
";
$params = [];
if ($busca !== '') {
    $sql .= " WHERE cf.cliente LIKE ? OR cf.agencia LIKE ? OR cf.campanha LIKE ? OR c.nome LIKE ? ";
    $like = '%' . $busca . '%';
    $params = [$like, $like, $like, $like];
}
$sql .= " GROUP BY cf.cliente, cf.agencia, cf.campanha, cf.situacao, cf.inicio, cf.fim
          ORDER BY ultimo_envio DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);

function fmtDataHist($d) {
    if (!$d) return '—';
    try { return (new DateTime($d))->format('d/m/Y'); } catch (Exception $e) { return $d; }
}
function fmtDataHoraHist($d) {
    if (!$d) return '—';
    try { return (new DateTime($d))->format('d/m/Y \à\s H:i'); } catch (Exception $e) { return $d; }
}

$paginaAtual = 'campanhas';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/public/assets/css/gestor.css?v=2">
<title>Histórico de Checking Fotográfico</title>
<style>
:root {
    --ck-purple: #7e22ce;
    --ck-purple-light: #f3e8ff;
    --ck-purple-border: #d8b4fe;
}

.ckh-page { max-width: 1100px; margin: 0 auto; padding: 1.5rem 1rem 6rem; }

.ckh-breadcrumb { font-size: 0.72rem; color: var(--color-text-muted); font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; margin-bottom: 0.3rem; }
.ckh-breadcrumb a { color: var(--color-text-muted); text-decoration: none; }
.ckh-breadcrumb a:hover { color: var(--color-accent-primary); }

.ckh-header {
    display: flex; align-items: center; justify-content: space-between;
    gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1.25rem;
}
.ckh-titulo { font-size: 1.3rem; font-weight: 800; color: var(--color-text-dark); margin: 0; }

.ckh-busca-wrap {
    display: flex; align-items: center; gap: 0.5rem;
    margin-bottom: 1.25rem;
}
.ckh-busca-input {
    flex: 1; max-width: 420px;
    border: 1.5px solid var(--color-border); border-radius: 9px;
    padding: 0.6rem 0.9rem; font-size: 0.85rem;
    font-family: inherit;
}
.ckh-busca-input:focus { outline: none; border-color: var(--ck-purple); }
.ckh-busca-btn {
    background: var(--ck-purple); color: #fff; border: none;
    border-radius: 9px; padding: 0.6rem 1.1rem;
    font-size: 0.85rem; font-weight: 700; cursor: pointer;
}
.ckh-busca-btn:hover { background: #6b21a8; }
.ckh-busca-limpar {
    font-size: 0.8rem; color: var(--color-text-muted); text-decoration: none;
    padding: 0.6rem 0.3rem;
}
.ckh-busca-limpar:hover { color: var(--color-accent-primary); }

.ckh-count { font-size: 0.8rem; color: var(--color-text-muted); margin-bottom: 1rem; }
.ckh-count b { color: var(--color-text-dark); }

.ckh-card {
    background: #fff; border: 1.5px solid var(--color-border);
    border-radius: 14px; padding: 1.1rem 1.3rem; margin-bottom: 1rem;
    display: flex; align-items: center; justify-content: space-between;
    gap: 1rem; flex-wrap: wrap;
    transition: box-shadow 0.2s;
}
.ckh-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,0.07); }

.ckh-card-info { flex: 1; min-width: 240px; }
.ckh-card-titulo { font-weight: 800; font-size: 0.95rem; color: var(--color-text-dark); }
.ckh-card-titulo-sep { color: var(--color-text-muted); font-weight: 700; margin: 0 0.1rem; }
.ckh-card-sub { font-size: 0.78rem; color: var(--color-text-muted); margin-top: 2px; display: flex; gap: 0.7rem; flex-wrap: wrap; }
.ckh-card-sub b { color: var(--color-text-dark); }

.ckh-sit-badge {
    display: inline-block; padding: 2px 10px; border-radius: 20px;
    font-size: 0.65rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.05em; background: #fde8e8; color: #c0392b;
}

.ckh-card-meta { font-size: 0.74rem; color: var(--color-text-muted); margin-top: 4px; }
.ckh-data-destaque {
    color: #2563eb;
    background: #eff6ff;
    font-weight: 700;
    padding: 1px 7px;
    border-radius: 6px;
}

.ckh-card-actions { display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0; }
.ckh-fotos-badge {
    background: var(--ck-purple-light); color: var(--ck-purple);
    border: 1px solid var(--ck-purple-border);
    padding: 3px 10px; border-radius: 20px;
    font-size: 0.72rem; font-weight: 700; white-space: nowrap;
}
.ckh-btn {
    display: inline-flex; align-items: center; gap: 0.35rem;
    border-radius: 8px; padding: 0.5rem 0.9rem;
    font-size: 0.78rem; font-weight: 700; text-decoration: none;
    transition: background 0.15s;
    white-space: nowrap;
}
.ckh-btn-abrir { background: var(--ck-purple); color: #fff; }
.ckh-btn-abrir:hover { background: #6b21a8; }
.ckh-btn-pdf { background: #f3f4f6; color: var(--color-text-dark); border: 1.5px solid var(--color-border); }
.ckh-btn-pdf:hover { background: #e5e7eb; }

.ckh-vazio {
    text-align: center; padding: 3rem 1rem; color: var(--color-text-muted);
}
.ckh-vazio-icon { font-size: 2rem; margin-bottom: 0.5rem; }
</style>
</head>
<body>
<?php include __DIR__ . '/../../partials/env_banner.php'; ?>

<?php require __DIR__ . '/../../layouts/_nav.php'; ?>

<div class="ckh-page">

    <div class="ckh-breadcrumb">
        <a href="/gestor/campanhas">Campanhas</a> › Histórico de Checking Fotográfico
    </div>

    <div class="ckh-header">
        <h1 class="ckh-titulo">🕓 Histórico de Checking Fotográfico</h1>
        <a href="/gestor/campanhas" class="ckh-btn ckh-btn-pdf">← Voltar</a>
    </div>

    <form class="ckh-busca-wrap" method="get" action="/gestor/campanhas/checking/historico">
        <input type="text" name="busca" class="ckh-busca-input"
               placeholder="Buscar por cliente, agência ou campanha…"
               value="<?= htmlspecialchars($busca) ?>">
        <button type="submit" class="ckh-busca-btn">🔍 Buscar</button>
        <?php if ($busca !== ''): ?>
        <a href="/gestor/campanhas/checking/historico" class="ckh-busca-limpar">Limpar</a>
        <?php endif; ?>
    </form>

    <div class="ckh-count">
        <b><?= count($grupos) ?></b> checking<?= count($grupos) !== 1 ? 's' : '' ?> encontrado<?= count($grupos) !== 1 ? 's' : '' ?>
        <?= $busca !== '' ? ' para "' . htmlspecialchars($busca) . '"' : '' ?>
    </div>

    <?php if (empty($grupos)): ?>
    <div class="ckh-vazio">
        <div class="ckh-vazio-icon">📷</div>
        <?= $busca !== '' ? 'Nenhum checking encontrado para essa busca.' : 'Nenhum checking fotográfico realizado ainda.' ?>
    </div>
    <?php else: ?>
        <?php foreach ($grupos as $g):
            $pontoIds = array_values(array_filter(array_map('intval', explode(',', $g['ponto_ids'] ?? '')), fn($id) => $id > 0));

            $q = http_build_query([
                'cliente'      => $g['cliente'],
                'agencia'      => $g['agencia'],
                'campanha'     => $g['campanha'],
                'situacao'     => $g['situacao'],
                'inicio'       => $g['inicio'] ? substr($g['inicio'], 0, 10) : '',
                'fim'          => $g['fim']    ? substr($g['fim'],    0, 10) : '',
            ]);
            foreach ($pontoIds as $pid) { $q .= '&pontoIds[]=' . $pid; }

            $periodoFmt = ($g['inicio'] || $g['fim'])
                ? (fmtDataHist($g['inicio']) . ' → ' . fmtDataHist($g['fim']))
                : '—';

            $titulo = $g['nome_projeto']
                ? htmlspecialchars($g['nome_projeto']) . ' <span class="ckh-card-titulo-sep">&gt;</span> ' . htmlspecialchars($g['campanha'] ?: '—')
                : htmlspecialchars($g['campanha'] ?: '(sem nome de campanha)');
        ?>
        <div class="ckh-card">
            <div class="ckh-card-info">
                <div class="ckh-card-titulo"><?= $titulo ?></div>
                <div class="ckh-card-sub">
                    <span><?= htmlspecialchars($g['cliente']) ?></span>
                    <?php if ($g['agencia']): ?><span>· <?= htmlspecialchars($g['agencia']) ?></span><?php endif; ?>
                    <span>· <b><?= $periodoFmt ?></b></span>
                    <span>· <?= $g['total_pontos'] ?> ponto<?= $g['total_pontos'] != 1 ? 's' : '' ?></span>
                    <span class="ckh-sit-badge"><?= htmlspecialchars($g['situacao']) ?></span>
                </div>
                <div class="ckh-card-meta">
                    Último envio: <span class="ckh-data-destaque"><?= fmtDataHoraHist($g['ultimo_envio']) ?></span>
                </div>
            </div>
            <div class="ckh-card-actions">
                <span class="ckh-fotos-badge">📷 <?= $g['total_fotos'] ?> foto<?= $g['total_fotos'] != 1 ? 's' : '' ?></span>
                <a href="/gestor/campanhas/checking/pdf?<?= htmlspecialchars($q) ?>" target="_blank" class="ckh-btn ckh-btn-pdf">📄 PDF</a>
                <a href="/gestor/campanhas/checking?<?= htmlspecialchars($q) ?>" class="ckh-btn ckh-btn-abrir">Abrir →</a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

</body>
</html>
