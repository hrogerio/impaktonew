<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

$paginaAtual = 'auditoria';

try {
    require_once __DIR__ . '/../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("Erro na conexão com o banco de dados.");
}

// ── Totais gerais ─────────────────────────────────────────────
$totalAtivos = (int)$pdo->query("SELECT COUNT(*) FROM pontos WHERE ativo=1 OR ativo IS NULL")->fetchColumn();
$totalInativos = (int)$pdo->query("SELECT COUNT(*) FROM pontos WHERE ativo=0")->fetchColumn();

// ── Sem foto ──────────────────────────────────────────────────
$semFoto = $pdo->query("
    SELECT p.id, p.numero, p.logradouro, p.cidade, p.situacao
    FROM pontos p
    WHERE (p.ativo=1 OR p.ativo IS NULL)
      AND (p.foto IS NULL OR p.foto = '')
      AND NOT EXISTS (SELECT 1 FROM ponto_fotos pf WHERE pf.ponto_id = p.id)
    ORDER BY p.numero ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ── Sem coordenadas ───────────────────────────────────────────
$semCoord = $pdo->query("
    SELECT id, numero, logradouro, cidade, situacao
    FROM pontos
    WHERE (ativo=1 OR ativo IS NULL)
      AND (latitude IS NULL OR latitude=0 OR longitude IS NULL OR longitude=0)
    ORDER BY numero ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ── Sem cliente (nem pontos nem campanha ativa têm cliente) ──
$semCliente = $pdo->query("
    SELECT p.id, p.numero, p.logradouro, p.cidade, p.situacao,
           COALESCE(CASE WHEN CAST(c.fim AS CHAR) NOT IN ('0000-00-00','') THEN c.fim ELSE NULL END, CASE WHEN CAST(p.fim_contrato AS CHAR) NOT IN ('0000-00-00','') THEN p.fim_contrato ELSE NULL END) AS fim_contrato
    FROM pontos p
    LEFT JOIN campanhas c ON c.ponto_id = p.id AND c.ativo = 1
    WHERE (p.ativo=1 OR p.ativo IS NULL)
      AND NULLIF(TRIM(c.cliente),'') IS NULL
    GROUP BY p.id
    ORDER BY p.numero ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ── Contratos vencidos sem atualização de situação ────────────
$vencidosSemAtualizar = $pdo->query("
    SELECT p.id, p.numero, p.logradouro, p.cidade, p.situacao,
           c.cliente AS cliente,
           COALESCE(CASE WHEN CAST(c.fim AS CHAR) NOT IN ('0000-00-00','') THEN c.fim ELSE NULL END, CASE WHEN CAST(p.fim_contrato AS CHAR) NOT IN ('0000-00-00','') THEN p.fim_contrato ELSE NULL END) AS fim_contrato,
           DATEDIFF(CURDATE(), COALESCE(CASE WHEN CAST(c.fim AS CHAR) NOT IN ('0000-00-00','') THEN c.fim ELSE NULL END, CASE WHEN CAST(p.fim_contrato AS CHAR) NOT IN ('0000-00-00','') THEN p.fim_contrato ELSE NULL END)) AS dias_vencido
    FROM pontos p
    LEFT JOIN campanhas c ON c.ponto_id = p.id AND c.ativo = 1 AND c.situacao IN ('Ocupado','Vencido')
    WHERE (p.ativo=1 OR p.ativo IS NULL)
      AND COALESCE(CASE WHEN CAST(c.fim AS CHAR) NOT IN ('0000-00-00','') THEN c.fim ELSE NULL END, CASE WHEN CAST(p.fim_contrato AS CHAR) NOT IN ('0000-00-00','') THEN p.fim_contrato ELSE NULL END) IS NOT NULL
      AND COALESCE(CASE WHEN CAST(c.fim AS CHAR) NOT IN ('0000-00-00','') THEN c.fim ELSE NULL END, CASE WHEN CAST(p.fim_contrato AS CHAR) NOT IN ('0000-00-00','') THEN p.fim_contrato ELSE NULL END) < CURDATE()
      AND LOWER(p.situacao) NOT IN ('disponivel','disponível','vencido')
    ORDER BY fim_contrato ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ── Dados incompletos (sem logradouro ou cidade) ──────────────
$incompletos = $pdo->query("
    SELECT id, numero, logradouro, cidade, situacao
    FROM pontos
    WHERE (ativo=1 OR ativo IS NULL)
      AND (logradouro IS NULL OR TRIM(logradouro)=''
           OR cidade IS NULL OR TRIM(cidade)='' OR TRIM(cidade)='-')
    ORDER BY numero ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ── Score de completude ────────────────────────────────────────
$camposVerificados = 5; // foto, coord, cliente, contrato, logradouro/cidade
$problemas = [
    count($semFoto),
    count($semCoord),
    count($semCliente),
    count($vencidosSemAtualizar),
    count($incompletos),
];
$totalProblemas = array_sum($problemas);
$scoreBase = $totalAtivos > 0
    ? max(0, round(100 - ($totalProblemas / ($totalAtivos * $camposVerificados) * 100)))
    : 100;

function fmtData($d) {
    if (!$d || $d === '0000-00-00') return '-';
    try { return (new DateTime($d))->format('d/m/Y'); } catch(Exception $e) { return '-'; }
}
function badgeSit($s) {
    $cores = ['Disponivel'=>'#1a9059','Disponível'=>'#1a9059','Ocupado'=>'#dc3545','Reservado'=>'#fd7e14','Vencido'=>'#6c757d','Permuta'=>'#51086e','Bisemana'=>'#0284c7'];
    $cor = $cores[$s] ?? '#888';
    return '<span style="background:'.$cor.';color:white;font-size:0.65rem;font-weight:800;padding:1px 7px;border-radius:10px;text-transform:uppercase">'
           .htmlspecialchars($s ?: '-').'</span>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoria · SGI</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
    <link rel="stylesheet" href="/public/assets/css/relatorios.css">
    <style>
        .audit-score-wrap { display:flex; align-items:center; gap:1.5rem; margin-bottom:1.5rem; flex-wrap:wrap; }
        .audit-score-circle { position:relative; width:90px; height:90px; flex-shrink:0; }
        .audit-score-circle svg { transform:rotate(-90deg); }
        .audit-score-num { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:1.4rem; font-weight:800; color:var(--color-text-dark); }
        .audit-score-label { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--color-text-muted); margin-top:0.2rem; text-align:center; }
        .audit-score-info { flex:1; }
        .audit-score-title { font-size:1.1rem; font-weight:800; color:var(--color-text-dark); margin-bottom:0.25rem; }
        .audit-score-sub { font-size:0.85rem; color:var(--color-text-muted); }

        .audit-section { margin-bottom:1.5rem; }
        .audit-section-header {
            display:flex; align-items:center; justify-content:space-between;
            padding:0.65rem 1rem; border-radius:8px; cursor:pointer;
            user-select:none; transition:background 0.12s;
        }
        .audit-section-header:hover { background:#f9fafb; }
        .audit-section-header .audit-section-title { display:flex; align-items:center; gap:0.5rem; font-size:0.9rem; font-weight:800; }
        .audit-section-badge { font-size:0.75rem; font-weight:800; padding:2px 8px; border-radius:10px; }
        .badge-ok   { background:#dcfce7; color:#166534; }
        .badge-warn { background:#fef3c7; color:#92400e; }
        .badge-danger { background:#fee2e2; color:#991b1b; }
        .audit-section-toggle { font-size:0.75rem; color:var(--color-text-muted); transition:transform 0.2s; }
        .audit-section-toggle.fechado { transform:rotate(-90deg); }
        .audit-section-body { padding:0 0.5rem 0.5rem; }

        .audit-table { width:100%; border-collapse:collapse; font-size:0.82rem; }
        .audit-table th { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:var(--color-text-muted); padding:0.4rem 0.75rem; border-bottom:2px solid var(--color-border); text-align:left; }
        .audit-table td { padding:0.5rem 0.75rem; border-bottom:1px solid #f5f5f5; vertical-align:middle; }
        .audit-table tr:last-child td { border-bottom:none; }
        .audit-table tr:hover td { background:#fafafa; }
        .audit-num { font-weight:800; color:var(--color-accent-primary); }
        .audit-link { font-size:0.75rem; font-weight:700; color:var(--color-accent-primary); text-decoration:none; white-space:nowrap; }
        .audit-link:hover { text-decoration:underline; }
        .audit-empty { padding:1rem; text-align:center; color:var(--color-text-muted); font-size:0.85rem; }

        .audit-resumo-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:0.75rem; margin-bottom:1.5rem; }
        .audit-resumo-card { background:white; border:1.5px solid var(--color-border); border-radius:10px; padding:0.9rem 1rem; }
        .audit-resumo-num { font-size:1.6rem; font-weight:800; }
        .audit-resumo-label { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:var(--color-text-muted); margin-top:0.15rem; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="container" style="padding-top:0.75rem;padding-bottom:2rem;">

    <div class="welcome" style="margin-bottom:1rem;">
        <h2>📊 Auditoria de Dados</h2>
        <p>Qualidade e completude do cadastro de pontos — identifique e corrija pendências.</p>
    </div>

    <!-- ── Score de completude ─────────────────────────────── -->
    <?php
    $scoreColor = $scoreBase >= 80 ? '#1a9059' : ($scoreBase >= 60 ? '#f59e0b' : '#dc3545');
    $circunf = 2 * M_PI * 36;
    $dash = ($scoreBase / 100) * $circunf;
    ?>
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="audit-score-wrap">
            <div>
                <div class="audit-score-circle">
                    <svg width="90" height="90" viewBox="0 0 90 90">
                        <circle cx="45" cy="45" r="36" fill="none" stroke="#f0f0f0" stroke-width="10"/>
                        <circle cx="45" cy="45" r="36" fill="none"
                                stroke="<?= $scoreColor ?>" stroke-width="10"
                                stroke-dasharray="<?= round($dash,1) ?> <?= round($circunf,1) ?>"
                                stroke-linecap="round"/>
                    </svg>
                    <div class="audit-score-num" style="color:<?= $scoreColor ?>"><?= $scoreBase ?>%</div>
                </div>
                <div class="audit-score-label">Completude</div>
            </div>
            <div class="audit-score-info">
                <div class="audit-score-title">
                    <?php if ($scoreBase >= 80): ?>✅ Cadastro em boa forma
                    <?php elseif ($scoreBase >= 60): ?>⚠️ Cadastro com pendências
                    <?php else: ?>🔴 Cadastro precisa de atenção
                    <?php endif; ?>
                </div>
                <div class="audit-score-sub">
                    <?= $totalAtivos ?> pontos ativos · <?= $totalProblemas ?> pendência(s) identificada(s)
                    <?php if ($totalInativos > 0): ?> · <span style="color:var(--color-text-muted)"><?= $totalInativos ?> inativos</span><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Resumo rápido -->
        <div class="audit-resumo-grid">
            <div class="audit-resumo-card" style="border-color:<?= count($semFoto)>0?'#fde047':'var(--color-border)' ?>">
                <div class="audit-resumo-num" style="color:<?= count($semFoto)>0?'#92400e':'#1a9059' ?>"><?= count($semFoto) ?></div>
                <div class="audit-resumo-label">Sem foto</div>
            </div>
            <div class="audit-resumo-card" style="border-color:<?= count($semCoord)>0?'#fde047':'var(--color-border)' ?>">
                <div class="audit-resumo-num" style="color:<?= count($semCoord)>0?'#92400e':'#1a9059' ?>"><?= count($semCoord) ?></div>
                <div class="audit-resumo-label">Sem coordenadas</div>
            </div>
            <div class="audit-resumo-card" style="border-color:<?= count($semCliente)>0?'#fde047':'var(--color-border)' ?>">
                <div class="audit-resumo-num" style="color:<?= count($semCliente)>0?'#92400e':'#1a9059' ?>"><?= count($semCliente) ?></div>
                <div class="audit-resumo-label">Sem cliente</div>
            </div>
            <div class="audit-resumo-card" style="border-color:<?= count($vencidosSemAtualizar)>0?'#fca5a5':'var(--color-border)' ?>">
                <div class="audit-resumo-num" style="color:<?= count($vencidosSemAtualizar)>0?'#991b1b':'#1a9059' ?>"><?= count($vencidosSemAtualizar) ?></div>
                <div class="audit-resumo-label">Ctr. vencido s/ atualização</div>
            </div>
            <div class="audit-resumo-card" style="border-color:<?= count($incompletos)>0?'#fca5a5':'var(--color-border)' ?>">
                <div class="audit-resumo-num" style="color:<?= count($incompletos)>0?'#991b1b':'#1a9059' ?>"><?= count($incompletos) ?></div>
                <div class="audit-resumo-label">Dados incompletos</div>
            </div>
        </div>
    </div>

    <!-- ── Seções expansíveis ──────────────────────────────── -->

    <?php
    $secoes = [
        ['id'=>'semfoto',   'icone'=>'📷', 'titulo'=>'Pontos sem foto',             'dados'=>$semFoto,              'cor'=>count($semFoto)>0?'warn':'ok'],
        ['id'=>'semcoord',  'icone'=>'📍', 'titulo'=>'Pontos sem coordenadas',       'dados'=>$semCoord,             'cor'=>count($semCoord)>0?'warn':'ok'],
        ['id'=>'semcli',    'icone'=>'👤', 'titulo'=>'Pontos sem cliente cadastrado', 'dados'=>$semCliente,           'cor'=>count($semCliente)>0?'warn':'ok'],
        ['id'=>'vencidos',  'icone'=>'⏰',
         'titulo'=>'Contratos vencidos sem atualização de situação'
             . (count($vencidosSemAtualizar) > 0
                 ? ' <button onclick="event.stopPropagation();processarVencidos()" id="btnProcessarTodos"
                       style="margin-left:0.75rem;background:#1a9059;color:white;border:none;border-radius:6px;padding:3px 10px;font-size:0.72rem;font-weight:700;cursor:pointer;font-family:inherit;">
                       ⚡ Liberar todos (' . count($vencidosSemAtualizar) . ')
                   </button>'
                 : ''),
         'dados'=>$vencidosSemAtualizar, 'cor'=>count($vencidosSemAtualizar)>0?'danger':'ok'],
        ['id'=>'incompl',   'icone'=>'📝', 'titulo'=>'Dados incompletos (logradouro / cidade)', 'dados'=>$incompletos, 'cor'=>count($incompletos)>0?'danger':'ok'],
    ];
    ?>

    <?php foreach ($secoes as $s): ?>
    <div class="card audit-section" style="padding:0;">
        <div class="audit-section-header" onclick="toggleSecao('<?= $s['id'] ?>')">
            <div class="audit-section-title">
                <?= $s['icone'] ?> <?= $s['titulo'] ?>
                <span class="audit-section-badge badge-<?= $s['cor'] ?>">
                    <?= count($s['dados']) === 0 ? '✓ OK' : count($s['dados']).' pendência'.(count($s['dados'])>1?'s':'') ?>
                </span>
            </div>
            <span class="audit-section-toggle<?= count($s['dados'])===0?' fechado':'' ?>" id="toggle-<?= $s['id'] ?>">▼</span>
        </div>

        <div class="audit-section-body" id="body-<?= $s['id'] ?>"
             style="<?= count($s['dados'])===0?'display:none':'' ?>">
            <?php if (empty($s['dados'])): ?>
                <div class="audit-empty">✅ Nenhuma pendência encontrada.</div>
            <?php else: ?>
            <div style="overflow-x:auto;">
            <table class="audit-table">
                <thead>
                    <tr>
                        <th>Nº</th>
                        <th>Logradouro</th>
                        <th>Cidade</th>
                        <th>Situação</th>
                        <?php if ($s['id']==='vencidos'): ?>
                        <th>Cliente</th>
                        <th>Venceu em</th>
                        <th>Dias</th>
                        <th></th>
                        <?php endif; ?>
                        <?php if ($s['id']==='semcli'): ?>
                        <th>Vencimento</th>
                        <?php endif; ?>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($s['dados'] as $p): ?>
                    <tr>
                        <td class="audit-num">#<?= htmlspecialchars($p['numero']) ?></td>
                        <td><?= htmlspecialchars($p['logradouro'] ?? '-') ?></td>
                        <td style="color:var(--color-text-muted)"><?= htmlspecialchars($p['cidade'] ?? '-') ?></td>
                        <td><?= badgeSit($p['situacao'] ?? '') ?></td>
                        <?php if ($s['id']==='vencidos'): ?>
                        <td><?= htmlspecialchars($p['cliente'] ?? '-') ?></td>
                        <td><?= fmtData($p['fim_contrato']) ?></td>
                        <td><span style="font-weight:700;color:#991b1b"><?= $p['dias_vencido'] ?>d</span></td>
                        <td>
                            <button class="audit-link"
                                    style="background:#fee2e2;border:1px solid #fca5a5;border-radius:5px;padding:2px 8px;color:#991b1b;cursor:pointer;font-size:0.72rem;font-weight:700;font-family:inherit;"
                                    onclick="liberarPonto(<?= (int)$p['id'] ?>, '<?= addslashes($p['numero']) ?>', this)">
                                🔓 Liberar
                            </button>
                        </td>
                        <?php endif; ?>
                        <?php if ($s['id']==='semcli'): ?>
                        <td style="font-size:0.78rem;color:var(--color-text-muted)"><?= fmtData($p['fim_contrato'] ?? '') ?></td>
                        <?php endif; ?>
                        <td>
                            <a href="/gestor/pontos/editar?id=<?= (int)$p['id'] ?><?= $s['id']==='semfoto'?'&aba=fotos':'' ?>"
                               class="audit-link">✏️ Editar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

</div>

<script>
function toggleSecao(id) {
    var body   = document.getElementById('body-' + id);
    var toggle = document.getElementById('toggle-' + id);
    var aberto = body.style.display !== 'none';
    body.style.display   = aberto ? 'none' : 'block';
    toggle.classList.toggle('fechado', aberto);
}

// ── Liberar um ponto individualmente ─────────────────────────
function liberarPonto(pontoId, numero, btn) {
    if (!confirm('Encerrar contrato do ponto ' + numero + ' e marcá-lo como Disponível?')) return;
    btn.disabled = true;
    btn.textContent = '⏳...';
    fetch('/gestor/campanhas/encerrar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ponto_id: pontoId })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) {
            var tr = btn.closest('tr');
            if (tr) {
                tr.style.transition = 'opacity 0.4s';
                tr.style.opacity = '0';
                setTimeout(function() { tr.remove(); }, 400);
            }
            mostrarToastAudit('✅ Ponto ' + numero + ' liberado com sucesso!', 'ok');
        } else {
            btn.disabled = false;
            btn.textContent = '🔓 Liberar';
            mostrarToastAudit('❌ Erro ao liberar ponto ' + numero, 'err');
        }
    })
    .catch(function() {
        btn.disabled = false;
        btn.textContent = '🔓 Liberar';
        mostrarToastAudit('❌ Erro de comunicação', 'err');
    });
}

// ── Processar todos os vencidos de uma vez ────────────────────
function processarVencidos() {
    var btn = document.getElementById('btnProcessarTodos');
    if (!confirm('Encerrar TODOS os contratos vencidos e marcar os pontos como Disponível?\n\nEsta ação não pode ser desfeita.')) return;
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Processando...'; }
    fetch('/gestor/campanhas/processar-vencidos', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: '{}'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) {
            mostrarToastAudit('✅ ' + data.processados + ' ponto(s) liberado(s)! Recarregando...', 'ok');
            setTimeout(function() { location.reload(); }, 1800);
        } else {
            if (btn) { btn.disabled = false; btn.textContent = '⚡ Liberar todos'; }
            mostrarToastAudit('❌ Erro: ' + (data.msg || 'desconhecido'), 'err');
        }
    })
    .catch(function() {
        if (btn) { btn.disabled = false; btn.textContent = '⚡ Liberar todos'; }
        mostrarToastAudit('❌ Erro de comunicação', 'err');
    });
}

function mostrarToastAudit(msg, tipo) {
    var t = document.getElementById('auditToast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'auditToast';
        t.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;'
            + 'padding:0.75rem 1.25rem;border-radius:8px;font-size:0.83rem;font-weight:700;'
            + 'box-shadow:0 4px 16px rgba(0,0,0,0.2);transition:all 0.3s ease;'
            + 'transform:translateY(80px);opacity:0;';
        document.body.appendChild(t);
    }
    t.textContent = msg;
    t.style.background = tipo === 'ok' ? '#1a9059' : '#dc3545';
    t.style.color = 'white';
    t.style.transform = 'translateY(0)';
    t.style.opacity = '1';
    clearTimeout(t._tmr);
    t._tmr = setTimeout(function() {
        t.style.transform = 'translateY(80px)';
        t.style.opacity = '0';
    }, 3500);
}
</script>

</body>
</html>
