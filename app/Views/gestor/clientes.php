<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

$paginaAtual = 'clientes';

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

// ── Filtros ────────────────────────────────────────────────
$busca         = trim($_GET['q'] ?? '');
$statusFiltro  = in_array($_GET['status'] ?? '', ['ativo', 'inativo'], true) ? $_GET['status'] : '';

$colunasOrdenaveis = ['razao_social', 'cnpj', 'contato', 'telefone', 'email', 'ativo'];
$sort = in_array($_GET['sort'] ?? '', $colunasOrdenaveis, true) ? $_GET['sort'] : 'razao_social';
$dir  = strtolower($_GET['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

// Status agora é automático: cliente é "ativo" quando tem alguma campanha
// ativa vinculada (campanhas.cliente_id -> clientes.id, campanhas.ativo = 1).
$statusExpr = "EXISTS (SELECT 1 FROM campanhas cp WHERE cp.cliente_id = clientes.id AND cp.ativo = 1)";

$where  = [];
$params = [];
if ($busca !== '') {
    $where[]  = "(razao_social LIKE ? OR cnpj LIKE ?)";
    $like     = '%' . $busca . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($statusFiltro === 'ativo') {
    $where[] = "$statusExpr";
} elseif ($statusFiltro === 'inativo') {
    $where[] = "NOT ($statusExpr)";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ── Paginação ──────────────────────────────────────────────
$porPagina = 5;
$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM clientes $whereSql");
$stmtTotal->execute($params);
$totalFiltrado = (int)$stmtTotal->fetchColumn();
$totalPaginas  = max(1, (int)ceil($totalFiltrado / $porPagina));

$pagina = max(1, min((int)($_GET['page'] ?? 1), $totalPaginas));
$offset = ($pagina - 1) * $porPagina;

$ordemColuna = $sort === 'ativo' ? 'tem_campanha_ativa' : $sort;
$sql = "SELECT clientes.*, ($statusExpr) AS tem_campanha_ativa
        FROM clientes $whereSql
        ORDER BY $ordemColuna $dir, razao_social ASC LIMIT $porPagina OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupa por status (estilo "grupos" do monday.com) — dentro da página atual
$grupos = ['ativo' => [], 'inativo' => []];
foreach ($clientes as $c) {
    $grupos[$c['tem_campanha_ativa'] ? 'ativo' : 'inativo'][] = $c;
}
$grupoInfo = [
    'ativo'   => ['titulo' => 'Ativos',   'cor' => '#00c875'],
    'inativo' => ['titulo' => 'Inativos', 'cor' => '#e2445c'],
];

// ── Cards de resumo (totais gerais, não afetados pelos filtros) ──
$totais = $pdo->query("SELECT COUNT(*) AS total,
                               SUM(CASE WHEN $statusExpr THEN 1 ELSE 0 END) AS ativos,
                               SUM(CASE WHEN NOT ($statusExpr) THEN 1 ELSE 0 END) AS inativos
                        FROM clientes")
               ->fetch(PDO::FETCH_ASSOC);

// Lista completa de razões sociais, para autocomplete no campo de busca
$listaRazoesSociais = $pdo->query("SELECT razao_social FROM clientes ORDER BY razao_social ASC")
                           ->fetchAll(PDO::FETCH_COLUMN);

// ── Helper para montar querystring preservando filtros ────
function clientesQs(array $overrides): string {
    $qs = array_merge($_GET, $overrides);
    foreach ($qs as $k => $v) {
        if ($v === '' || $v === null) unset($qs[$k]);
    }
    $str = http_build_query($qs);
    return $str !== '' ? ('?' . $str) : '';
}

function clientesSortLink(string $coluna, string $rotulo, string $sortAtual, string $dirAtual): string {
    $novaDir = ($sortAtual === $coluna && $dirAtual === 'ASC') ? 'desc' : 'asc';
    $seta = '';
    if ($sortAtual === $coluna) {
        $seta = $dirAtual === 'ASC' ? ' ▲' : ' ▼';
    }
    $href = htmlspecialchars(clientesQs(['sort' => $coluna, 'dir' => $novaDir, 'page' => null]));
    return '<a href="' . $href . '" class="th-sort-link">' . htmlspecialchars($rotulo) . $seta . '</a>';
}

function clientesIniciais(string $nome): string {
    $palavras = preg_split('/\s+/', trim($nome));
    $palavras = array_filter($palavras, fn($p) => $p !== '' && !in_array(mb_strtolower($p), ['de','da','do','das','dos','e']));
    $palavras = array_values($palavras);
    if (count($palavras) === 0) return '?';
    if (count($palavras) === 1) return mb_strtoupper(mb_substr($palavras[0], 0, 2));
    return mb_strtoupper(mb_substr($palavras[0], 0, 1) . mb_substr($palavras[1], 0, 1));
}

// Paleta rotativa de avatar, estilo monday.com (cores por índice, determinístico por nome)
$cliPaletaAvatar = ['#579bfc', '#a25ddc', '#00c875', '#fdab3d', '#e2445c', '#66ccff', '#ff642e', '#7f5347'];
function clientesCorAvatar(array $paleta, string $seed): string {
    $hash = 0;
    foreach (str_split($seed) as $ch) { $hash = ($hash * 31 + ord($ch)) % 100000; }
    return $paleta[$hash % count($paleta)];
}

$mensagem = '';
if (isset($_GET['msg'])) {
    $mapa = [
        'criado'     => 'Cliente cadastrado com sucesso.',
        'atualizado' => 'Cliente atualizado com sucesso.',
        'ativado'    => 'Cliente ativado.',
        'desativado' => 'Cliente desativado.',
    ];
    $mensagem = $mapa[$_GET['msg']] ?? '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes · Impakto</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
    <link rel="stylesheet" href="/public/assets/css/backup.css">
    <style>
        .cli-totais-discretos {
            margin-left: auto; font-size: 0.78rem; color: var(--color-text-muted);
            white-space: nowrap;
        }
        .cli-totais-discretos .pt-ativo { color: #00c875; font-weight: 700; }
        .cli-totais-discretos .pt-inativo { color: #e2445c; font-weight: 700; }

        /* ── Toolbar estilo monday.com: campos em pílula, compactos ── */
        .cli-filtros {
            display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; align-items: center;
        }
        .cli-filtros input[type="text"] {
            flex: 1; max-width: 320px; padding: 0.55rem 0.9rem;
            border: 1.5px solid var(--color-border); border-radius: 999px;
            font-family: inherit; font-size: 0.85rem; transition: border-color 0.15s;
        }
        .cli-filtros input[type="text"]:focus { outline: none; border-color: var(--color-accent-primary); }
        .cli-filtros select {
            padding: 0.55rem 0.9rem; border: 1.5px solid var(--color-border); border-radius: 999px;
            font-family: inherit; font-size: 0.85rem; background: white;
        }
        .cli-filtros button, .cli-filtros a.btn-dl { border-radius: 999px !important; }
        .cli-novo-btn {
            border-radius: 999px !important; font-weight: 700 !important;
            box-shadow: 0 2px 8px rgba(255, 100, 46, 0.25);
        }

        .table-container { overflow-x: auto; max-height: 70vh; overflow-y: auto; }
        .backup-table { table-layout: fixed; width: 100%; border-collapse: separate; border-spacing: 0; }
        .backup-table thead th {
            position: sticky; top: 0; z-index: 2;
        }
        .backup-table th, .backup-table td {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .backup-table td[title] { cursor: help; }
        .backup-table th:nth-child(1), .backup-table td:nth-child(1) { width: 22%; }
        .backup-table th:nth-child(2), .backup-table td:nth-child(2) { width: 14%; }
        .backup-table th:nth-child(3), .backup-table td:nth-child(3) { width: 14%; }
        .backup-table th:nth-child(4), .backup-table td:nth-child(4) { width: 12%; }
        .backup-table th:nth-child(5), .backup-table td:nth-child(5) { width: 24%; }
        .backup-table th:nth-child(6), .backup-table td:nth-child(6) { width: 8%; text-align: center; }
        .backup-table th:nth-child(7), .backup-table td:nth-child(7) {
            width: 10%; white-space: normal; overflow: visible; text-overflow: clip;
        }
        .th-sort-link { color: white; text-decoration: none; }
        .th-sort-link:hover { font-weight: 800; }

        /* ── Linha de grupo, estilo "group header" do monday.com ── */
        .cli-grupo-row td {
            padding: 0.5rem 0.875rem !important;
            font-weight: 800; font-size: 0.8rem;
            cursor: pointer; user-select: none;
            white-space: nowrap; overflow: visible;
            border-bottom: none !important;
        }
        .cli-grupo-row:hover td { filter: brightness(0.97); }
        .cli-grupo-chevron { display: inline-block; margin-right: 0.4rem; transition: transform 0.15s; }
        .cli-grupo-row.colapsado .cli-grupo-chevron { transform: rotate(-90deg); }
        tr.cli-linha[data-grupo-colapsado="1"] { display: none; }
        .cli-linha td { border-left: 3px solid transparent; }
        .cli-linha { transition: background 0.1s; }
        .cli-linha:hover { background: #f6f7fb; }

        /* ── Avatar circular com iniciais, estilo "pessoa" do monday.com ── */
        .cli-avatar-nome { display: flex; align-items: center; gap: 0.6rem; }
        .cli-avatar {
            flex-shrink: 0; width: 26px; height: 26px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 800; font-size: 0.68rem;
        }
        .cli-avatar-nome { min-width: 0; }
        .cli-avatar-nome span { overflow: hidden; text-overflow: ellipsis; min-width: 0; }

        .cli-email-cell {
            display: flex; align-items: center; gap: 0.4rem;
            overflow: hidden; min-width: 0;
        }
        .cli-email-link {
            color: #1a73e8; text-decoration: none; font-weight: 700;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            display: inline-flex; align-items: center; gap: 0.3rem;
            min-width: 0; max-width: 100%;
            background-image: none;
        }
        .cli-email-link span { overflow: hidden; text-overflow: ellipsis; min-width: 0; }
        .cli-email-link:hover { text-decoration: underline; text-decoration-color: #1a73e8; }
        .cli-email-link:visited { color: #6b3fa0; }

        .cli-status-dot {
            display: inline-block; width: 12px; height: 12px; border-radius: 50%;
            cursor: help;
        }
        .cli-status-dot.ativo { background: #00c875; }
        .cli-status-dot.inativo { background: #e2445c; }

        .cli-acoes {
            display: flex; gap: 0.3rem; align-items: center;
            white-space: nowrap; overflow: visible !important;
        }
        .cli-acao-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 26px; height: 26px; border-radius: 6px;
            border: 1px solid transparent; background: none; cursor: pointer;
            font-size: 0.85rem; line-height: 1; opacity: 0.55;
            transition: opacity 0.12s, background 0.12s, border-color 0.12s;
            font-family: inherit;
        }
        .cli-acao-btn:hover {
            opacity: 1; background: #f3f4f6; border-color: var(--color-border);
        }

        .cli-paginacao {
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem;
        }
        .cli-paginacao-info { font-size: 0.82rem; color: var(--color-text-muted); }
        .cli-paginacao-links { display: flex; gap: 0.4rem; }
        .cli-paginacao-links a, .cli-paginacao-links span {
            padding: 0.4rem 0.75rem; border-radius: 999px; font-size: 0.82rem; font-weight: 600;
            border: 1px solid var(--color-border); text-decoration: none; color: var(--color-text-dark);
        }
        .cli-paginacao-links a:hover { background: #f3f4f6; }
        .cli-paginacao-links .atual { background: var(--color-accent-primary); color: white; border-color: var(--color-accent-primary); }
        .cli-paginacao-links .desabilitado { opacity: 0.4; pointer-events: none; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="container" style="padding-bottom:2rem;">

    <div class="welcome" style="margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
        <div>
            <h2>🏢 Clientes</h2>
            <p>Cadastro de dados dos clientes (independente de campanha ativa).</p>
        </div>
        <a href="/gestor/clientes/novo" class="btn-backup btn-baixar cli-novo-btn">➕ Novo Cliente</a>
    </div>

    <?php if ($mensagem): ?>
        <div class="alerta alerta-ok">✅ <?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <form method="GET" action="/gestor/clientes" class="cli-filtros">
        <input type="text" name="q" id="cliBuscaInput" placeholder="🔎 Buscar por razão social ou CNPJ..." value="<?= htmlspecialchars($busca) ?>"
               list="cliRazoesSociais" autocomplete="off">
        <datalist id="cliRazoesSociais">
            <?php foreach ($listaRazoesSociais as $rs): ?>
                <option value="<?= htmlspecialchars($rs) ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <select name="status">
            <option value="">Todos os status</option>
            <option value="ativo" <?= $statusFiltro === 'ativo' ? 'selected' : '' ?>>Somente ativos</option>
            <option value="inativo" <?= $statusFiltro === 'inativo' ? 'selected' : '' ?>>Somente inativos</option>
        </select>
        <button type="submit" class="btn-dl">Filtrar</button>
        <?php if ($busca !== '' || $statusFiltro !== ''): ?>
            <a href="/gestor/clientes" class="btn-dl">Limpar</a>
        <?php endif; ?>
        <span class="cli-totais-discretos">
            <?= (int)$totais['total'] ?> clientes ·
            <span class="pt-ativo"><?= (int)$totais['ativos'] ?> ativos</span> ·
            <span class="pt-inativo"><?= (int)$totais['inativos'] ?> inativos</span>
        </span>
    </form>

    <div class="table-container">
        <table class="backup-table">
            <thead>
                <tr>
                    <th><?= clientesSortLink('razao_social', 'Razão Social', $sort, $dir) ?></th>
                    <th><?= clientesSortLink('cnpj', 'CNPJ', $sort, $dir) ?></th>
                    <th><?= clientesSortLink('contato', 'Contato', $sort, $dir) ?></th>
                    <th><?= clientesSortLink('telefone', 'Telefone', $sort, $dir) ?></th>
                    <th><?= clientesSortLink('email', 'E-mail', $sort, $dir) ?></th>
                    <th><?= clientesSortLink('ativo', 'Status', $sort, $dir) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($clientes)): ?>
                <tr><td colspan="7" style="text-align:center; color:var(--color-text-muted);">Nenhum cliente encontrado.</td></tr>
            <?php endif; ?>

            <?php foreach ($grupos as $chaveGrupo => $linhas): ?>
                <?php if (empty($linhas)) continue; ?>
                <?php $info = $grupoInfo[$chaveGrupo]; ?>
                <tr class="cli-grupo-row" data-grupo="<?= $chaveGrupo ?>"
                    style="background:<?= $info['cor'] ?>1a; color:<?= $info['cor'] ?>;"
                    onclick="cliToggleGrupo('<?= $chaveGrupo ?>', this)">
                    <td colspan="7">
                        <span class="cli-grupo-chevron">▾</span><?= htmlspecialchars($info['titulo']) ?> (<?= count($linhas) ?>)
                    </td>
                </tr>
                <?php foreach ($linhas as $c): ?>
                    <tr class="cli-linha" data-grupo="<?= $chaveGrupo ?>"
                        style="border-left:3px solid <?= $info['cor'] ?>;">
                        <td class="backup-nome" title="<?= htmlspecialchars($c['razao_social']) ?>">
                            <div class="cli-avatar-nome">
                                <span class="cli-avatar" style="background:<?= clientesCorAvatar($cliPaletaAvatar, $c['razao_social']) ?>;">
                                    <?= htmlspecialchars(clientesIniciais($c['razao_social'])) ?>
                                </span>
                                <span><?= htmlspecialchars($c['razao_social']) ?></span>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($c['cnpj'] ?: '—') ?></td>
                        <td title="<?= htmlspecialchars($c['contato'] ?: '') ?>"><?= htmlspecialchars($c['contato'] ?: '—') ?></td>
                        <td><?= htmlspecialchars($c['telefone'] ?: '—') ?></td>
                        <td title="<?= htmlspecialchars($c['email'] ?: '') ?>">
                            <?php if ($c['email']): ?>
                                <div class="cli-email-cell">
                                    <a href="mailto:<?= htmlspecialchars($c['email']) ?>" class="cli-email-link">
                                        <span><?= htmlspecialchars($c['email']) ?></span>
                                    </a>
                                </div>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($c['tem_campanha_ativa']): ?>
                                <span class="cli-status-dot ativo" title="Ativo — tem campanha ativa vinculada"></span>
                            <?php else: ?>
                                <span class="cli-status-dot inativo" title="Inativo — sem campanha ativa no momento"></span>
                            <?php endif; ?>
                        </td>
                        <td class="cli-acoes">
                            <a href="/gestor/clientes/editar?id=<?= (int)$c['id'] ?>" class="cli-acao-btn" title="Editar">✏️</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalFiltrado > 0): ?>
    <div class="cli-paginacao">
        <div class="cli-paginacao-info">
            Mostrando <?= count($clientes) ?> de <?= $totalFiltrado ?> cliente<?= $totalFiltrado === 1 ? '' : 's' ?>
            — página <?= $pagina ?> de <?= $totalPaginas ?>
        </div>
        <div class="cli-paginacao-links">
            <a href="<?= htmlspecialchars(clientesQs(['page' => $pagina - 1])) ?>" class="<?= $pagina <= 1 ? 'desabilitado' : '' ?>">« Anterior</a>
            <a href="<?= htmlspecialchars(clientesQs(['page' => $pagina + 1])) ?>" class="<?= $pagina >= $totalPaginas ? 'desabilitado' : '' ?>">Próxima »</a>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
function cliToggleGrupo(grupo, headerEl) {
    var colapsar = !headerEl.classList.contains('colapsado');
    headerEl.classList.toggle('colapsado', colapsar);
    document.querySelectorAll('tr.cli-linha[data-grupo="' + grupo + '"]').forEach(function(tr) {
        if (colapsar) {
            tr.setAttribute('data-grupo-colapsado', '1');
        } else {
            tr.removeAttribute('data-grupo-colapsado');
        }
    });
}

// Envia a busca automaticamente quando o usuário escolhe uma sugestão do autocomplete
(function() {
    var input = document.getElementById('cliBuscaInput');
    var datalist = document.getElementById('cliRazoesSociais');
    if (!input || !datalist) return;

    var opcoes = Array.prototype.map.call(datalist.options, function(o) { return o.value; });

    input.addEventListener('input', function() {
        if (opcoes.indexOf(input.value) !== -1) {
            input.form.submit();
        }
    });
})();
</script>

</body>
</html>
