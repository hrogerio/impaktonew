<?php
$paginaAtual = $paginaAtual ?? '';
?>
<style>
.nav-gear-wrap { position: relative; }
.nav-gear-btn {
    background: none; border: 1px solid transparent; cursor: pointer;
    font-size: 1.1rem; padding: 0.35rem 0.6rem; border-radius: 8px;
    color: var(--color-text-muted); line-height: 1;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.nav-gear-btn:hover,
.nav-gear-btn.aberto { background: #f3f4f6; color: var(--color-text-dark); }
.nav-gear-btn.aberto-admin { background: #fff1f0; color: var(--color-accent-primary); }
.nav-gear-dropdown {
    display: none; position: absolute; right: 0; top: calc(100% + 8px);
    background: white; border: 1.5px solid var(--color-border);
    border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    min-width: 170px; z-index: 500; overflow: hidden;
}
.nav-gear-dropdown.aberto { display: block; }
.nav-gear-sep {
    font-size: 0.65rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.06em; color: var(--color-text-muted);
    padding: 0.5rem 1rem 0.2rem;
}
.nav-gear-link {
    display: flex; align-items: center; gap: 0.6rem;
    padding: 0.6rem 1rem; font-size: 0.83rem; font-weight: 600;
    color: var(--color-text-dark); text-decoration: none;
    transition: background 0.12s;
}
.nav-gear-link:hover { background: #f9fafb; }
.nav-gear-link.active { color: var(--color-accent-primary); font-weight: 700; }
.nav-gear-divider { height: 1px; background: var(--color-border); margin: 0.25rem 0; }
.nav-saudacao {
    font-size: 0.83rem; font-weight: 600; color: var(--color-text-muted);
    white-space: nowrap;
    display: flex; align-items: center; gap: 0.55rem;
}
.nav-saudacao strong { color: var(--color-text-dark); font-weight: 700; }
.nav-avatar {
    width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, var(--color-accent-primary), #ff8a70);
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 0.78rem;
}
</style>

<div class="header">
    <div class="header-content">
        <div class="logo">
            <img src="/public/assets/img/logo.png" alt="Impakto Mídia OOH" class="logo-img">
            <span class="logo-subtitle">Sistema de Gestão Integrada</span>
        </div>

        <nav class="main-nav">
            <a href="/gestor"            class="nav-link<?= $paginaAtual === 'dashboard'  ? ' active' : '' ?>">Dashboard</a>
            <a href="/gestor/pontos"     class="nav-link<?= $paginaAtual === 'pontos'     ? ' active' : '' ?>">Pontos</a>
            <a href="/gestor/reservas"   class="nav-link<?= $paginaAtual === 'reservas'   ? ' active' : '' ?>">Reservas</a>
            <a href="/gestor/campanhas"  class="nav-link<?= $paginaAtual === 'campanhas'  ? ' active' : '' ?>">Campanhas</a>
            <a href="/gestor/relatorios" class="nav-link<?= in_array($paginaAtual, ['relatorios', 'clientes']) ? ' active' : '' ?>">Relatórios</a>
            <a href="/gestor/mapa"       class="nav-link<?= $paginaAtual === 'mapa'       ? ' active' : '' ?>">Mapa</a>
        </nav>

        <div class="user-info">
            <?php
            $nomeUsuario = $_SESSION['usuario'] ?? '';
            $iniciaisUsuario = strtoupper(mb_substr(trim($nomeUsuario), 0, 1)) ?: '?';
            ?>
            <span class="nav-saudacao">
                <span class="nav-avatar"><?= htmlspecialchars($iniciaisUsuario) ?></span>
                Olá, <strong><?= htmlspecialchars($nomeUsuario) ?></strong>
            </span>

            <!-- ⚙ Dropdown Auditoria / Backup -->
            <?php
            $paginasAdmin = ['auditoria', 'backup', 'usuarios', 'logs-acesso', 'midia-kit', 'pontos-inativos'];
            $isAdmin = in_array($paginaAtual, $paginasAdmin);
            $souAdmin = ($_SESSION['usuario_role'] ?? 'admin') === 'admin';
            ?>
            <div class="nav-gear-wrap" id="navGearWrap">
                <button class="nav-gear-btn<?= $isAdmin ? ' aberto-admin' : '' ?>"
                        id="navGearBtn"
                        onclick="toggleGear()"
                        title="Administração">⚙</button>
                <div class="nav-gear-dropdown<?= $isAdmin ? ' aberto' : '' ?>" id="navGearDropdown">
                    <div class="nav-gear-sep">Administração</div>
                    <a href="/gestor/auditoria" class="nav-gear-link<?= $paginaAtual === 'auditoria' ? ' active' : '' ?>">
                        📊 Auditoria
                    </a>
                    <a href="/gestor/midia-kit" class="nav-gear-link<?= $paginaAtual === 'midia-kit' ? ' active' : '' ?>">
                        🖼️ Mídia Kit
                    </a>
                    <a href="/gestor/pontos-inativos" class="nav-gear-link<?= $paginaAtual === 'pontos-inativos' ? ' active' : '' ?>">
                        ⏸ Pontos Inativos
                    </a>
                    <?php if ($souAdmin): ?>
                    <div class="nav-gear-divider"></div>
                    <a href="/gestor/usuarios" class="nav-gear-link<?= $paginaAtual === 'usuarios' ? ' active' : '' ?>">
                        👤 Usuários
                    </a>
                    <a href="/gestor/logs-acesso" class="nav-gear-link<?= $paginaAtual === 'logs-acesso' ? ' active' : '' ?>">
                        🛡️ Log de Acessos
                    </a>
                    <div class="nav-gear-divider"></div>
                    <a href="/gestor/backup" class="nav-gear-link<?= $paginaAtual === 'backup' ? ' active' : '' ?>">
                        💾 Backup BD
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <a href="/logout" class="btn-logout"
               onclick="return confirm('Tem certeza que deseja sair?')">Sair</a>
        </div>
    </div>
</div>

<script>
function toggleGear() {
    var dd  = document.getElementById('navGearDropdown');
    var btn = document.getElementById('navGearBtn');
    var open = dd.classList.toggle('aberto');
    btn.classList.toggle('aberto', open);
}
document.addEventListener('click', function(e) {
    var wrap = document.getElementById('navGearWrap');
    if (wrap && !wrap.contains(e.target)) {
        document.getElementById('navGearDropdown').classList.remove('aberto');
        document.getElementById('navGearBtn').classList.remove('aberto');
    }
});
</script>
