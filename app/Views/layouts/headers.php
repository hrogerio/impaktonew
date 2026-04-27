<?php
// ============================================
// app/Views/layouts/header.php
?>
<header class="topo">
    <div class="header-container">
        <div class="logo">
            <img src="/assets/img/logo.png" alt="Logo Impakto">
        </div>

        <div class="busca-google-central">
            <form method="get" action="/gestor/pontos">
                <input type="text" name="busca" placeholder="Buscar pontos..." 
                       value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>">
                <button type="submit" class="icone-lupa">&#128269;</button>
            </form>
            <div id="search-results" class="search-results"></div>
        </div>

        <div class="acoes">
            <nav class="links-topo">
                <a href="/gestor">Dashboard</a>
                <a href="/gestor/pontos">Pontos</a>
                <a href="/gestor/pre-selecao">Pré-Seleção</a>
            </nav>

            <div class="usuario-menu">
                <span>👋 Bem-vindo, <strong><?= htmlspecialchars($_SESSION['usuario']) ?></strong></span>
                <a href="/logout" class="btn-logout">Sair</a>
            </div>
        </div>
    </div>
</header>