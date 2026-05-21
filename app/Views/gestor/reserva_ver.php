<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}
$paginaAtual = 'reservas';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(404);
    die('Reserva não encontrada.');
}

try {
    require_once __DIR__ . '/../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("Erro na conexão com o banco de dados.");
}

// Cabeçalho da pré-seleção
$stmt = $pdo->prepare("SELECT * FROM pre_selecoes WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$ps = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ps) {
    http_response_code(404);
    die('Reserva não encontrada.');
}

// Pontos da pré-seleção
$stmt2 = $pdo->prepare("
    SELECT p.id, p.numero, p.logradouro, p.descricao, p.cidade, p.regiao,
           p.tipo, p.situacao, p.formato, p.cliente AS ponto_cliente,
           COALESCE(
               (SELECT pf.caminho FROM ponto_fotos pf WHERE pf.ponto_id = p.id AND pf.principal = 1 LIMIT 1),
               p.foto
           ) AS foto
    FROM pre_selecao_pontos psp
    JOIN pontos p ON p.id = psp.ponto_id
    WHERE psp.pre_selecao_id = ?
    ORDER BY psp.ordem ASC, p.numero ASC
");
$stmt2->execute([$id]);
$pontos = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Formatação do período
$periodo = '';
if ($ps['sem_periodo']) {
    $periodo = 'Sem período definido';
} elseif ($ps['periodo_ini'] && $ps['periodo_fim']) {
    $periodo = date('d/m/Y', strtotime($ps['periodo_ini'])) . ' – ' . date('d/m/Y', strtotime($ps['periodo_fim']));
} elseif ($ps['periodo_ini']) {
    $periodo = 'A partir de ' . date('d/m/Y', strtotime($ps['periodo_ini']));
}

// Agrupar por região
$grupos = []; $ordem = [];
foreach ($pontos as $p) {
    $reg = trim($p['regiao'] ?? '') ?: 'Sem região';
    if (!isset($grupos[$reg])) { $grupos[$reg] = []; $ordem[] = $reg; }
    $grupos[$reg][] = $p;
}
usort($ordem, function($a, $b) {
    if ($a === 'Sem região') return 1;
    if ($b === 'Sem região') return -1;
    return strcmp($a, $b);
});

$CORES = [
    'Disponivel' => '#1a9059', 'Disponível' => '#1a9059',
    'Ocupado'    => '#dc3545',
    'Reservado'  => '#fd7e14',
    'Vencido'    => '#6c757d',
    'Permuta'    => '#51086e',
    'Bisemana'   => '#0284c7',
];
function corSit($sit, $cores) { return $cores[$sit] ?? '#888'; }
function badgeSit($sit) {
    if (!$sit) return '<span class="badge-sit sit-outro">—</span>';
    $map = ['disponivel'=>'sit-disponivel','disponível'=>'sit-disponivel','ocupado'=>'sit-ocupado','reservado'=>'sit-reservado','vencido'=>'sit-vencido','permuta'=>'sit-permuta','bisemana'=>'sit-bisemana'];
    $key = strtolower(iconv('UTF-8','ASCII//TRANSLIT',$sit));
    $cls = $map[$key] ?? 'sit-outro';
    return '<span class="badge-sit '.$cls.'">'.htmlspecialchars($sit).'</span>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserva #<?= $ps['id'] ?> — <?= htmlspecialchars($ps['cliente']) ?></title>
    <link rel="icon" href="/public/assets/img/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
    <style>
        .pv-page { max-width:960px; margin:0 auto; padding:1.5rem 1.5rem 3rem; }
        .pv-titulo { display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap; }
        .pv-titulo h1 { font-size:1.2rem; font-weight:800; color:var(--color-text-dark); margin:0; flex:1; }
        .pv-volta { color:var(--color-text-muted); font-size:0.82rem; font-weight:600; text-decoration:none; }
        .pv-volta:hover { color:var(--color-accent-primary); }

        /* Meta da proposta */
        .pv-meta {
            display:flex; flex-wrap:wrap; gap:1rem;
            background:white; border:1px solid var(--color-border);
            border-radius:10px; padding:1rem 1.25rem; margin-bottom:1.25rem;
        }
        .pv-meta-item { flex:1; min-width:150px; }
        .pv-meta-label { font-size:0.65rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:0.4px; margin-bottom:0.2rem; }
        .pv-meta-val   { font-size:0.88rem; font-weight:700; color:var(--color-text-dark); }

        /* Ações */
        .pv-acoes { display:flex; gap:0.6rem; margin-bottom:1.25rem; flex-wrap:wrap; }
        .btn-acao-sec {
            display:inline-flex; align-items:center; gap:0.3rem;
            padding:0.5rem 1rem; border-radius:8px; font-family:'Montserrat',sans-serif;
            font-size:0.82rem; font-weight:700; cursor:pointer; text-decoration:none;
            transition:all 0.15s; border:1.5px solid;
        }
        .btn-reabrir-ps { background:#fff3f3; border-color:#fca5a5; color:var(--color-accent-primary); }
        .btn-reabrir-ps:hover { background:var(--color-accent-primary); color:white; border-color:var(--color-accent-primary); }
        .btn-imprimir-ps { background:#ebf5fb; border-color:#a9cce3; color:#2980b9; }
        .btn-imprimir-ps:hover { background:#2980b9; color:white; border-color:#2980b9; }

        /* Grupos e tabela */
        .pv-grupo-header {
            background:#f0f0f0; padding:0.4rem 0.85rem;
            font-size:0.72rem; font-weight:800; text-transform:uppercase;
            letter-spacing:0.6px; color:#333;
            border-left:4px solid var(--color-accent-primary);
            margin:1rem 0 0;
        }
        .pv-card { background:white; border:1px solid var(--color-border); border-radius:10px; overflow:hidden; }
        .pv-table { width:100%; border-collapse:collapse; font-size:0.82rem; }
        .pv-table th {
            background:var(--color-bg-primary); padding:0.45rem 0.75rem;
            font-size:0.68rem; font-weight:700; color:var(--color-text-muted);
            text-transform:uppercase; letter-spacing:0.4px;
            border-bottom:1.5px solid var(--color-border); text-align:left;
        }
        .pv-table td { padding:0.55rem 0.75rem; border-bottom:1px solid var(--color-border); vertical-align:middle; }
        .pv-table tbody tr:last-child td { border-bottom:none; }
        .pv-table tbody tr:hover { background:#fafafa; }
        .pv-num  { font-weight:800; color:var(--color-accent-primary); width:50px; }
        .pv-foto { width:68px; padding:4px 6px !important; }
        .pv-thumb { width:60px; height:48px; border-radius:5px; object-fit:cover; display:block; }
        .pv-thumb-vazio { width:60px; height:48px; border-radius:5px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; font-size:1.2rem; color:#ccc; }

        .badge-sit { display:inline-block; padding:2px 8px; border-radius:20px; font-size:0.65rem; font-weight:700; text-transform:uppercase; white-space:nowrap; }
        .sit-disponivel { background:#dcfce7; color:#166534; }
        .sit-ocupado    { background:#fee2e2; color:#991b1b; }
        .sit-reservado  { background:#ffedd5; color:#9a3412; }
        .sit-vencido    { background:#f3e8ff; color:#6b21a8; }
        .sit-permuta    { background:#ede9fe; color:#4c1d95; }
        .sit-bisemana   { background:#cffafe; color:#164e63; }
        .sit-outro      { background:#f1f5f9; color:#475569; }

        .pv-total { padding:0.6rem 0.85rem; font-size:0.82rem; font-weight:800; border-top:2px solid var(--color-border); }

        /* Toast */
        .toast {
            position:fixed; bottom:1.5rem; right:1.5rem; z-index:9999;
            background:#1a9059; color:white; padding:0.75rem 1.25rem;
            border-radius:8px; font-size:0.83rem; font-weight:700;
            box-shadow:0 4px 16px rgba(0,0,0,0.2);
            transform:translateY(80px); opacity:0; transition:all 0.3s ease;
        }
        .toast.show { transform:translateY(0); opacity:1; }

        /* Print */
        @media print {
            .no-print { display:none !important; }
            .pv-acoes, .header { display:none !important; }
        }
    </style>
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="pv-page">

    <div class="pv-titulo">
        <a href="/gestor/reservas" class="pv-volta no-print">← Reservas</a>
        <h1>📋 Reserva #<?= $ps['id'] ?> — <?= htmlspecialchars($ps['cliente']) ?></h1>
    </div>

    <!-- Meta -->
    <div class="pv-meta">
        <div class="pv-meta-item">
            <div class="pv-meta-label">Cliente</div>
            <div class="pv-meta-val"><?= htmlspecialchars($ps['cliente']) ?></div>
        </div>
        <?php if ($ps['agencia']): ?>
        <div class="pv-meta-item">
            <div class="pv-meta-label">Agência</div>
            <div class="pv-meta-val"><?= htmlspecialchars($ps['agencia']) ?></div>
        </div>
        <?php endif; ?>
        <div class="pv-meta-item">
            <div class="pv-meta-label">Período</div>
            <div class="pv-meta-val"><?= htmlspecialchars($periodo ?: '—') ?></div>
        </div>
        <div class="pv-meta-item">
            <div class="pv-meta-label">Total de pontos</div>
            <div class="pv-meta-val"><?= count($pontos) ?></div>
        </div>
        <div class="pv-meta-item">
            <div class="pv-meta-label">Gerada em</div>
            <div class="pv-meta-val"><?= date('d/m/Y H:i', strtotime($ps['criado_em'])) ?></div>
        </div>
        <?php if ($ps['criado_por']): ?>
        <div class="pv-meta-item">
            <div class="pv-meta-label">Por</div>
            <div class="pv-meta-val"><?= htmlspecialchars($ps['criado_por']) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Ações -->
    <div class="pv-acoes no-print">
        <button class="btn-acao-sec btn-reabrir-ps" onclick="reabrir(<?= $ps['id'] ?>)">↩ Reabrir no carrinho</button>
        <button class="btn-acao-sec btn-imprimir-ps" onclick="window.print()">🖨️ Imprimir</button>
    </div>

    <!-- Pontos agrupados -->
    <div class="pv-card">
        <?php if (empty($pontos)): ?>
        <div style="padding:2rem;text-align:center;color:var(--color-text-muted)">Nenhum ponto encontrado nesta proposta.</div>
        <?php else: ?>

        <?php $n = 0; foreach ($ordem as $reg):
            $pts = $grupos[$reg] ?? [];
            if (empty($pts)) continue;
        ?>
        <div class="pv-grupo-header">
            <?= htmlspecialchars($reg) ?> &nbsp;(<?= count($pts) ?> ponto<?= count($pts) > 1 ? 's' : '' ?>)
        </div>
        <table class="pv-table">
            <thead>
                <tr>
                    <th class="pv-foto no-print"></th>
                    <th style="width:50px">Nº</th>
                    <th>Logradouro</th>
                    <th style="width:130px">Cidade</th>
                    <th style="width:100px">Situação</th>
                    <th style="width:80px" class="no-print">Detalhes</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pts as $p): $n++; ?>
            <tr>
                <td class="pv-foto no-print">
                    <?php if ($p['foto']): ?>
                    <img class="pv-thumb" src="/<?= htmlspecialchars($p['foto']) ?>" loading="lazy"
                         onerror="this.outerHTML='<div class=\'pv-thumb-vazio\'>📷</div>'">
                    <?php else: ?>
                    <div class="pv-thumb-vazio">📷</div>
                    <?php endif; ?>
                </td>
                <td class="pv-num"><?= htmlspecialchars($p['numero']) ?></td>
                <td>
                    <div style="font-weight:600"><?= htmlspecialchars($p['logradouro']) ?></div>
                    <?php if ($p['descricao']): ?>
                    <div style="font-size:0.72rem;color:var(--color-text-muted)"><?= htmlspecialchars($p['descricao']) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($p['cidade'] ?? '—') ?></td>
                <td><?= badgeSit($p['situacao']) ?></td>
                <td class="no-print">
                    <a href="/gestor/pontos/detalhes?id=<?= $p['id'] ?>" style="font-size:0.75rem;font-weight:700;color:var(--color-accent-primary);text-decoration:none">Ver →</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endforeach; ?>

        <div class="pv-total">TOTAL: <?= count($pontos) ?> ponto<?= count($pontos) > 1 ? 's' : '' ?></div>
        <?php endif; ?>
    </div>

</div>

<div class="toast" id="toast"></div>

<script>
function reabrir(id) {
    fetch('/gestor/pre-selecao/dados?id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.erro) { alert('Erro ao carregar a proposta.'); return; }
            localStorage.setItem('impakto_cart', JSON.stringify(data.pontos_ids));
            mostrarToast('✅ ' + data.pontos_ids.length + ' ponto(s) carregado(s)! Redirecionando...');
            setTimeout(function() { window.location.href = '/gestor/pre-selecao'; }, 1200);
        })
        .catch(function() { alert('Erro de comunicação.'); });
}
function mostrarToast(msg) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(function() { t.classList.remove('show'); }, 3000);
}
</script>

</body>
</html>
