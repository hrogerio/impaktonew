<?php
session_start();

if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    if (isset($_SESSION['usuario'])) {
        error_log("Logout realizado para usuário: {$_SESSION['usuario']}");
    }
    
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    
    header("Location: /impaktonew/public/index.php?mensagem=logout_sucesso");
    exit;
}

if (!isset($_SESSION['usuario'])) {
    header("Location: /impaktonew/public/index.php?erro=nao_logado");
    exit;
}

$loginSucesso = isset($_GET['logado']) && $_GET['logado'] == '1';
$paginaAtual = $_GET['page'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/impaktonew/public/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet">    
    <link rel="stylesheet" href="/impaktonew/public/assets/css/gestor.css"> 
    <title>Dashboard - Impakto Mídia</title>
</head>
<body>

<div class="header">
    <div class="header-content">
        <div class="logo">
            <img src="/impaktonew/public/assets/img/logo.png" alt="Impakto Mídia" class="logo-img">
        </div>
        
        <nav class="main-nav">
            <a href="/impaktonew/gestor/index.php" class="nav-link active">Dashboard</a>
            <a href="/impaktonew/app/Views/gestor/listar_ponto.php" class="nav-link">Pontos</a>
            <a href="/impaktonew/app/Views/gestor/relatorios/pre_selecao.php" class="nav-link">Pré-Seleção</a>
            <a href="/impaktonew/app/Views/gestor/relatorios/pre_selecao.php" class="nav-link">Relatórios</a>
            <a href="#" class="nav-link disabled" title="Em desenvolvimento">Google Maps</a>
        </nav>
        
        <div class="user-info">      
            <a href="?logout=1" class="btn-logout" onclick="return confirm('Tem certeza que deseja sair?')">
                <span class="logout-icon">🚪</span>
                Sair
            </a>
        </div>
    </div>
</div>

<div class="container">
    <?php if ($loginSucesso): ?>
        <div class="success-alert">
            <span style="font-size: 1.5rem;">🎉</span>
            <div>Login realizado com sucesso! Bem-vindo ao sistema de gestão.</div>
        </div>
    <?php endif; ?>
    
    <div class="welcome">
        <h2>Sistema de Gestão de Pontos</h2>
        <p>Gerencie seus pontos de mídia exterior com eficiência e precisão. Acesse as funcionalidades através dos cards abaixo.</p>
    </div>
    
    <div class="quick-menu">
        <div class="menu-card">
            <a href="/impaktonew/app/Views/gestor/listar_ponto.php">
                <div class="icon"></div> 
                <h3>Lista de Pontos</h3>
            </a>
        </div>
        
        <div class="menu-card">
            <a href="/impaktonew/app/Views/gestor/relatorios/pre_selecao.php">
                <div class="icon"></div> 
                <h3>Pré-Seleção</h3>
            </a>
        </div>
        
        <div class="menu-card disabled" title="Em desenvolvimento">
            <div class="icon"></div>
            <h3>Relatórios</h3>
        </div>
        
        <div class="menu-card disabled" title="Em desenvolvimento">
            <div class="icon"></div>
            <h3>Google Maps</h3>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.search.includes('logado=1')) {
        setTimeout(() => {
            const url = new URL(window.location);
            url.searchParams.delete('logado');
            window.history.replaceState({}, '', url);
        }, 3000);
    }
    
    const menuCards = document.querySelectorAll('.menu-card:not(.disabled)');
    menuCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    const links = document.querySelectorAll('a[href]:not([href^="#"]):not([href^="javascript:"])');
    links.forEach(link => {
        link.addEventListener('click', function() {
            if (!this.href.includes('logout')) { 
                document.body.classList.add('loading');
            }
        });
    });
});
</script>

</body>
</html>