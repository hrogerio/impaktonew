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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editando = $id > 0;

// Pra onde voltar depois de salvar: de onde o usuário veio (ex: Relatórios > Clientes),
// restrito a páginas internas do próprio gestor por segurança.
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$refPath = parse_url($referer, PHP_URL_PATH) ?: '';
$voltarUrl = str_starts_with($refPath, '/gestor/') ? $referer : '/gestor/clientes';

$dados = ['id' => 0, 'razao_social' => '', 'nome_fantasia' => '', 'cnpj' => '', 'endereco' => '', 'email' => '', 'telefone' => '', 'contato' => '', 'observacoes' => ''];
if ($editando) {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        header("Location: /gestor/clientes");
        exit;
    }
    $dados = $row;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $editando ? 'Editar' : 'Novo' ?> Cliente · Impakto</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="container" style="padding-bottom:2rem; max-width:640px;">

    <div class="welcome" style="margin-bottom:1.5rem;">
        <h2><?= $editando ? '✏️ Editar Cliente' : '➕ Novo Cliente' ?></h2>
    </div>

    <?php if (!empty($_GET['erro'])): ?>
        <div class="alerta alerta-err">❌ <?= htmlspecialchars($_GET['erro']) ?></div>
    <?php endif; ?>

    <form method="POST" action="/gestor/clientes/salvar" class="table-container" style="padding:1.5rem;">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="id" value="<?= (int)$dados['id'] ?>">
        <input type="hidden" name="voltar" value="<?= htmlspecialchars($voltarUrl) ?>">

        <div class="form-group" style="margin-bottom:1rem;">
            <label>Razão Social *</label><br>
            <input type="text" name="razao_social" required maxlength="200"
                   value="<?= htmlspecialchars($dados['razao_social']) ?>"
                   style="width:100%; padding:0.6rem; border:1px solid var(--color-border); border-radius:8px;">
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label>Nome Fantasia</label><br>
            <input type="text" name="nome_fantasia" maxlength="200"
                   value="<?= htmlspecialchars($dados['nome_fantasia'] ?? '') ?>"
                   style="width:100%; padding:0.6rem; border:1px solid var(--color-border); border-radius:8px;">
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label>CNPJ</label><br>
            <input type="text" name="cnpj" maxlength="20"
                   value="<?= htmlspecialchars($dados['cnpj'] ?? '') ?>"
                   placeholder="00.000.000/0000-00"
                   style="width:100%; padding:0.6rem; border:1px solid var(--color-border); border-radius:8px;">
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label>Endereço</label><br>
            <input type="text" name="endereco" maxlength="255"
                   value="<?= htmlspecialchars($dados['endereco'] ?? '') ?>"
                   style="width:100%; padding:0.6rem; border:1px solid var(--color-border); border-radius:8px;">
        </div>

        <div style="display:flex; gap:1rem; flex-wrap:wrap;">
            <div class="form-group" style="margin-bottom:1rem; flex:1; min-width:220px;">
                <label>E-mail</label><br>
                <input type="email" name="email" maxlength="150"
                       value="<?= htmlspecialchars($dados['email'] ?? '') ?>"
                       style="width:100%; padding:0.6rem; border:1px solid var(--color-border); border-radius:8px;">
            </div>

            <div class="form-group" style="margin-bottom:1rem; flex:1; min-width:220px;">
                <label>Telefone</label><br>
                <input type="text" name="telefone" maxlength="30"
                       value="<?= htmlspecialchars($dados['telefone'] ?? '') ?>"
                       style="width:100%; padding:0.6rem; border:1px solid var(--color-border); border-radius:8px;">
            </div>
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label>Contato (nome do responsável)</label><br>
            <input type="text" name="contato" maxlength="150"
                   value="<?= htmlspecialchars($dados['contato'] ?? '') ?>"
                   style="width:100%; padding:0.6rem; border:1px solid var(--color-border); border-radius:8px;">
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label>Observações</label><br>
            <textarea name="observacoes" rows="3"
                      style="width:100%; padding:0.6rem; border:1px solid var(--color-border); border-radius:8px;"><?= htmlspecialchars($dados['observacoes'] ?? '') ?></textarea>
        </div>

        <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
            <button type="submit" class="btn-backup btn-baixar">💾 Salvar</button>
            <a href="<?= htmlspecialchars($voltarUrl) ?>" class="btn-backup" style="background:#f3f4f6; color:var(--color-text-dark);">Cancelar</a>
        </div>
    </form>

</div>

</body>
</html>
