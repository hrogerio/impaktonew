<?php
require_once __DIR__ . '/../../../config/security.php';

// Verificar autenticação
if (!isset($_SESSION['usuario'])) {
    header("Location: /index.php?erro=nao_logado");
    exit;
}

// Incluir controller
require_once __DIR__ . '/../../Controllers/PontoController.php';

$controller = new PontoController();

// Processar filtros
$filtros = ValidationService::validateFilters($_GET);
$pagination = ValidationService::validatePagination($_GET['pagina'] ?? 1, $_GET['limite'] ?? 10);

// Buscar dados
$dados = $controller->listar($filtros, $pagination['page'], $pagination['limit']);
$pontos = $dados['pontos'];
$total = $dados['total'];
$totalPaginas = ceil($total / $pagination['limit']);

// Obter dados extras
$clientes = $controller->obterClientesAtivos();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Pontos - Impakto</title>
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
</head>
<body>

<header class="topo">
    <div class="header-container">
        <div class="logo">
            <img src="/public/assets/img/logo.png" alt="Logo Impakto">
        </div>

        <div class="busca-google-central">
            <form method="get">
                <input type="text" name="busca" placeholder="Buscar pontos..." 
                       value="<?= htmlspecialchars($filtros['busca'] ?? '') ?>">
                <button type="submit">🔍</button>
            </form>
        </div>

        <div class="acoes">
            <nav class="links-topo">
                <a href="?page=pre_selecao">Pré-Seleção</a>
            </nav>
            <div class="usuario-menu">
                <span>Olá, <strong><?= htmlspecialchars($_SESSION['usuario']) ?></strong></span>
                <a href="/logout" class="btn-logout">Sair</a>
            </div>
        </div>
    </div>
</header>

<div class="filtros-container">
    <div class="pontos-contagem">
        <span><?= $total ?> ponto(s) encontrado(s)</span>
    </div>

    <form method="get" class="filtros-form">
        <input type="hidden" name="busca" value="<?= htmlspecialchars($filtros['busca'] ?? '') ?>">
        
        <select name="tipo" onchange="this.form.submit()">
            <option value="">Tipo</option>
            <option value="Outdoor" <?= ($filtros['tipo'] ?? '') === 'Outdoor' ? 'selected' : '' ?>>Outdoor</option>
            <option value="Painel" <?= ($filtros['tipo'] ?? '') === 'Painel' ? 'selected' : '' ?>>Painel</option>
        </select>

        <select name="regiao" onchange="this.form.submit()">
            <option value="">Região</option>
            <option value="Metropolitana" <?= ($filtros['regiao'] ?? '') === 'Metropolitana' ? 'selected' : '' ?>>Metropolitana</option>
            <option value="Agreste" <?= ($filtros['regiao'] ?? '') === 'Agreste' ? 'selected' : '' ?>>Agreste</option>
        </select>

        <select name="situacao" onchange="this.form.submit()">
            <option value="">Situação</option>
            <option value="Disponível" <?= ($filtros['situacao'] ?? '') === 'Disponível' ? 'selected' : '' ?>>Disponível</option>
            <option value="Ocupado" <?= ($filtros['situacao'] ?? '') === 'Ocupado' ? 'selected' : '' ?>>Ocupado</option>
            <option value="Reservado" <?= ($filtros['situacao'] ?? '') === 'Reservado' ? 'selected' : '' ?>>Reservado</option>
        </select>

        <a href="?" class="btn-reset">Limpar</a>
    </form>
</div>

<div class="tabela-scroll">
    <table class="tabela-compacta">
        <thead>
        <tr>
            <th>Nº</th>
            <th>Logradouro</th>
            <th>Descrição</th>
            <th>Cidade</th>
            <th>Cliente</th>
            <th>Status</th>
            <th>Fim Contrato</th>
            <th>Ações</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($pontos as $ponto): ?>
            <tr>
                <td><?= htmlspecialchars($ponto['numero'] ?? '') ?></td>
                <td><?= htmlspecialchars($ponto['logradouro'] ?? '') ?></td>
                <td><?= htmlspecialchars($ponto['descricao'] ?? '') ?></td>
                <td><?= htmlspecialchars($ponto['cidade'] ?? '') ?></td>
                <td><?= htmlspecialchars($ponto['cliente'] ?? '') ?></td>
                <td>
                    <span class="badge <?= strtolower($ponto['situacao'] ?? '') ?>">
                        <?= htmlspecialchars($ponto['situacao'] ?? '') ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($ponto['fim_contrato'] ?? '-') ?></td>
                <td>
                    <a href="?page=ponto&id=<?= $ponto['id'] ?>" target="_blank">Ver</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPaginas > 1): ?>
    <div class="paginacao">
        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>"
               class="<?= $i == $pagination['page'] ? 'ativo' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<script src="/public/assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mostrar notificação se logou com sucesso
    if (new URLSearchParams(window.location.search).get('logado') === '1') {
        if (typeof NotificationSystem !== 'undefined') {
            NotificationSystem.show('Login realizado com sucesso!', 'success');
        }
    }
});
</script>

</body>
</html>