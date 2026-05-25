<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}
$paginaAtual = 'campanhas';

require_once __DIR__ . '/../../../config/database.php';
$pdo = getDatabase();

// ── KPIs ──────────────────────────────────────────────────────
$kpi = $pdo->query("
    SELECT
        COUNT(*)                                                      AS total,
        SUM(ativo = 1)                                                AS ativas,
        SUM(ativo = 0)                                                AS encerradas,
        COUNT(DISTINCT NULLIF(TRIM(cliente),''))                      AS clientes,
        SUM(ativo = 1 AND fim IS NOT NULL
            AND fim BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) AS vencendo
    FROM campanhas
")->fetch(PDO::FETCH_ASSOC);

// ── Todas as campanhas com dados do ponto ─────────────────────
$rows = $pdo->query("
    SELECT
        c.id, c.ponto_id, c.cliente, c.agencia, c.campanha,
        c.situacao, c.inicio, c.fim, c.ativo, c.encerrado_em, c.criado_em,
        p.numero, p.logradouro, p.cidade, p.regiao
    FROM campanhas c
    JOIN pontos p ON p.id = c.ponto_id
    ORDER BY
        COALESCE(NULLIF(TRIM(c.cliente),''), 'ZZZZ') ASC,
        c.ativo DESC,
        c.criado_em DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Agrupar: Cliente → Campanha(nome+período+status) → Painéis
$grupos = [];
foreach ($rows as $r) {
    $cli  = trim($r['cliente']  ?? '') ?: '— Sem cliente —';
    $camp = trim($r['campanha'] ?? '') ?: '—';
    // Chave única da campanha: nome + situação + início + fim + status ativo
    $campKey = md5($camp . '|' . $r['situacao'] . '|' . ($r['inicio'] ?? '') . '|' . ($r['fim'] ?? '') . '|' . $r['ativo']);

    if (!isset($grupos[$cli])) $grupos[$cli] = [];
    if (!isset($grupos[$cli][$campKey])) {
        $grupos[$cli][$campKey] = [
            'nome'     => $camp,
            'situacao' => $r['situacao'],
            'ativo'    => (int)$r['ativo'],
            'inicio'   => $r['inicio'],
            'fim'      => $r['fim'],
            'rows'     => [],
        ];
    }
    $grupos[$cli][$campKey]['rows'][] = $r;
}

// Clientes únicos para filtro
$clientes = array_keys($grupos);
sort($clientes);
$clientes = array_filter($clientes, fn($c) => $c !== '— Sem cliente —');

$CORES = [
    'Ocupado'   => '#dc3545', 'Reservado' => '#fd7e14',
    'Permuta'   => '#51086e', 'Bisemana'  => '#0284c7',
    'Vencido'   => '#6c757d',
];
function corSit($s, $cores) { return $cores[$s] ?? '#888'; }
function fmtD($d) {
    if (!$d || $d === '0000-00-00') return '—';
    try { return (new DateTime($d))->format('d/m/Y'); } catch(Exception $e) { return '—'; }
}
function diasRestantes($fim) {
    if (!$fim || $fim === '0000-00-00') return null;
    $d = (new DateTime($fim))->diff(new DateTime())->days;
    return new DateTime($fim) >= new DateTime() ? $d : -$d;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campanhas — Impakto Mídia</title>
    <link rel="icon" href="/public/assets/img/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
    <style>
        .cp-page { max-width:1100px; margin:0 auto; padding:1.5rem 1.5rem 4rem; }

        /* ── KPIs ── */
        .cp-kpis { display:grid; grid-template-columns:repeat(5,1fr); gap:0.75rem; margin-bottom:1.5rem; }
        .cp-kpi {
            background:white; border:1px solid var(--color-border); border-radius:10px;
            padding:0.9rem 1rem; display:flex; flex-direction:column; gap:0.2rem;
        }
        .cp-kpi-label { font-size:0.65rem; font-weight:800; text-transform:uppercase; letter-spacing:0.4px; color:var(--color-text-muted); }
        .cp-kpi-val   { font-size:1.7rem; font-weight:800; color:var(--color-text-dark); line-height:1; }
        .cp-kpi-val.verde  { color:#1a9059; }
        .cp-kpi-val.laranja{ color:#fd7e14; }
        .cp-kpi-val.vermelho{ color:var(--color-accent-primary); }
        .cp-kpi-val.azul   { color:#0284c7; }

        /* ── Filtros ── */
        .cp-filtros {
            display:flex; gap:0.6rem; flex-wrap:wrap;
            background:white; border:1px solid var(--color-border);
            border-radius:10px; padding:0.75rem 1rem; margin-bottom:1.25rem;
            align-items:center;
        }
        .cp-busca-wrap { position:relative; flex:1; min-width:180px; }
        .cp-busca-icon { position:absolute; left:0.65rem; top:50%; transform:translateY(-50%); color:#aaa; font-size:0.85rem; }
        .cp-busca {
            width:100%; padding:0.45rem 0.75rem 0.45rem 2rem;
            border:1px solid var(--color-border); border-radius:7px;
            font-family:'Montserrat',sans-serif; font-size:0.82rem;
        }
        .cp-busca:focus { outline:none; border-color:var(--color-accent-primary); }
        .cp-sel {
            padding:0.45rem 0.65rem; border:1px solid var(--color-border);
            border-radius:7px; font-family:'Montserrat',sans-serif;
            font-size:0.82rem; background:white; color:var(--color-text-dark);
            cursor:pointer;
        }
        .cp-sel:focus { outline:none; border-color:var(--color-accent-primary); }
        .cp-sel.ativo { border-color:var(--color-accent-primary); background:#fff5f5; font-weight:700; }
        .cp-limpar {
            padding:0.45rem 0.9rem; background:#f3f4f6; border:1px solid var(--color-border);
            border-radius:7px; font-size:0.78rem; font-weight:700; color:#555;
            cursor:pointer; display:none; font-family:'Montserrat',sans-serif;
        }
        .cp-limpar.vis { display:block; }
        .cp-contador { font-size:0.78rem; font-weight:700; color:var(--color-text-muted); white-space:nowrap; }

        /* ── Grupos por cliente ── */
        .cp-grupo { margin-bottom:1.25rem; }
        .cp-grupo-header {
            display:flex; align-items:center; gap:0.75rem;
            padding:0.55rem 1rem; background:#f8f9fc;
            border:1px solid var(--color-border); border-radius:10px 10px 0 0;
            cursor:pointer; user-select:none;
        }
        .cp-grupo-header:hover { background:#f0f2f5; }
        .cp-grupo-nome { font-size:0.88rem; font-weight:800; color:var(--color-text-dark); }
        .cp-grupo-count {
            background:var(--color-accent-primary); color:white;
            font-size:0.65rem; font-weight:800; padding:2px 8px; border-radius:10px;
        }
        .cp-grupo-ativas {
            background:#dcfce7; color:#166534;
            font-size:0.65rem; font-weight:800; padding:2px 8px; border-radius:10px;
        }
        .cp-grupo-seta { margin-left:auto; font-size:0.8rem; color:var(--color-text-muted); transition:transform 0.2s; }
        .cp-grupo-seta.fechado { transform:rotate(-90deg); }

        /* ── Tabela ── */
        .cp-table-wrap {
            border:1px solid var(--color-border); border-top:none;
            border-radius:0 0 10px 10px; overflow:hidden;
        }
        .cp-table { width:100%; border-collapse:collapse; font-size:0.82rem; }
        .cp-table th {
            background:#fafbfc; padding:0.45rem 0.75rem;
            font-size:0.65rem; font-weight:800; text-transform:uppercase;
            letter-spacing:0.4px; color:var(--color-text-muted);
            border-bottom:1px solid var(--color-border); text-align:left;
        }
        .cp-table td { padding:0.6rem 0.75rem; border-bottom:1px solid #f5f5f7; vertical-align:middle; }
        .cp-table tbody tr:last-child td { border-bottom:none; }
        .cp-table tbody tr:hover { background:#fafafa; }
        .cp-table tbody tr.encerrada { opacity:0.6; }
        /* Remover borda da tabela externa (não usada mais) */
        .cp-table-wrap { display:none; }

        .cp-num   { font-weight:800; color:var(--color-accent-primary); font-size:0.8rem; }
        .cp-end   { font-size:0.75rem; color:var(--color-text-muted); }
        .cp-camp  { font-weight:700; color:var(--color-text-dark); }
        .cp-sub   { font-size:0.72rem; color:var(--color-text-muted); margin-top:1px; }
        .cp-per   { font-size:0.78rem; color:var(--color-text-muted); white-space:nowrap; }

        .sit-badge {
            display:inline-block; padding:2px 8px; border-radius:10px;
            font-size:0.62rem; font-weight:800; text-transform:uppercase;
            letter-spacing:0.3px; color:white; white-space:nowrap;
        }
        .status-badge {
            display:inline-block; padding:2px 8px; border-radius:10px;
            font-size:0.62rem; font-weight:800; letter-spacing:0.2px; white-space:nowrap;
        }
        .status-ativa    { background:#dcfce7; color:#166534; }
        .status-encerrada{ background:#f1f5f9; color:#475569; }

        .prazo-urg  { background:#fee2e2; color:#991b1b; font-size:0.65rem; font-weight:800; padding:1px 6px; border-radius:8px; margin-left:4px; }
        .prazo-ale  { background:#ffedd5; color:#9a3412; font-size:0.65rem; font-weight:800; padding:1px 6px; border-radius:8px; margin-left:4px; }

        .cp-link { font-size:0.75rem; font-weight:700; color:var(--color-accent-primary); text-decoration:none; }
        .cp-link:hover { text-decoration:underline; }

        /* ── Sub-grupos de campanha ── */
        .cp-grupo-body { border:1px solid var(--color-border); border-top:none; border-radius:0 0 10px 10px; overflow:hidden; }
        .cp-camp-grupo { border-bottom:1px solid #f0f2f5; }
        .cp-camp-grupo:last-child { border-bottom:none; }
        .cp-camp-header {
            display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap;
            padding:0.6rem 1rem; background:#fafbfc; border-bottom:1px solid #f0f2f5;
        }
        .cp-camp-nome { font-size:0.85rem; font-weight:800; color:var(--color-text-dark); }
        .cp-camp-per  { font-size:0.75rem; color:var(--color-text-muted); font-weight:600; }
        .cp-camp-paineis {
            margin-left:auto; font-size:0.7rem; font-weight:700;
            color:var(--color-text-muted); background:#f0f2f5;
            padding:2px 8px; border-radius:8px;
        }
        .cp-paineis-table { width:100%; border-collapse:collapse; }
        .cp-paineis-table td { padding:0.5rem 1rem; border-bottom:1px solid #f5f5f7; vertical-align:middle; }
        .cp-paineis-table tbody tr:last-child td { border-bottom:none; }
        .cp-paineis-table tbody tr:hover { background:#fafafa; }
        .cp-paineis-table tbody tr.encerrada { opacity:0.55; }

        .cp-empty { padding:3rem; text-align:center; color:var(--color-text-muted); font-size:0.85rem; }
        .cp-grupo-hidden { display:none; }

        @media(max-width:800px) {
            .cp-kpis { grid-template-columns:repeat(3,1fr); }
            .cp-table th:nth-child(4),
            .cp-table td:nth-child(4) { display:none; }
        }
        @media(max-width:560px) {
            .cp-kpis { grid-template-columns:repeat(2,1fr); }
        }
    </style>
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="cp-page">

    <!-- ── Título ── -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.75rem">
        <h1 style="font-size:1.3rem;font-weight:800;color:var(--color-text-dark);margin:0">📢 Campanhas</h1>
        <span style="font-size:0.78rem;color:var(--color-text-muted);font-weight:600">
            Histórico completo de ocupações por ponto
        </span>
    </div>

    <!-- ── KPIs ── -->
    <div class="cp-kpis">
        <div class="cp-kpi">
            <div class="cp-kpi-label">Total</div>
            <div class="cp-kpi-val"><?= $kpi['total'] ?></div>
        </div>
        <div class="cp-kpi">
            <div class="cp-kpi-label">Ativas</div>
            <div class="cp-kpi-val verde"><?= $kpi['ativas'] ?></div>
        </div>
        <div class="cp-kpi">
            <div class="cp-kpi-label">Encerradas</div>
            <div class="cp-kpi-val" style="color:var(--color-text-muted)"><?= $kpi['encerradas'] ?></div>
        </div>
        <div class="cp-kpi">
            <div class="cp-kpi-label">Clientes</div>
            <div class="cp-kpi-val azul"><?= $kpi['clientes'] ?></div>
        </div>
        <div class="cp-kpi">
            <div class="cp-kpi-label">Vencendo 30d</div>
            <div class="cp-kpi-val <?= $kpi['vencendo'] > 0 ? 'laranja' : '' ?>"><?= $kpi['vencendo'] ?></div>
        </div>
    </div>

    <!-- ── Filtros ── -->
    <div class="cp-filtros">
        <div class="cp-busca-wrap">
            <span class="cp-busca-icon">🔍</span>
            <input type="text" id="cpBusca" class="cp-busca" placeholder="Buscar cliente, campanha, ponto..." autocomplete="off">
        </div>
        <select id="cpFiltroCliente" class="cp-sel">
            <option value="">Todos os clientes</option>
            <?php foreach ($clientes as $c): ?>
            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="cpFiltroSit" class="cp-sel">
            <option value="">Todas situações</option>
            <?php foreach(['Ocupado','Reservado','Permuta','Bisemana','Vencido'] as $s): ?>
            <option value="<?= $s ?>"><?= $s ?></option>
            <?php endforeach; ?>
        </select>
        <select id="cpFiltroStatus" class="cp-sel">
            <option value="">Ativas + Encerradas</option>
            <option value="1">Só ativas</option>
            <option value="0">Só encerradas</option>
        </select>
        <button class="cp-limpar" id="cpLimpar" onclick="limparFiltros()">✕ Limpar</button>
        <span class="cp-contador" id="cpContador"></span>
    </div>

    <!-- ── Grupos: Cliente → Campanha → Painéis ── -->
    <div id="cpGrupos">
    <?php foreach ($grupos as $cliente => $campanhas):
        $totalPaineis = array_sum(array_map(fn($c) => count($c['rows']), $campanhas));
        $totalAtivas  = array_sum(array_map(fn($c) => $c['ativo'] ? 1 : 0, $campanhas));
        $buscaCli = strtolower($cliente);
    ?>
    <div class="cp-grupo" data-cliente="<?= htmlspecialchars($buscaCli) ?>">

        <!-- Cabeçalho do cliente -->
        <div class="cp-grupo-header" onclick="toggleGrupo(this)">
            <div class="cp-grupo-nome"><?= htmlspecialchars($cliente) ?></div>
            <span class="cp-grupo-count"><?= count($campanhas) ?> campanha<?= count($campanhas) > 1 ? 's' : '' ?> · <?= $totalPaineis ?> painel<?= $totalPaineis > 1 ? 'is' : '' ?></span>
            <?php if ($totalAtivas > 0): ?>
            <span class="cp-grupo-ativas"><?= $totalAtivas ?> ativa<?= $totalAtivas > 1 ? 's' : '' ?></span>
            <?php endif; ?>
            <span class="cp-grupo-seta">▼</span>
        </div>

        <!-- Campanhas deste cliente -->
        <div class="cp-grupo-body">
        <?php foreach ($campanhas as $campKey => $camp):
            $cor      = corSit($camp['situacao'], $CORES);
            $nPaineis = count($camp['rows']);
            $diasC    = $camp['fim'] ? diasRestantes($camp['fim']) : null;
            $ini = fmtD($camp['inicio']); $fim = fmtD($camp['fim']);
            $periodo  = ($ini !== '—' ? $ini : '') . ($fim !== '—' ? ' – ' . $fim : '');
            // Busca string para filtro JS (acumula todos os painéis)
            $buscarStr = strtolower($cliente . ' ' . $camp['nome'] . ' ' . $camp['situacao']);
            foreach ($camp['rows'] as $r) {
                $buscarStr .= ' ' . ($r['numero'] ?? '') . ' ' . ($r['logradouro'] ?? '') . ' ' . ($r['agencia'] ?? '');
            }
        ?>
        <div class="cp-camp-grupo cp-row"
             data-busca="<?= htmlspecialchars($buscarStr) ?>"
             data-situacao="<?= htmlspecialchars($camp['situacao']) ?>"
             data-status="<?= $camp['ativo'] ?>"
             data-cliente="<?= htmlspecialchars($buscaCli) ?>">

            <!-- Sub-cabeçalho da campanha -->
            <div class="cp-camp-header">
                <span class="sit-badge" style="background:<?= $cor ?>"><?= htmlspecialchars($camp['situacao']) ?></span>
                <span class="cp-camp-nome"><?= htmlspecialchars($camp['nome'] !== '—' ? $camp['nome'] : 'Sem nome') ?></span>
                <?php if ($periodo): ?>
                <span class="cp-camp-per"><?= $periodo ?>
                    <?php if ($camp['ativo'] && $diasC !== null && $diasC >= 0 && $diasC <= 30):
                        $cls = $diasC <= 7 ? 'prazo-urg' : 'prazo-ale'; ?>
                    <span class="<?= $cls ?>"><?= $diasC ?>d</span>
                    <?php endif; ?>
                </span>
                <?php endif; ?>
                <span class="cp-camp-paineis"><?= $nPaineis ?> painel<?= $nPaineis > 1 ? 'is' : '' ?></span>
                <?php if ($camp['ativo']): ?>
                <span class="status-badge status-ativa">✅ Ativa</span>
                <?php else: ?>
                <span class="status-badge status-encerrada">Encerrada</span>
                <?php endif; ?>
            </div>

            <!-- Painéis desta campanha -->
            <table class="cp-table cp-paineis-table">
                <tbody>
                <?php foreach ($camp['rows'] as $r): ?>
                <tr class="<?= !$camp['ativo'] ? 'encerrada' : '' ?>">
                    <td style="width:60px"><span class="cp-num"><?= str_pad($r['numero'], 3, '0', STR_PAD_LEFT) ?></span></td>
                    <td>
                        <div style="font-weight:600;font-size:0.82rem"><?= htmlspecialchars($r['logradouro']) ?></div>
                        <div class="cp-sub"><?= htmlspecialchars(implode(' · ', array_filter([$r['cidade'] ?? '', $r['regiao'] ?? '']))) ?></div>
                    </td>
                    <?php if ($r['agencia']): ?>
                    <td><div class="cp-sub">📍 <?= htmlspecialchars($r['agencia']) ?></div></td>
                    <?php else: ?><td></td><?php endif; ?>
                    <td style="width:40px;text-align:right">
                        <a href="/gestor/pontos/detalhes?id=<?= $r['ponto_id'] ?>" class="cp-link" title="Ver ponto">→</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
        </div>

    </div>
    <?php endforeach; ?>
    </div>

    <div class="cp-empty" id="cpEmpty" style="display:none">
        Nenhuma campanha encontrada para os filtros aplicados.
    </div>

</div><!-- /cp-page -->

<script>
var filtros = { busca:'', cliente:'', situacao:'', status:'' };

// ── Filtrar ───────────────────────────────────────────────────
function filtrar() {
    var temFiltro = filtros.busca || filtros.cliente || filtros.situacao || filtros.status !== '';
    document.getElementById('cpLimpar').className = 'cp-limpar' + (temFiltro ? ' vis' : '');

    var totalVis = 0;

    document.querySelectorAll('.cp-grupo').forEach(function(grupo) {
        var clienteGrupo = grupo.dataset.cliente;
        var rows = grupo.querySelectorAll('.cp-row');
        var visGroup = 0;

        rows.forEach(function(tr) {
            var ok = true;
            if (filtros.busca    && tr.dataset.busca.indexOf(filtros.busca) === -1) ok = false;
            if (filtros.cliente  && tr.dataset.cliente !== filtros.cliente)         ok = false;
            if (filtros.situacao && tr.dataset.situacao !== filtros.situacao)       ok = false;
            if (filtros.status !== '' && tr.dataset.status !== filtros.status)      ok = false;
            tr.style.display = ok ? '' : 'none';
            if (ok) visGroup++;
        });

        totalVis += visGroup;
        grupo.style.display = visGroup === 0 ? 'none' : '';
    });

    document.getElementById('cpContador').textContent = totalVis + ' campanha' + (totalVis !== 1 ? 's' : '');
    document.getElementById('cpEmpty').style.display  = totalVis === 0 ? 'block' : 'none';
}

// ── Eventos ───────────────────────────────────────────────────
var debTimer;
document.getElementById('cpBusca').addEventListener('input', function() {
    clearTimeout(debTimer);
    var val = this.value.toLowerCase().trim();
    debTimer = setTimeout(function() { filtros.busca = val; filtrar(); }, 150);
});

document.getElementById('cpFiltroCliente').addEventListener('change', function() {
    filtros.cliente = this.value.toLowerCase();
    this.className = 'cp-sel' + (this.value ? ' ativo' : '');
    filtrar();
});
document.getElementById('cpFiltroSit').addEventListener('change', function() {
    filtros.situacao = this.value;
    this.className = 'cp-sel' + (this.value ? ' ativo' : '');
    filtrar();
});
document.getElementById('cpFiltroStatus').addEventListener('change', function() {
    filtros.status = this.value;
    this.className = 'cp-sel' + (this.value ? ' ativo' : '');
    filtrar();
});

function limparFiltros() {
    filtros = { busca:'', cliente:'', situacao:'', status:'' };
    document.getElementById('cpBusca').value = '';
    ['cpFiltroCliente','cpFiltroSit','cpFiltroStatus'].forEach(function(id) {
        document.getElementById(id).value = '';
        document.getElementById(id).className = 'cp-sel';
    });
    filtrar();
}

// ── Expandir/recolher grupos ──────────────────────────────────
function toggleGrupo(header) {
    var body = header.nextElementSibling;
    var seta = header.querySelector('.cp-grupo-seta');
    var fechado = body.style.display === 'none';
    body.style.display  = fechado ? '' : 'none';
    seta.classList.toggle('fechado', !fechado);
}

// Inicializa contador
filtrar();
</script>

</body>
</html>
