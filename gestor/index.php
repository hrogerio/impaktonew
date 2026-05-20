<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

$loginSucesso = isset($_GET['logado']) && $_GET['logado'] == '1';
$paginaAtual  = 'dashboard';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/public/assets/img/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
    <title>Dashboard - Impakto Mídia</title>
</head>
<body>

<?php require __DIR__ . '/../app/Views/layouts/_nav.php'; ?>

<div class="container">
    <?php if ($loginSucesso): ?>
        <div class="success-alert">
            <div>Login realizado com sucesso! Bem-vindo ao sistema de gestão.</div>
        </div>
    <?php endif; ?>

    <div class="welcome">
        <h2>Sistema de Gestão de Pontos</h2>
        <p>Gerencie seus pontos de mídia exterior com eficiência e precisão. Acesse as funcionalidades através dos cards abaixo.</p>
    </div>

    <div class="quick-menu">
        <div class="menu-card">
            <a href="/gestor/pontos">
                <div class="icon"></div>
                <h3>Pontos / Pré-Seleção</h3>
            </a>
        </div>

        <div class="menu-card">
            <a href="/gestor/relatorios">
                <div class="icon"></div>
                <h3>Relatórios</h3>
            </a>
        </div>

        <div class="menu-card">
            <a href="/gestor/mapa">
                <div class="icon"></div>
                <h3>Google Maps</h3>
            </a>
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
});
</script>

</body>
</html>
