<?php
// app/Views/layouts/app.php - Layout principal
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Sistema de Gestão - Impakto' ?></title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/assets/css/gestor.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/img/favicon.ico">
    
    <!-- Meta tags SEO -->
    <meta name="description" content="Sistema de gestão de pontos de mídia exterior">
    <meta name="robots" content="noindex, nofollow">
</head>
<body>
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
