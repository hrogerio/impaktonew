<?php
// Faixa de aviso exibida em toda tela quando o sistema não está em produção.
$appEnv = getenv('APP_ENV') ?: 'production';
if ($appEnv !== 'production'):
?>
<div style="position:sticky;top:0;z-index:9999;background:#d97706;color:#fff;text-align:center;font:700 13px/1 -apple-system,Segoe UI,sans-serif;padding:6px 8px;letter-spacing:.03em;">
    ⚠️ AMBIENTE LOCAL (<?= htmlspecialchars($appEnv) ?>) — não é produção
</div>
<?php endif; ?>
