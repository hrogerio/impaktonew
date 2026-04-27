<?php
session_start();

// Verificar se já está logado
if (isset($_SESSION['usuario'])) {
    header("Location: /impaktonew/gestor/");
    exit;
}

// INICIALIZAR VARIÁVEIS
$erro = false;
$debug_info = [];

// PROCESSAR LOGIN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($usuario) || empty($senha)) {
        $erro = "Usuário e senha são obrigatórios";
    } else {
        try {
            require_once __DIR__ . '/../config/database.php';
            $pdo = getDatabase();
            
            // Usar SELECT * para evitar problemas com colunas
            $sql = "SELECT * FROM admins WHERE usuario = ? LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$usuario]);
            $user = $stmt->fetch();
            
            // Verificar ativo (se coluna existir)
            $isAtivo = !isset($user['ativo']) || $user['ativo'] == 1 || $user['ativo'] === '1';
            
            if ($user && $isAtivo && password_verify($senha, $user['senha'])) {
                session_regenerate_id(true);
                $_SESSION['usuario'] = $user['usuario'];
                $_SESSION['usuario_id'] = $user['id'];
                
                header("Location: /impaktonew/gestor/?logado=1");
                exit;
            } else {
                $erro = "Usuário ou senha incorretos";
            }
            
        } catch (Exception $e) {
            $erro = "Erro de conexão: " . $e->getMessage();
            $debug_info['erro'] = $e->getMessage();
        }
    }
}

// Gerar token CSRF
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
    <link rel="icon" href="/impaktonew/public/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet">    
    <link rel="stylesheet" href="/impaktonew/public/assets/css/login.css"> 
  
</head>
<body>

<div class="login-container">
    <div class="logo">
    <img src="/impaktonew/public/assets/img/logo.png" alt="Impakto Mídia" class="logo-img">
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
        
    <?php if (!empty($debug_info)): ?>
    <div class="debug-info">
        <h4>🔍 Debug:</h4>
        <pre><?= htmlspecialchars(print_r($debug_info, true)) ?></pre>
    </div>
    <?php endif; ?>
</div>

</body>
</html>