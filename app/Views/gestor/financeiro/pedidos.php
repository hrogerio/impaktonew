<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}
$paginaAtual = 'financeiro';

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/_helpers.php';
$pdo = getDatabase();

$fTipo   = in_array($_GET['tipo'] ?? '', ['PI', 'PP'], true) ? $_GET['tipo'] : '';
$fStatus = in_array($_GET['status'] ?? '', ['rascunho', 'emitido'], true) ? $_GET['status'] : '';
$fBusca  = trim($_GET['busca'] ?? '');

$where  = [];
$params = [];
if ($fTipo !== '')   { $where[] = 'tipo = ?';                     $params[] = $fTipo; }
if ($fStatus !== '') { $where[] = 'status = ?';                   $params[] = $fStatus; }
if ($fBusca !== '')  { $where[] = '(cliente_razao_social LIKE ? OR nome_campanha LIKE ?)'; $params[] = "%$fBusca%"; $params[] = "%$fBusca%"; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("
    SELECT id, tipo, numero_data, numero_seq, status, data_emissao, nome_campanha,
           cliente_razao_social, qtd_parcelas, valor_total, valor_bruto, criado_em
    FROM pedidos_financeiros
    $whereSql
    ORDER BY criado_em DESC
");
$stmt->execute($params);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$kpiPI      = (int)$pdo->query("SELECT COUNT(*) FROM pedidos_financeiros WHERE tipo='PI'")->fetchColumn();
$kpiPP      = (int)$pdo->query("SELECT COUNT(*) FROM pedidos_financeiros WHERE tipo='PP'")->fetchColumn();
$kpiEmitido = (float)$pdo->query("SELECT COALESCE(SUM(valor_bruto),0) FROM pedidos_financeiros WHERE status='emitido'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financeiro · Impakto</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css?v=2">
    <style>
        .fp-page { max-width:clamp(1200px, 90vw, 1600px); margin:0 auto; padding:1.5rem 1.5rem 4rem; }
        .fp-kpis { display:grid; grid-template-columns:repeat(3,1fr); gap:0.75rem; margin-bottom:1.5rem; }
        .fp-kpi { background:white; border:1px solid var(--color-border); border-radius:10px; padding:0.9rem 1rem; }
        .fp-kpi-label { font-size:0.65rem; font-weight:800; text-transform:uppercase; letter-spacing:0.4px; color:var(--color-text-muted); }
        .fp-kpi-val { font-size:1.5rem; font-weight:800; color:var(--color-text-dark); line-height:1.3; }
        .fp-filtros {
            display:flex; gap:0.6rem; flex-wrap:wrap; background:white; border:1px solid var(--color-border);
            border-radius:10px; padding:0.75rem 1rem; margin-bottom:1.25rem; align-items:center;
        }
        .fp-filtros input, .fp-filtros select {
            padding:0.45rem 0.65rem; border:1px solid var(--color-border); border-radius:7px;
            font-family:'Montserrat',sans-serif; font-size:0.82rem;
        }
        .fp-filtros input[type=text] { flex:1; min-width:180px; }
        .fp-badge { display:inline-block; padding:0.15rem 0.55rem; border-radius:20px; font-size:0.7rem; font-weight:800; }
        .fp-badge.pi { background:#e0f2fe; color:#0369a1; }
        .fp-badge.pp { background:#fef3c7; color:#92400e; }
        .fp-status { display:inline-block; padding:0.15rem 0.55rem; border-radius:20px; font-size:0.7rem; font-weight:700; }
        .fp-status.rascunho { background:#f1f5f9; color:#64748b; }
        .fp-status.emitido  { background:#dcfce7; color:#15803d; }
        .fp-acoes a, .fp-acoes button { margin-right:0.4rem; font-size:0.78rem; }
        .fp-vazio { padding:3rem; text-align:center; color:var(--color-text-muted); font-size:0.88rem; font-weight:600; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../../partials/env_banner.php'; ?>
<?php require __DIR__ . '/../../layouts/_nav.php'; ?>

<div class="fp-page">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.75rem">
        <h1 style="font-size:1.3rem;font-weight:800;color:var(--color-text-dark);margin:0">💰 Financeiro · P.I. / P.P.</h1>
        <div style="display:flex;gap:0.5rem">
            <a href="/gestor/financeiro/pedidos/novo?tipo=PI" class="btn btn-primary">+ Novo P.I.</a>
            <a href="/gestor/financeiro/pedidos/novo?tipo=PP" class="btn btn-secondary">+ Novo P.P.</a>
        </div>
    </div>

    <div class="fp-kpis">
        <div class="fp-kpi"><div class="fp-kpi-label">Pedidos de Inserção</div><div class="fp-kpi-val"><?= $kpiPI ?></div></div>
        <div class="fp-kpi"><div class="fp-kpi-label">Pedidos de Produção</div><div class="fp-kpi-val"><?= $kpiPP ?></div></div>
        <div class="fp-kpi"><div class="fp-kpi-label">Total emitido</div><div class="fp-kpi-val"><?= moedaBr($kpiEmitido) ?></div></div>
    </div>

    <form class="fp-filtros" method="get" action="/gestor/financeiro/pedidos">
        <input type="text" name="busca" placeholder="Buscar cliente ou campanha..." value="<?= htmlspecialchars($fBusca) ?>">
        <select name="tipo">
            <option value="">Todos os tipos</option>
            <option value="PI" <?= $fTipo === 'PI' ? 'selected' : '' ?>>P.I.</option>
            <option value="PP" <?= $fTipo === 'PP' ? 'selected' : '' ?>>P.P.</option>
        </select>
        <select name="status">
            <option value="">Todos os status</option>
            <option value="rascunho" <?= $fStatus === 'rascunho' ? 'selected' : '' ?>>Rascunho</option>
            <option value="emitido" <?= $fStatus === 'emitido' ? 'selected' : '' ?>>Emitido</option>
        </select>
        <button type="submit" class="btn btn-outline">Filtrar</button>
        <?php if ($fTipo || $fStatus || $fBusca): ?>
            <a href="/gestor/financeiro/pedidos" class="btn btn-reset">Limpar</a>
        <?php endif; ?>
    </form>

    <?php if (empty($pedidos)): ?>
        <div class="card"><div class="fp-vazio">Nenhum pedido encontrado.</div></div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nº</th>
                        <th>Tipo</th>
                        <th>Cliente</th>
                        <th>Campanha</th>
                        <th>Emissão</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pedidos as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars(numeroPedidoFmt($p['tipo'], $p['numero_data'], (int)$p['numero_seq'])) ?></td>
                        <td><span class="fp-badge <?= strtolower($p['tipo']) ?>"><?= $p['tipo'] ?></span></td>
                        <td><?= htmlspecialchars($p['cliente_razao_social']) ?></td>
                        <td><?= htmlspecialchars($p['nome_campanha'] ?: '-') ?></td>
                        <td><?= dataFmtBr($p['data_emissao']) ?></td>
                        <td><?= moedaBr((float)$p['valor_bruto']) ?><?php if ((int)$p['qtd_parcelas'] > 1): ?><br><span style="font-size:0.7rem;color:var(--color-text-muted)"><?= moedaBr((float)$p['valor_total']) ?>/mês · <?= (int)$p['qtd_parcelas'] ?>x</span><?php endif; ?></td>
                        <td><span class="fp-status <?= $p['status'] ?>"><?= $p['status'] === 'emitido' ? 'Emitido' : 'Rascunho' ?></span></td>
                        <td class="fp-acoes">
                            <a href="/gestor/financeiro/pedidos/editar?id=<?= $p['id'] ?>" class="btn btn-outline">Editar</a>
                            <a href="/gestor/financeiro/pedidos/pdf?id=<?= $p['id'] ?>" class="btn btn-outline" target="_blank">PDF</a>
                            <button type="button" class="btn btn-reset" onclick="excluirPedido(<?= $p['id'] ?>)">Excluir</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function excluirPedido(id) {
    if (!confirm('Excluir este pedido? Essa ação não pode ser desfeita.')) return;
    fetch('/gestor/financeiro/pedidos/excluir', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) { location.reload(); }
        else { alert('Erro ao excluir: ' + (d.erro || 'desconhecido')); }
    })
    .catch(() => alert('Erro de conexão ao excluir.'));
}
</script>
</body>
</html>
