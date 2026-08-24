<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

$paginaAtual = 'relatorios';

require_once __DIR__ . '/../../../config/database.php';
$pdo = getDatabase();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: /gestor/agencias");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM agencias WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$agencia = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$agencia) {
    header("Location: /gestor/agencias");
    exit;
}

$stmtC = $pdo->prepare("SELECT nome, email, tipo FROM agencia_contatos WHERE agencia_id = ? ORDER BY ordem ASC, id ASC");
$stmtC->execute([$id]);
$diretoria = [];
$midia = [];
foreach ($stmtC->fetchAll(PDO::FETCH_ASSOC) as $c) {
    if ($c['tipo'] === 'diretoria') $diretoria[] = $c;
    else $midia[] = $c;
}

$temCampanhaAtiva = (bool)$pdo->query("SELECT 1 FROM campanhas WHERE agencia_id = " . (int)$id . " AND ativo = 1 LIMIT 1")->fetchColumn();

require_once __DIR__ . '/campanhas/_helpers.php';

// ── Clientes que essa agência já atendeu (via campanhas.agencia_id) ──
$stmtClientes = $pdo->prepare("
    SELECT
        c.cliente_id,
        COALESCE(cl.razao_social, NULLIF(TRIM(c.cliente), '')) AS nome_cliente,
        cl.logo,
        COUNT(*) AS total_campanhas,
        SUM(c.ativo) AS campanhas_ativas
    FROM campanhas c
    LEFT JOIN clientes cl ON cl.id = c.cliente_id
    WHERE c.agencia_id = ?
    GROUP BY c.cliente_id, nome_cliente, cl.logo
    HAVING nome_cliente IS NOT NULL
    ORDER BY campanhas_ativas DESC, total_campanhas DESC
");
$stmtClientes->execute([$id]);
$clientesAgencia = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);

// ── Histórico: todas as campanhas (ativas e encerradas) já feitas por essa agência ──
$hoje = date('Y-m-d');
$stmtHist = $pdo->prepare("
    SELECT c.id, c.ponto_id, c.campanha, c.nome AS nome_projeto, c.situacao,
           c.inicio, c.fim, c.ativo, c.criado_em, c.cliente,
           p.numero, p.logradouro, p.cidade, p.regiao,
           cl.razao_social AS cliente_cadastro
    FROM campanhas c
    JOIN pontos p ON p.id = c.ponto_id AND (p.ativo = 1 OR p.ativo IS NULL)
    LEFT JOIN clientes cl ON cl.id = c.cliente_id
    WHERE c.agencia_id = ?
    ORDER BY c.ativo DESC, COALESCE(c.inicio, c.criado_em) DESC, c.criado_em DESC
");
$stmtHist->execute([$id]);
$historicoRows = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

$CORES_SIT = ['Ocupado' => '#dc3545', 'Reservado' => '#fd7e14', 'Permuta' => '#51086e', 'Bisemana' => '#0284c7', 'Vencido' => '#6c757d'];

$historico = [];
foreach ($historicoRows as $r) {
    $camp = trim($r['campanha'] ?? '') ?: '—';
    $nomeProjeto = trim($r['nome_projeto'] ?? '');
    $cliTexto = trim($r['cliente'] ?? '') ?: '— Sem cliente —';
    $key = md5($cliTexto . '|' . $camp . '|' . $nomeProjeto . '|' . $r['situacao'] . '|' . ($r['inicio'] ?? '') . '|' . ($r['fim'] ?? '') . '|' . $r['ativo']);
    if (!isset($historico[$key])) {
        $historico[$key] = [
            'campanha'          => $camp,
            'nome_projeto'      => $nomeProjeto,
            'titulo'            => $nomeProjeto !== '' ? $nomeProjeto : ($camp !== '—' ? $camp : 'Sem nome'),
            'cliente_exibicao'  => clienteParaExibicao($r['cliente_cadastro'], $cliTexto, $nomeProjeto ?: null),
            'situacao'          => $r['situacao'],
            'ativo'             => (int)$r['ativo'],
            'inicio'            => $r['inicio'],
            'fim'               => $r['fim'],
            'pontos'            => [],
        ];
    }
    $historico[$key]['pontos'][] = $r;
}

function afFmtData(?string $d): ?string {
    if (!$d || $d === '0000-00-00') return null;
    try { return (new DateTime($d))->format('d/m/Y'); } catch (Exception $e) { return null; }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($agencia['nome']) ?> · Impakto</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css?v=2">
    <style>
        .cf-page { max-width:800px; margin:0 auto; padding:1.5rem 1.5rem 4rem; }
        .cf-voltar { font-size:0.8rem; font-weight:700; color:var(--color-text-muted); text-decoration:none; }
        .cf-voltar:hover { color:var(--color-accent-primary); }
        .cf-head { display:flex; align-items:center; gap:1rem; margin:0.75rem 0 1.25rem; flex-wrap:wrap; }
        .cf-logo { width:56px; height:56px; border-radius:10px; object-fit:contain; background:#f6f7fb; border:1px solid var(--color-border); flex-shrink:0; }
        .cf-nome { font-size:1.4rem; font-weight:800; color:var(--color-text-dark); margin:0; flex:1; }
        .cf-status { font-size:0.65rem; font-weight:800; text-transform:uppercase; letter-spacing:0.4px; padding:2px 9px; border-radius:8px; }
        .cf-status.ativo   { background:#dcfce7; color:#166534; }
        .cf-status.inativo { background:#f1f5f9; color:#475569; }
        .cf-card { background:white; border:1px solid var(--color-border); border-radius:12px; padding:1.25rem; margin-bottom:1rem; }
        .cf-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:1rem; }
        .cf-campo-label { font-size:0.65rem; font-weight:800; text-transform:uppercase; letter-spacing:0.4px; color:var(--color-text-muted); margin-bottom:0.2rem; }
        .cf-campo-val { font-size:0.9rem; font-weight:600; color:var(--color-text-dark); }
        .cf-secao-titulo { font-size:0.78rem; font-weight:800; text-transform:uppercase; letter-spacing:0.4px; color:var(--color-text-muted); margin-bottom:0.6rem; }
        .cf-pessoa { display:flex; justify-content:space-between; align-items:center; padding:0.5rem 0; border-bottom:1px solid #f0f2f5; }
        .cf-pessoa:last-child { border-bottom:none; }
        .cf-pessoa-nome { font-size:0.88rem; font-weight:700; color:var(--color-text-dark); }
        .cf-pessoa-email { font-size:0.8rem; color:var(--color-accent-primary); text-decoration:none; }
        .cf-pessoa-email:hover { text-decoration:underline; }
        .cf-vazio { font-size:0.82rem; color:var(--color-text-muted); font-style:italic; }
        .cf-secao-titulo-grande {
            display:flex; align-items:center; justify-content:space-between; gap:1rem;
            font-size:0.95rem; font-weight:800; color:var(--color-text-dark);
            margin:1.5rem 0 0.75rem;
        }
        .cf-secao-titulo-grande a { font-size:0.78rem; font-weight:700; color:var(--color-accent-primary); text-decoration:none; }
        .cf-secao-titulo-grande a:hover { text-decoration:underline; }
        .cf-cliente-item {
            display:flex; align-items:center; gap:0.7rem;
            padding:0.65rem 1.1rem; border-bottom:1px solid #f0f2f5;
        }
        .cf-cliente-item:last-child { border-bottom:none; }
        .cf-cliente-logo { width:32px; height:32px; border-radius:6px; object-fit:contain; background:#f6f7fb; border:1px solid var(--color-border); flex-shrink:0; }
        .cf-cliente-logo-vazia {
            width:32px; height:32px; border-radius:6px; background:#f6f7fb; border:1px solid var(--color-border);
            display:flex; align-items:center; justify-content:center; font-size:0.85rem; color:var(--color-text-muted); flex-shrink:0;
        }
        .cf-cliente-nome { flex:1; min-width:0; font-size:0.86rem; font-weight:700; color:var(--color-text-dark); text-decoration:none; }
        .cf-cliente-nome:hover { text-decoration:underline; }
        .cf-cliente-contagem { font-size:0.72rem; color:var(--color-text-muted); font-weight:600; white-space:nowrap; }
        .cf-hist-item {
            display:flex; align-items:flex-start; gap:0.75rem;
            padding:0.85rem 1.1rem; border-bottom:1px solid #f0f2f5;
        }
        .cf-hist-item:last-child { border-bottom:none; }
        .cf-hist-dot { width:10px; height:10px; border-radius:50%; margin-top:6px; flex-shrink:0; }
        .cf-hist-corpo { flex:1; min-width:0; }
        .cf-hist-titulo { font-size:0.9rem; font-weight:800; color:var(--color-text-dark); }
        .cf-hist-motivo { font-size:0.78rem; font-weight:500; color:var(--color-text-muted); margin-left:0.3rem; }
        .cf-hist-meta { font-size:0.78rem; color:var(--color-text-muted); margin-top:0.2rem; }
        .cf-hist-badge {
            font-size:0.62rem; font-weight:800; text-transform:uppercase; letter-spacing:0.4px;
            padding:2px 8px; border-radius:8px; flex-shrink:0; white-space:nowrap;
        }
        .cf-hist-badge.ativa     { background:#dcfce7; color:#166534; }
        .cf-hist-badge.vencida   { background:#fee2e2; color:#991b1b; }
        .cf-hist-badge.encerrada { background:#f1f5f9; color:#475569; }
        @media(max-width:520px) { .cf-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<?php include __DIR__ . '/../partials/env_banner.php'; ?>


<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="cf-page">
    <a href="/gestor/agencias" class="cf-voltar">← Voltar para Agências</a>

    <div class="cf-head">
        <?php if ($agencia['logo']): ?>
        <img src="/<?= htmlspecialchars($agencia['logo']) ?>" alt="" class="cf-logo">
        <?php endif; ?>
        <h1 class="cf-nome">🏛️ <?= htmlspecialchars($agencia['nome']) ?></h1>
        <span class="cf-status <?= $temCampanhaAtiva ? 'ativo' : 'inativo' ?>"><?= $temCampanhaAtiva ? 'Ativo' : 'Inativo' ?></span>
    </div>

    <div class="cf-card">
        <div class="cf-grid">
            <div>
                <div class="cf-campo-label">Telefone</div>
                <div class="cf-campo-val"><?= htmlspecialchars($agencia['telefone'] ?: '—') ?></div>
            </div>
            <div style="grid-column:2/3"></div>
            <div style="grid-column:1/-1">
                <div class="cf-campo-label">Endereço</div>
                <div class="cf-campo-val"><?= htmlspecialchars($agencia['endereco'] ?: '—') ?></div>
            </div>
            <?php if ($agencia['observacoes']): ?>
            <div style="grid-column:1/-1">
                <div class="cf-campo-label">Observações</div>
                <div class="cf-campo-val"><?= nl2br(htmlspecialchars($agencia['observacoes'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="cf-card">
        <div class="cf-secao-titulo">Diretoria</div>
        <?php if (empty($diretoria)): ?>
        <div class="cf-vazio">Nenhum diretor(a) cadastrado.</div>
        <?php else: foreach ($diretoria as $d): ?>
        <div class="cf-pessoa">
            <span class="cf-pessoa-nome"><?= htmlspecialchars($d['nome']) ?></span>
            <?php if ($d['email']): ?>
            <a href="mailto:<?= htmlspecialchars($d['email']) ?>" class="cf-pessoa-email"><?= htmlspecialchars($d['email']) ?></a>
            <?php endif; ?>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <div class="cf-card">
        <div class="cf-secao-titulo">Mídia</div>
        <?php if (empty($midia)): ?>
        <div class="cf-vazio">Nenhum contato de mídia cadastrado.</div>
        <?php else: foreach ($midia as $m): ?>
        <div class="cf-pessoa">
            <span class="cf-pessoa-nome"><?= htmlspecialchars($m['nome']) ?></span>
            <?php if ($m['email']): ?>
            <a href="mailto:<?= htmlspecialchars($m['email']) ?>" class="cf-pessoa-email"><?= htmlspecialchars($m['email']) ?></a>
            <?php endif; ?>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <div class="cf-secao-titulo-grande">
        <span>🏢 Clientes (<?= count($clientesAgencia) ?>)</span>
    </div>
    <div class="cf-card" style="padding:0;">
        <?php if (empty($clientesAgencia)): ?>
        <div class="cf-vazio" style="padding:1.25rem;">Nenhum cliente registrado pra essa agência ainda.</div>
        <?php else: ?>
            <?php foreach ($clientesAgencia as $cl): ?>
            <div class="cf-cliente-item">
                <?php if (!empty($cl['logo'])): ?>
                <img src="/<?= htmlspecialchars($cl['logo']) ?>" alt="" class="cf-cliente-logo">
                <?php else: ?>
                <div class="cf-cliente-logo-vazia">🏢</div>
                <?php endif; ?>
                <?php if (!empty($cl['cliente_id'])): ?>
                <a href="/gestor/clientes/ficha?id=<?= (int)$cl['cliente_id'] ?>" class="cf-cliente-nome"><?= htmlspecialchars($cl['nome_cliente']) ?></a>
                <?php else: ?>
                <span class="cf-cliente-nome"><?= htmlspecialchars($cl['nome_cliente']) ?></span>
                <?php endif; ?>
                <span class="cf-cliente-contagem"><?= (int)$cl['campanhas_ativas'] ?> ativa<?= $cl['campanhas_ativas'] != 1 ? 's' : '' ?> · <?= (int)$cl['total_campanhas'] ?> no total</span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="cf-secao-titulo-grande">
        <span>📋 Histórico de Campanhas (<?= count($historico) ?>)</span>
        <a href="/gestor/campanhas?busca=<?= urlencode($agencia['nome']) ?>">Ver em Campanhas →</a>
    </div>
    <div class="cf-card" style="padding:0;">
        <?php if (empty($historico)): ?>
        <div class="cf-vazio" style="padding:1.25rem;">Nenhuma campanha registrada pra essa agência ainda.</div>
        <?php else: ?>
            <?php foreach ($historico as $h): ?>
                <?php
                    $isVencida = $h['ativo'] && $h['fim'] && substr($h['fim'], 0, 10) < $hoje;
                    if (!$h['ativo'])       { $dotCor = '#6b7280'; $badgeCls = 'encerrada'; $badgeTxt = 'Encerrada'; }
                    elseif ($isVencida)     { $dotCor = '#6c757d'; $badgeCls = 'vencida';   $badgeTxt = 'Vencida'; }
                    else                    { $dotCor = $CORES_SIT[$h['situacao']] ?? '#888'; $badgeCls = 'ativa'; $badgeTxt = 'Ativa'; }
                    $ini = afFmtData($h['inicio']);
                    $fim = afFmtData($h['fim']);
                    $nPontos = count($h['pontos']);
                    $mostraMotivo = $h['nome_projeto'] !== '' && $h['campanha'] !== '—';
                ?>
                <div class="cf-hist-item">
                    <span class="cf-hist-dot" style="background:<?= $dotCor ?>" title="<?= htmlspecialchars($h['situacao']) ?>"></span>
                    <div class="cf-hist-corpo">
                        <span class="cf-hist-titulo"><?= htmlspecialchars($h['titulo']) ?></span>
                        <?php if ($mostraMotivo): ?>
                        <span class="cf-hist-motivo">(<?= htmlspecialchars($h['campanha']) ?>)</span>
                        <?php endif; ?>
                        <div class="cf-hist-meta">
                            <?= htmlspecialchars($h['cliente_exibicao']) ?>
                            <?php if ($ini || $fim): ?> · <?= $ini ?? '?' ?> → <?= $fim ?? '?' ?><?php endif; ?>
                            · 📍 <?= $nPontos ?> ponto<?= $nPontos > 1 ? 's' : '' ?>
                        </div>
                    </div>
                    <span class="cf-hist-badge <?= $badgeCls ?>"><?= $badgeTxt ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <a href="/gestor/agencias/editar?id=<?= (int)$agencia['id'] ?>" class="cf-voltar">✏️ Editar cadastro</a>
</div>

</body>
</html>
