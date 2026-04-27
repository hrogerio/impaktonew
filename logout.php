<?php
// logout.php
session_start();

// Log do logout se houver usuário logado
if (isset($_SESSION['usuario'])) {
    error_log("Logout realizado para usuário: {$_SESSION['usuario']}");
}

// Limpar TODA a sessão
$_SESSION = array();

// Deletar cookie da sessão se existir
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir a sessão
session_destroy();

// Redirecionar para o login com mensagem
header("Location: /impaktonew/public/index.php?mensagem=logout_sucesso");
exit;