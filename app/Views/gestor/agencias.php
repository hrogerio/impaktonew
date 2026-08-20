<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

$paginaAtual = 'relatorios';

try {
    require_once __DIR__ . '/../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("Erro na conexão com o banco de dados.");
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

$busca = trim($_GET['q'] ?? '');

$where  = [];
$params = [];
if ($busca !== '') {
    $where[]  = "nome LIKE ?";
    $params[] = '%' . $busca . '%';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$porPagina = 15;
$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM agencias $whereSql");
$stmtTotal->execute($params);
$totalFiltrado = (int)$stmtTotal->fetchColumn();
$totalPaginas  = max(1, (int)ceil($totalFiltrado / $porPagina));

$pagina = max(1, min((int)($_GET['page'] ?? 1), $totalPaginas));
$offset = ($pagina - 1) * $porPagina;

$sql = "SELECT * FROM agencias $whereSql ORDER BY nome ASC LIMIT $porPagina OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$agencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Conta diretoria/mídia de cada agência da página atual, numa única query
$contagens = [];
if ($agencias) {
    $ids = array_column($agencias, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $stmtC = $pdo->prepare("SELECT agencia_id, tipo, COUNT(*) AS qtd FROM agencia_contatos WHERE agencia_id IN ($ph) GROUP BY agencia_id, tipo");
    $stmtC->execute($ids);
    foreach ($stmtC->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $contagens[$r['agencia_id']][$r['tipo']] = (int)$r['qtd'];
    }
}

$totalGeral = (int)$pdo->query("SELECT COUNT(*) FROM agencias")->fetchColumn();

function agenciasQs(array $overrides): string {
    $qs = array_merge($_GET, $overrides);
    foreach ($qs as $k => $v) { if ($v === '' || $v === null) unset($qs[$k]); }
    $str = http_build_query($qs);
    return $str !== '' ? ('?' . $str) : '';
}

$mensagem = '';
if (isset($_GET['msg'])) {
    $qtdImportadas = (int)($_GET['qtd'] ?? 0);
    $mapa = [
        'criado'     => 'Agência cadastrada com sucesso.',
        'atualizado' => 'Agência atualizada com sucesso.',
        'importado'  => $qtdImportadas . ' agência' . ($qtdImportadas === 1 ? '' : 's') . ' importada' . ($qtdImportadas === 1 ? '' : 's') . ' de campanhas existentes.',
    ];
    $mensagem = $mapa[$_GET['msg']] ?? '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agências · Impakto</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
    <link rel="stylesheet" href="/public/assets/css/backup.css">
    <style>
        .ag-filtros { display:flex; gap:0.5rem; margin-bottom:1rem; flex-wrap:wrap; align-items:center; }
        .ag-filtros input[type="text"] {
            flex:1; max-width:320px; padding:0.55rem 0.9rem;
            border:1.5px solid var(--color-border); border-radius:999px;
            font-family:inherit; font-size:0.85rem;
        }
        .ag-filtros button, .ag-filtros a.btn-dl { border-radius:999px !important; }
        .ag-novo-btn { border-radius:999px !important; font-weight:700 !important; box-shadow:0 2px 8px rgba(255,100,46,0.25); }
        .ag-logo-cel { width:44px; height:44px; border-radius:8px; object-fit:contain; background:#f6f7fb; border:1px solid var(--color-border); }
        .ag-logo-vazio { width:44px; height:44px; border-radius:8px; background:#f6f7fb; border:1px solid var(--color-border); display:flex; align-items:center; justify-content:center; font-size:1.1rem; color:var(--color-text-muted); }
        .ag-acoes { display:flex; gap:0.3rem; align-items:center; }
        .ag-acao-btn {
            display:inline-flex; align-items:center; justify-content:center;
            width:26px; height:26px; border-radius:6px; border:1px solid transparent;
            background:none; cursor:pointer; font-size:0.85rem; text-decoration:none;
        }
        .ag-acao-btn:hover { background:#f3f4f6; border-color:var(--color-border); }
        .ag-paginacao { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; margin-top:1rem; }
        .ag-paginacao-info { font-size:0.82rem; color:var(--color-text-muted); }
        .ag-paginacao-links { display:flex; gap:0.4rem; }
        .ag-paginacao-links a, .ag-paginacao-links span {
            padding:0.4rem 0.75rem; border-radius:999px; font-size:0.82rem; font-weight:600;
            border:1px solid var(--color-border); text-decoration:none; color:var(--color-text-dark);
        }
        .ag-paginacao-links a:hover { background:#f3f4f6; }
        .ag-paginacao-links .desabilitado { opacity:0.4; pointer-events:none; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="container" style="padding-bottom:2rem;">

    <div class="welcome" style="margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
        <div>
            <h2>🏛️ Agências</h2>
            <p>Cadastro de agências parceiras, com diretoria e departamento de mídia.</p>
        </div>
        <div style="display:flex; gap:0.6rem; flex-wrap:wrap;">
            <a href="/gestor/agencias/importar" class="btn-backup" style="background:#f3f4f6; color:var(--color-text-dark); border-radius:999px;">📥 Importar de Campanhas</a>
            <a href="/gestor/agencias/novo" class="btn-backup btn-baixar ag-novo-btn">➕ Nova Agência</a>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="alerta alerta-ok">✅ <?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <form method="GET" action="/gestor/agencias" class="ag-filtros">
        <input type="text" name="q" placeholder="🔎 Buscar por nome..." value="<?= htmlspecialchars($busca) ?>">
        <button type="submit" class="btn-dl">Filtrar</button>
        <?php if ($busca !== ''): ?>
            <a href="/gestor/agencias" class="btn-dl">Limpar</a>
        <?php endif; ?>
        <span style="margin-left:auto; font-size:0.78rem; color:var(--color-text-muted);"><?= $totalGeral ?> agências cadastradas</span>
    </form>

    <div class="table-container">
        <table class="backup-table">
            <thead>
                <tr>
                    <th style="width:60px;"></th>
                    <th>Nome</th>
                    <th>Telefone</th>
                    <th>Diretoria</th>
                    <th>Mídia</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($agencias)): ?>
                <tr><td colspan="6" style="text-align:center; color:var(--color-text-muted);">Nenhuma agência encontrada.</td></tr>
            <?php endif; ?>
            <?php foreach ($agencias as $ag): ?>
                <tr>
                    <td>
                        <?php if ($ag['logo']): ?>
                        <img src="/<?= htmlspecialchars($ag['logo']) ?>" alt="" class="ag-logo-cel">
                        <?php else: ?>
                        <div class="ag-logo-vazio">🏛️</div>
                        <?php endif; ?>
                    </td>
                    <td class="backup-nome">
                        <a href="/gestor/agencias/ficha?id=<?= (int)$ag['id'] ?>" style="color:var(--color-text-dark); font-weight:700; text-decoration:none;"><?= htmlspecialchars($ag['nome']) ?></a>
                    </td>
                    <td><?= htmlspecialchars($ag['telefone'] ?: '—') ?></td>
                    <td><?= $contagens[$ag['id']]['diretoria'] ?? 0 ?></td>
                    <td><?= $contagens[$ag['id']]['midia'] ?? 0 ?></td>
                    <td class="ag-acoes">
                        <a href="/gestor/agencias/editar?id=<?= (int)$ag['id'] ?>" class="ag-acao-btn" title="Editar">✏️</a>
                        <form method="POST" action="/gestor/agencias/excluir" style="display:inline;" onsubmit="return confirm('Excluir a agência &quot;<?= htmlspecialchars(str_replace('"', '', $ag['nome'])) ?>&quot;? Essa ação também remove a diretoria e mídia cadastradas. Não pode ser desfeita.');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="id" value="<?= (int)$ag['id'] ?>">
                            <button type="submit" class="ag-acao-btn" title="Excluir">🗑️</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalFiltrado > 0): ?>
    <div class="ag-paginacao">
        <div class="ag-paginacao-info">
            Mostrando <?= count($agencias) ?> de <?= $totalFiltrado ?> agência<?= $totalFiltrado === 1 ? '' : 's' ?>
            — página <?= $pagina ?> de <?= $totalPaginas ?>
        </div>
        <div class="ag-paginacao-links">
            <a href="<?= htmlspecialchars(agenciasQs(['page' => $pagina - 1])) ?>" class="<?= $pagina <= 1 ? 'desabilitado' : '' ?>">« Anterior</a>
            <a href="<?= htmlspecialchars(agenciasQs(['page' => $pagina + 1])) ?>" class="<?= $pagina >= $totalPaginas ? 'desabilitado' : '' ?>">Próxima »</a>
        </div>
    </div>
    <?php endif; ?>

</div>

</body>
</html>
