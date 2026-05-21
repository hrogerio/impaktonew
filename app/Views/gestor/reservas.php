<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}
$paginaAtual = 'reservas';

try {
    require_once __DIR__ . '/../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("Erro na conexão com o banco de dados.");
}

// Busca todas as pré-seleções, mais recentes primeiro
$lista = $pdo->query("
    SELECT id, cliente, agencia, periodo_ini, periodo_fim, sem_periodo,
           total_pontos, criado_em, criado_por
    FROM pre_selecoes
    ORDER BY criado_em DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservas — Impakto Mídia</title>
    <link rel="icon" href="/public/assets/img/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
    <style>
        .prop-page { max-width:960px; margin:0 auto; padding:1.5rem 1.5rem 3rem; }

        .prop-titulo {
            display:flex; align-items:center; justify-content:space-between;
            margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;
        }
        .prop-titulo h1 { font-size:1.3rem; font-weight:800; color:var(--color-text-dark); margin:0; }

        .btn-nova {
            display:inline-flex; align-items:center; gap:0.4rem;
            padding:0.55rem 1.1rem; background:var(--color-accent-primary); color:white;
            border-radius:8px; font-size:0.82rem; font-weight:700; text-decoration:none;
            transition:opacity 0.15s;
        }
        .btn-nova:hover { opacity:0.88; }

        /* Busca */
        .prop-busca-wrap {
            margin-bottom:1rem;
            position:relative;
        }
        .prop-busca-icon { position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); font-size:0.9rem; color:#aaa; }
        .prop-busca {
            width:100%; box-sizing:border-box;
            padding:0.55rem 0.75rem 0.55rem 2.2rem;
            border:1px solid var(--color-border); border-radius:8px;
            font-family:'Montserrat',sans-serif; font-size:0.83rem;
        }
        .prop-busca:focus { outline:none; border-color:var(--color-accent-primary); }

        /* Tabela */
        .prop-card { background:white; border:1px solid var(--color-border); border-radius:10px; overflow:hidden; }
        .prop-table { width:100%; border-collapse:collapse; font-size:0.83rem; }
        .prop-table th {
            background:var(--color-bg-primary); padding:0.5rem 0.85rem;
            font-size:0.68rem; font-weight:700; color:var(--color-text-muted);
            text-transform:uppercase; letter-spacing:0.4px;
            border-bottom:1.5px solid var(--color-border); text-align:left;
        }
        .prop-table td { padding:0.65rem 0.85rem; border-bottom:1px solid var(--color-border); vertical-align:middle; }
        .prop-table tbody tr:last-child td { border-bottom:none; }
        .prop-table tbody tr:hover { background:#fafafa; }

        .prop-id    { color:var(--color-text-muted); font-size:0.75rem; font-weight:600; }
        .prop-cli   { font-weight:700; color:var(--color-text-dark); }
        .prop-ag    { font-size:0.72rem; color:var(--color-text-muted); margin-top:2px; }
        .prop-per   { font-size:0.78rem; color:var(--color-text-muted); white-space:nowrap; }
        .prop-num   { font-weight:700; color:var(--color-accent-primary); text-align:center; }
        .prop-data  { font-size:0.75rem; color:var(--color-text-muted); white-space:nowrap; }
        .prop-por   { font-size:0.72rem; color:var(--color-text-muted); }

        .prop-acoes { display:flex; gap:0.4rem; align-items:center; }
        .btn-reabrir {
            display:inline-flex; align-items:center; gap:0.3rem;
            padding:0.3rem 0.75rem; background:#fff3f3;
            border:1px solid #fca5a5; border-radius:6px;
            font-size:0.75rem; font-weight:700; color:var(--color-accent-primary);
            cursor:pointer; transition:all 0.15s; white-space:nowrap;
            font-family:'Montserrat',sans-serif;
        }
        .btn-reabrir:hover { background:var(--color-accent-primary); color:white; border-color:var(--color-accent-primary); }
        .btn-ver {
            display:inline-flex; align-items:center; gap:0.3rem;
            padding:0.3rem 0.75rem; background:#f8f9fa;
            border:1px solid var(--color-border); border-radius:6px;
            font-size:0.75rem; font-weight:700; color:var(--color-text-muted);
            cursor:pointer; transition:all 0.15s; white-space:nowrap;
            text-decoration:none;
        }
        .btn-ver:hover { background:#f3f4f6; color:var(--color-text-dark); }
        .btn-excluir {
            display:inline-flex; align-items:center; gap:0.3rem;
            padding:0.3rem 0.6rem; background:#fff; border:1px solid #fca5a5;
            border-radius:6px; font-size:0.75rem; font-weight:700; color:#c0392b;
            cursor:pointer; transition:all 0.15s; white-space:nowrap;
            font-family:'Montserrat',sans-serif;
        }
        .btn-excluir:hover { background:#c0392b; color:white; border-color:#c0392b; }

        .prop-empty { padding:3rem 1rem; text-align:center; color:var(--color-text-muted); }
        .prop-empty .icon { font-size:2.5rem; margin-bottom:0.75rem; }
        .prop-empty p { font-size:0.9rem; margin-bottom:1rem; }

        /* Toast */
        .toast {
            position:fixed; bottom:1.5rem; right:1.5rem; z-index:9999;
            background:#1a9059; color:white; padding:0.75rem 1.25rem;
            border-radius:8px; font-size:0.83rem; font-weight:700;
            box-shadow:0 4px 16px rgba(0,0,0,0.2);
            transform:translateY(80px); opacity:0;
            transition:all 0.3s ease;
        }
        .toast.show { transform:translateY(0); opacity:1; }

        /* Linha oculta na busca */
        .prop-row-hidden { display:none; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="prop-page">

    <div class="prop-titulo">
        <h1>📋 Reservas</h1>
        <a href="/gestor/pre-selecao" class="btn-nova">+ Nova Pré-Seleção</a>
    </div>

    <div class="prop-busca-wrap">
        <span class="prop-busca-icon">🔍</span>
        <input type="text" class="prop-busca" id="propBusca" placeholder="Buscar por cliente, agência..." autocomplete="off">
    </div>

    <div class="prop-card">
        <?php if (empty($lista)): ?>
        <div class="prop-empty">
            <div class="icon">📋</div>
            <p>Nenhuma reserva registrada ainda.</p>
            <a href="/gestor/pre-selecao" class="btn-nova">+ Criar primeira pré-seleção</a>
        </div>
        <?php else: ?>
        <table class="prop-table" id="propTable">
            <thead>
                <tr>
                    <th style="width:40px">#</th>
                    <th>Cliente / Agência</th>
                    <th style="width:160px">Período</th>
                    <th style="width:60px;text-align:center">Pontos</th>
                    <th style="width:120px">Data</th>
                    <th style="width:140px"></th>
                </tr>
            </thead>
            <tbody id="propTbody">
            <?php foreach ($lista as $ps):
                $periodo = '';
                if ($ps['sem_periodo']) {
                    $periodo = 'Sem período';
                } elseif ($ps['periodo_ini'] && $ps['periodo_fim']) {
                    $ini = date('d/m/Y', strtotime($ps['periodo_ini']));
                    $fim = date('d/m/Y', strtotime($ps['periodo_fim']));
                    $periodo = $ini . ' – ' . $fim;
                } elseif ($ps['periodo_ini']) {
                    $periodo = 'A partir de ' . date('d/m/Y', strtotime($ps['periodo_ini']));
                }
                $dataFmt = date('d/m/Y H:i', strtotime($ps['criado_em']));
                $buscarStr = strtolower($ps['cliente'] . ' ' . $ps['agencia']);
            ?>
            <tr class="prop-row" data-busca="<?= htmlspecialchars($buscarStr) ?>">
                <td class="prop-id"><?= $ps['id'] ?></td>
                <td>
                    <div class="prop-cli"><?= htmlspecialchars($ps['cliente']) ?></div>
                    <?php if ($ps['agencia']): ?>
                    <div class="prop-ag"><?= htmlspecialchars($ps['agencia']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="prop-per"><?= htmlspecialchars($periodo ?: '—') ?></td>
                <td class="prop-num"><?= $ps['total_pontos'] ?></td>
                <td>
                    <div class="prop-data"><?= $dataFmt ?></div>
                    <?php if ($ps['criado_por']): ?>
                    <div class="prop-por"><?= htmlspecialchars($ps['criado_por']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="prop-acoes">
                        <a href="/gestor/reservas/ver?id=<?= $ps['id'] ?>" class="btn-ver">👁 Ver</a>
                        <button class="btn-reabrir" onclick="reabrir(<?= $ps['id'] ?>)">↩ Reabrir</button>
                        <button class="btn-excluir" onclick="excluir(<?= $ps['id'] ?>, this)" title="Excluir reserva">🗑</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>

<div class="toast" id="toast"></div>

<script>
// ── Busca ─────────────────────────────────────────────────────
var debTimer;
document.getElementById('propBusca').addEventListener('input', function() {
    clearTimeout(debTimer);
    var val = this.value;
    debTimer = setTimeout(function() { filtrarTabela(val); }, 150);
});
function filtrarTabela(busca) {
    var termo = busca.toLowerCase().trim();
    document.querySelectorAll('.prop-row').forEach(function(tr) {
        var texto = tr.dataset.busca || '';
        tr.style.display = (!termo || texto.indexOf(termo) !== -1) ? '' : 'none';
    });
}

// ── Reabrir ───────────────────────────────────────────────────
function reabrir(id) {
    fetch('/gestor/pre-selecao/dados?id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.erro) { alert('Erro ao carregar a reserva.'); return; }
            // Salva os IDs no cart (localStorage)
            localStorage.setItem('impakto_cart', JSON.stringify(data.pontos_ids));
            mostrarToast('✅ ' + data.pontos_ids.length + ' ponto(s) carregado(s)! Redirecionando...');
            setTimeout(function() {
                window.location.href = '/gestor/pre-selecao';
            }, 1200);
        })
        .catch(function() { alert('Erro de comunicação.'); });
}

// ── Excluir ───────────────────────────────────────────────────
function excluir(id, btn) {
    if (!confirm('Excluir esta reserva? Esta ação não pode ser desfeita.')) return;
    btn.disabled = true;
    btn.textContent = '...';
    fetch('/gestor/reservas/excluir', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) {
            var tr = btn.closest('tr');
            tr.style.transition = 'opacity 0.3s';
            tr.style.opacity = '0';
            setTimeout(function() { tr.remove(); }, 300);
            mostrarToast('🗑 Reserva excluída.');
        } else {
            btn.disabled = false;
            btn.textContent = '🗑';
            alert('Erro ao excluir.');
        }
    })
    .catch(function() {
        btn.disabled = false;
        btn.textContent = '🗑';
        alert('Erro de comunicação.');
    });
}

// ── Toast ─────────────────────────────────────────────────────
function mostrarToast(msg) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(function() { t.classList.remove('show'); }, 3000);
}
</script>

</body>
</html>
