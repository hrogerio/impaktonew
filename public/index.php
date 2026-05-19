<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/gestor");
    exit;
}

$erro = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha   = $_POST['senha'] ?? '';

    if (empty($_POST['_token']) || $_POST['_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $erro = "Requisição inválida. Tente novamente.";
    } elseif (empty($usuario) || empty($senha)) {
        $erro = "Usuário e senha são obrigatórios";
    } else {
        try {
            require_once __DIR__ . '/../config/database.php';
            $pdo = getDatabase();

            $stmt = $pdo->prepare("SELECT * FROM admins WHERE usuario = ? LIMIT 1");
            $stmt->execute([$usuario]);
            $user = $stmt->fetch();

            $isAtivo = !isset($user['ativo']) || $user['ativo'] == 1 || $user['ativo'] === '1';

            if ($user && $isAtivo && password_verify($senha, $user['senha'])) {
                session_regenerate_id(true);
                $_SESSION['usuario']    = $user['usuario'];
                $_SESSION['usuario_id'] = $user['id'];
                header("Location: " . BASE . "/gestor?logado=1");
                exit;
            } else {
                $erro = "Usuário ou senha incorretos";
            }
        } catch (Exception $e) {
            error_log('LOGIN ERRO: ' . $e->getMessage());
            $erro = "Erro interno. Tente novamente.";
        }
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Login - Impakto Mídia</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/public/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/login.css">
</head>
<body>

<div class="login-container">
    <div class="logo">
        <img src="/public/assets/img/logo.png" alt="Impakto Mídia" class="logo-img">
    </div>

    <?php if ($erro): ?>
        <div class="erro">
            <span>⚠️</span>
            <?= htmlspecialchars($erro) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="form-group">
            <input type="text"
                   name="usuario"
                   placeholder="Nome de usuário"
                   required
                   autocomplete="username"
                   value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>" />
        </div>

        <div class="form-group">
            <input type="password"
                   name="senha"
                   placeholder="Senha"
                   required
                   autocomplete="current-password" />
        </div>

        <button type="submit">Entrar no Sistema</button>
    </form>
</div>

</body>
</html>