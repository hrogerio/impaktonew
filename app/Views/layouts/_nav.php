<?php
// Partial reutilizável do header/nav
// Requer $paginaAtual para destacar o link ativo
$paginaAtual = $paginaAtual ?? '';
?>
<div class="header">
    <div class="header-content">
        <div class="logo">
            <img src="/public/assets/img/logo.png" alt="Impakto Mídia" class="logo-img">
        </div>
        <nav class="main-nav">
            <a href="/gestor"            class="nav-link<?= $paginaAtual === 'dashboard'   ? ' active' : '' ?>">Dashboard</a>
            <a href="/gestor/pontos"     class="nav-link<?= $paginaAtual === 'pontos'      ? ' active' : '' ?>">Pontos</a>
            <a href="/gestor/relatorios" class="nav-link<?= $paginaAtual === 'relatorios'  ? ' active' : '' ?>">Relatórios</a>
            <a href="/gestor/auditoria"  class="nav-link<?= $paginaAtual === 'auditoria'   ? ' active' : '' ?>">Auditoria</a>
            <a href="/gestor/backup"     class="nav-link<?= $paginaAtual === 'backup'      ? ' active' : '' ?>">Backup BD</a>
            <a href="#" class="nav-link disabled" title="Em desenvolvimento">Google Maps</a>
        </nav>
        <div class="user-info">
            <a href="/logout" class="btn-logout" onclick="return confirm('Tem certeza que deseja sair?')">Sair</a>
        </div>
    </div>
</div>
