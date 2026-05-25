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
        c.ativo DESC,
        COALESCE(NULLIF(TRIM(c.cliente),''), 'ZZZZ') ASC,
        c.criado_em DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Agrupar: Cliente → CampanhaKey → dados + painéis
$grupos = [];
foreach ($rows as $r) {
    $cli  = trim($r['cliente']  ?? '') ?: '— Sem cliente —';
    $camp = trim($r['campanha'] ?? '') ?: '—';
    $campKey = md5($camp . '|' . $r['situacao'] . '|' . ($r['inicio'] ?? '') . '|' . ($r['fim'] ?? '') . '|' . $r['ativo']);

    if (!isset($grupos[$campKey])) {
        $grupos[$campKey] = [
            'cliente'  => $cli,
            'agencia'  => trim($r['agencia'] ?? ''),
            'nome'     => $camp,
            'situacao' => $r['situacao'],
            'ativo'    => (int)$r['ativo'],
            'inicio'   => $r['inicio'],
            'fim'      => $r['fim'],
            'rows'     => [],
        ];
    }
    $grupos[$campKey]['rows'][] = $r;
}

// Ordena: ativas primeiro, depois por cliente
usort($grupos, function($a, $b) {
    if ($a['ativo'] !== $b['ativo']) return $b['ativo'] - $a['ativo'];
    return strcmp($a['cliente'], $b['cliente']);
});

// Clientes únicos para filtro
$listaClientes = [];
foreach ($grupos as $g) {
    if ($g['cliente'] !== '— Sem cliente —') $listaClientes[$g['cliente']] = true;
}
ksort($listaClientes);
$listaClientes = array_keys($listaClientes);

$CORES = [
    'Ocupado'   => '#dc3545', 'Reservado' => '#fd7e14',
    'Permuta'   => '#51086e', 'Bisemana'  => '#0284c7',
    'Vencido'   => '#6c757d',
];
function corSit($s, $cores) { return $cores[$s] ?? '#888'; }
function fmtD($d) {
    if (!$d || $d === '0000-00-00') return null;
    try { return (new DateTime($d))->format('d/m/Y'); } catch(Exception $e) { return null; }
}
function diasR($fim) {
    if (!$fim || $fim === '0000-00-00') return null;
    $hoje = new DateTime(); $fimDt = new DateTime($fim);
    $diff = (int)$hoje->diff($fimDt)->days;
    return $fimDt >= $hoje ? $diff : -$diff;
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
        .cp-kpi-val.verde   { color:#1a9059; }
        .cp-kpi-val.laranja { color:#fd7e14; }
        .cp-kpi-val.azul    { color:#0284c7; }

        /* ── Filtros ── */
        .cp-filtros {
            display:flex; gap:0.6rem; flex-wrap:wrap;
            background:white; border:1px solid var(--color-border);
            border-radius:10px; padding:0.75rem 1rem; margin-bottom:1.5rem;
            align-items:center;
        }
        .cp-busca-wrap { position:relative; flex:1; min-width:180px; }
        .cp-busca-icon { position:absolute; left:0.65rem; top:50%; transform:translateY(-50%); color:#aaa; font-size:0.85rem; }
        .cp-busca {
            width:100%; padding:0.45rem 0.75rem 0.45rem 2rem;
            border:1px solid var(--color-border); border-radius:7px;
            font-family:'Montserrat',sans-serif; font-size:0.82rem; box-sizing:border-box;
        }
        .cp-busca:focus { outline:none; border-color:var(--color-accent-primary); }
        .cp-sel {
            padding:0.45rem 0.65rem; border:1px solid var(--color-border);
            border-radius:7px; font-family:'Montserrat',sans-serif;
            font-size:0.82rem; background:white; color:var(--color-text-dark); cursor:pointer;
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

        /* ── Grid de cards ── */
        .cp-grid {
            display:grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap:1rem;
        }

        /* ── Card ── */
        .cp-card {
            background:white;
            border:1px solid var(--color-border);
            border-radius:12px;
            overflow:hidden;
            display:flex;
            flex-direction:column;
            transition: box-shadow 0.15s;
        }
        .cp-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
        .cp-card.encerrada { opacity: 0.65; }

        /* Faixa colorida topo */
        .cp-card-faixa {
            height: 4px;
        }

        /* Cabeçalho do card */
        .cp-card-head {
            padding: 0.85rem 1rem 0.6rem;
            border-bottom: 1px solid #f0f2f5;
        }
        .cp-card-top {
            display:flex; align-items:center; gap:0.5rem; margin-bottom:0.35rem; flex-wrap:wrap;
        }
        .sit-badge {
            display:inline-block; padding:2px 9px; border-radius:10px;
            font-size:0.6rem; font-weight:800; text-transform:uppercase;
            letter-spacing:0.4px; color:white; white-space:nowrap; flex-shrink:0;
        }
        .cp-card-nome {
            font-size:0.75rem; font-weight:600; color:var(--color-text-muted);
            flex:1; min-width:0;
        }
        .cp-card-cliente {
            font-size:1rem; font-weight:800; color:var(--color-text-dark);
        }
        .cp-card-agencia {
            font-size:0.72rem; color:var(--color-text-muted); font-weight:600;
        }
        .cp-card-meta {
            display:flex; align-items:center; gap:0.5rem; margin-top:0.4rem; flex-wrap:wrap;
        }
        .cp-card-periodo {
            font-size:0.75rem; color:var(--color-text-muted); font-weight:600;
        }
        .prazo-urg { background:#fee2e2; color:#991b1b; font-size:0.62rem; font-weight:800; padding:1px 7px; border-radius:8px; }
        .prazo-ale { background:#ffedd5; color:#9a3412; font-size:0.62rem; font-weight:800; padding:1px 7px; border-radius:8px; }
        .status-ativa    { background:#dcfce7; color:#166534; font-size:0.62rem; font-weight:800; padding:1px 7px; border-radius:8px; }
        .status-encerrada{ background:#f1f5f9; color:#475569; font-size:0.62rem; font-weight:800; padding:1px 7px; border-radius:8px; }

        /* Lista de painéis */
        .cp-card-paineis { flex:1; }
        .cp-painel-row {
            display:flex; align-items:center; gap:0.6rem;
            padding:0.5rem 1rem; border-bottom:1px solid #f5f5f7;
        }
        .cp-painel-row:last-child { border-bottom:none; }
        .cp-painel-num {
            font-weight:800; color:var(--color-accent-primary);
            font-size:0.78rem; min-width:32px; flex-shrink:0;
        }
        .cp-painel-end {
            flex:1; min-width:0;
        }
        .cp-painel-log {
            font-size:0.78rem; font-weight:600; color:var(--color-text-dark);
            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
        }
        .cp-painel-cid {
            font-size:0.68rem; color:var(--color-text-muted); margin-top:1px;
        }
        .cp-painel-link {
            font-size:0.72rem; font-weight:700; color:var(--color-accent-primary);
            text-decoration:none; flex-shrink:0;
        }
        .cp-painel-link:hover { text-decoration:underline; }

        /* Rodapé do card: contagem de painéis */
        .cp-card-footer {
            padding:0.4rem 1rem;
            background:#fafbfc;
            border-top:1px solid #f0f2f5;
            font-size:0.68rem; font-weight:700; color:var(--color-text-muted);
        }

        .cp-empty { padding:3rem; text-align:center; color:var(--color-text-muted); font-size:0.85rem; }

        @media(max-width:700px) {
            .cp-kpis { grid-template-columns:repeat(3,1fr); }
            .cp-grid  { grid-template-columns:1fr; }
        }
        @media(max-width:480px) {
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
            <?php foreach ($listaClientes as $c): ?>
            <option value="<?= htmlspecialchars(strtolower($c)) ?>"><?= htmlspecialchars($c) ?></option>
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

    <!-- ── Grid de cards ── -->
    <div class="cp-grid" id="cpGrid">
    <?php foreach ($grupos as $g):
        $cor     = corSit($g['situacao'], $CORES);
        $ini     = fmtD($g['inicio']);
        $fim     = fmtD($g['fim']);
        $dias    = $g['fim'] ? diasR($g['fim']) : null;
        $nPain   = count($g['rows']);
        $buscaStr = strtolower(
            $g['cliente'] . ' ' . $g['agencia'] . ' ' . $g['nome'] . ' ' . $g['situacao']
            . ' ' . implode(' ', array_column($g['rows'], 'numero'))
            . ' ' . implode(' ', array_column($g['rows'], 'logradouro'))
            . ' ' . implode(' ', array_column($g['rows'], 'cidade'))
        );
    ?>
    <div class="cp-card <?= !$g['ativo'] ? 'encerrada' : '' ?>"
         data-busca="<?= htmlspecialchars($buscaStr) ?>"
         data-situacao="<?= htmlspecialchars($g['situacao']) ?>"
         data-status="<?= $g['ativo'] ?>"
         data-cliente="<?= htmlspecialchars(strtolower($g['cliente'])) ?>">

        <div class="cp-card-faixa" style="background:<?= $cor ?>"></div>

        <div class="cp-card-head">
            <div class="cp-card-top">
                <span class="sit-badge" style="background:<?= $cor ?>"><?= htmlspecialchars($g['situacao']) ?></span>
                <span class="cp-card-nome"><?= htmlspecialchars($g['nome'] !== '—' ? $g['nome'] : 'Sem nome') ?></span>
            </div>
            <div class="cp-card-cliente"><?= htmlspecialchars($g['cliente']) ?></div>
            <?php if ($g['agencia']): ?><div class="cp-card-agencia"><?= htmlspecialchars($g['agencia']) ?></div><?php endif; ?>
            <div class="cp-card-meta">
                <?php if ($ini || $fim): ?>
                <span class="cp-card-periodo">
                    <?= $ini ?? '?' ?> → <?= $fim ?? '?' ?>
                </span>
                <?php endif; ?>
                <?php if ($g['ativo'] && $dias !== null && $dias >= 0 && $dias <= 30):
                    $cls = $dias <= 7 ? 'prazo-urg' : 'prazo-ale'; ?>
                <span class="<?= $cls ?>"><?= $dias ?>d</span>
                <?php endif; ?>
                <?php if ($g['ativo']): ?>
                <span class="status-ativa">Ativa</span>
                <?php else: ?>
                <span class="status-encerrada">Encerrada</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="cp-card-paineis">
            <?php foreach ($g['rows'] as $r): ?>
            <div class="cp-painel-row">
                <span class="cp-painel-num"><?= str_pad($r['numero'], 3, '0', STR_PAD_LEFT) ?></span>
                <div class="cp-painel-end">
                    <div class="cp-painel-log" title="<?= htmlspecialchars($r['logradouro']) ?>"><?= htmlspecialchars($r['logradouro']) ?></div>
                    <div class="cp-painel-cid"><?= htmlspecialchars(implode(' · ', array_filter([$r['cidade'] ?? '', $r['regiao'] ?? '']))) ?></div>
                </div>
                <a href="/gestor/pontos/detalhes?id=<?= $r['ponto_id'] ?>" class="cp-painel-link" title="Ver ponto">→</a>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="cp-card-footer">
            <?= $nPain ?> painel<?= $nPain > 1 ? 'is' : '' ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <div class="cp-empty" id="cpEmpty" style="display:none">
        Nenhuma campanha encontrada para os filtros aplicados.
    </div>

</div>

<script>
var filtros = { busca:'', cliente:'', situacao:'', status:'' };

function filtrar() {
    var temFiltro = filtros.busca || filtros.cliente || filtros.situacao || filtros.status !== '';
    document.getElementById('cpLimpar').className = 'cp-limpar' + (temFiltro ? ' vis' : '');

    var total = 0;
    document.querySelectorAll('#cpGrid .cp-card').forEach(function(card) {
        var ok = true;
        if (filtros.busca    && card.dataset.busca.indexOf(filtros.busca)       === -1) ok = false;
        if (filtros.cliente  && card.dataset.cliente !== filtros.cliente)               ok = false;
        if (filtros.situacao && card.dataset.situacao !== filtros.situacao)             ok = false;
        if (filtros.status !== '' && card.dataset.status !== filtros.status)            ok = false;
        card.style.display = ok ? '' : 'none';
        if (ok) total++;
    });

    document.getElementById('cpContador').textContent = total + ' campanha' + (total !== 1 ? 's' : '');
    document.getElementById('cpEmpty').style.display  = total === 0 ? 'block' : 'none';
}

var debTimer;
document.getElementById('cpBusca').addEventListener('input', function() {
    clearTimeout(debTimer);
    var val = this.value.toLowerCase().trim();
    debTimer = setTimeout(function() { filtros.busca = val; filtrar(); }, 150);
});
document.getElementById('cpFiltroCliente').addEventListener('change', function() {
    filtros.cliente = this.value;
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

filtrar();
</script>

</body>
</html>
