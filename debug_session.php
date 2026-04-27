<?php
// ============================================
// TESTE RÁPIDO: Criar arquivo de debug
// Salvar como: impaktonew/debug_session.php
// ============================================
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Sessão</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .info { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #ffe7e7; padding: 15px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 3px; }
    </style>
</head>
<body>
    <h2>🔍 Debug da Sessão</h2>
    
    <?php
    session_start();
    
    echo "<div class='info'>";
    echo "<strong>Status da Sessão:</strong><br>";
    echo "Session ID: " . session_id() . "<br>";
    echo "Session Status: " . session_status() . "<br>";
    echo "Usuário Logado: " . (isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'NÃO') . "<br>";
    echo "</div>";
    
    echo "<div class='info'>";
    echo "<strong>Dados da Sessão:</strong><br>";
    echo "<pre>" . print_r($_SESSION, true) . "</pre>";
    echo "</div>";
    
    echo "<div class='info'>";
    echo "<strong>Cookies:</strong><br>";
    echo "<pre>" . print_r($_COOKIE, true) . "</pre>";
    echo "</div>";
    ?>
    
    <div style="margin-top: 20px;">
        <a href="/impaktonew/gestor/" style="padding: 10px 15px; background: #007cba; color: white; text-decoration: none; border-radius: 3px;">← Voltar ao Gestor</a>
        <a href="/impaktonew/logout.php" style="padding: 10px 15px; background: #dc3545; color: white; text-decoration: none; border-radius: 3px;">🚪 Fazer Logout</a>
        <a href="/impaktonew/public/" style="padding: 10px 15px; background: #28a745; color: white; text-decoration: none; border-radius: 3px;">🔑 Ir ao Login</a>
    </div>
</body>
</html>