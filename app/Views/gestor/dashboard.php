<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Impakto Mídia</title>
    <style>
        /* Usar o mesmo estilo do gestor/index.php */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif; 
            background: #f8f9fa;
            min-height: 100vh;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #ee8170ff 0%, #f40b0bff 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            margin-bottom: 2rem;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 1.5rem;
        }
        
        .logo h1 { 
            color: white; 
            font-size: 1.8rem; 
            font-weight: 300;
            letter-spacing: -1px;
        }
        .logo .red { color: #ffeb3b; font-weight: 700; }
        
        .nav-links {
            display: flex;
            gap: 1rem;
        }
        
        .nav-links a {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            padding: 0.6rem 1.2rem;
            border-radius: 20px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .nav-links a:hover, .nav-links a.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .user-info {
            color: rgba(255,255,255,0.9);
            font-size: 0.9rem;
        }
        
        .btn-logout {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 20px;
            text-decoration: none;
            margin-left: 1rem;
            font-weight: 600;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }
        
        .welcome {
            background: white;
            padding: 3rem;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            margin-bottom: 3rem;
            text-align: center;
        }
        
        .quick-menu {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }
        
        .menu-card {
            background: white;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            text-align: center;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }
        
        .menu-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .menu-card a {
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .menu-card .icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            display: block;
        }
        
        .menu-card h3 {
            margin-bottom: 0.8rem;
            color: #2c3e50;
            font-size: 1.3rem;
            font-weight: 600;
        }
        
        .menu-card p {
            color: #7f8c8d;
            font-size: 0.95rem;
            line-height: 1.5;
        }
    </style>
</head>
<body>

<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: ../public/index.php?erro=nao_logado");
    exit;
}
?>

<div class="header">
    <div class="header-content">
        <div class="logo">
            <h1>impa<span class="red">k</span>to</h1>
        </div>
        
        <div class="nav-links">
            <a href="index.php" class="active">
                <span style="margin-right: 8px;">🏠</span>Dashboard
            </a>
            <a href="../app/Views/gestor/listar_ponto.php">
                <span style="margin-right: 8px;">📋</span>Lista de Pontos
            </a>
            <a href="../app/Views/gestor/relatorios/pre_selecao.php">
                <span style="margin-right: 8px;">📊</span>Pré-Seleção
            </a>
        </div>
        
        <div class="user-info">
            Olá, <strong><?= htmlspecialchars($_SESSION['usuario']) ?></strong>
            <a href="../public/index.php?logout=1" class="btn-logout">Sair</a>
        </div>
    </div>
</div>

<div class="container">
    <div class="welcome">
        <h2>🎯 Dashboard - Sistema de Gestão</h2>
        <p>Bem-vindo ao painel de controle. Gerencie seus pontos de mídia com eficiência.</p>
    </div>
    
    <div class="quick-menu">
        <div class="menu-card">
            <a href="../app/Views/gestor/listar_ponto.php">
                <div class="icon">📋</div>
                <h3>Lista de Pontos</h3>
                <p>Visualizar e gerenciar todos os pontos cadastrados com filtros avançados</p>
            </a>
        </div>
        
        <div class="menu-card">
            <a href="../app/Views/gestor/relatorios/pre_selecao.php">
                <div class="icon">📊</div>
                <h3>Pré-Seleção</h3>
                <p>Fazer pré-seleção de pontos através da numeração específica</p>
            </a>
        </div>
        
        <div class="menu-card" style="opacity: 0.6;">
            <div class="icon">📈</div>
            <h3>Relatórios</h3>
            <p>Relatórios avançados e estatísticas (em desenvolvimento)</p>
        </div>
        
        <div class="menu-card" style="opacity: 0.6;">
            <div class="icon">⚙️</div>
            <h3>Configurações</h3>
            <p>Configurações do sistema (em desenvolvimento)</p>
        </div>
    </div>
</div>

</body>
</html>
