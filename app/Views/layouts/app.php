<?php
// app/Views/layouts/app.php - Layout principal
$appEnv = getenv('APP_ENV') ?: 'production';
$isProducao = $appEnv === 'production';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isProducao ? '' : '[LOCAL] ' ?><?= $title ?? 'Impakto' ?></title>

    <!-- CSS -->
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/assets/css/gestor.css">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">
    
    <!-- Meta tags SEO -->
    <meta name="description" content="Sistema de gestão de pontos de mídia exterior">
    <meta name="robots" content="noindex, nofollow">
</head>
<body>
    <?php if (!$isProducao): ?>
        <div style="position:sticky;top:0;z-index:9999;background:#d97706;color:#fff;text-align:center;font:700 13px/1 -apple-system,Segoe UI,sans-serif;padding:6px 8px;letter-spacing:.03em;">
            ⚠️ AMBIENTE LOCAL (<?= htmlspecialchars($appEnv) ?>) — não é produção
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['usuario'])): ?>
        <?php include __DIR__ . '/header.php'; ?>
    <?php endif; ?>
    
    <main class="main-content">
        <?php echo $content ?? ''; ?>
    </main>
    
    <?php if (isset($_SESSION['usuario'])): ?>
        <?php include __DIR__ . '/footer.php'; ?>
    <?php endif; ?>
    
    <!-- JavaScript -->
    <script src="/assets/js/app.js"></script>
    
    <!-- Flash Messages -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_SESSION['flash_success'])): ?>
            NotificationSystem.show('<?= addslashes($_SESSION['flash_success']) ?>', 'success');
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['flash_error'])): ?>
            NotificationSystem.show('<?= addslashes($_SESSION['flash_error']) ?>', 'error');
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['flash_warning'])): ?>
            NotificationSystem.show('<?= addslashes($_SESSION['flash_warning']) ?>', 'warning');
            <?php unset($_SESSION['flash_warning']); ?>
        <?php endif; ?>
    });
    </script>
</body>
</html>
