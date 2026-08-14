<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

$paginaAtual  = 'backup';
$backupDir    = __DIR__ . '/../../../storage/backups/';
$mensagem     = '';
$erro         = '';

try {
    require_once __DIR__ . '/../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("Erro na conexão: " . htmlspecialchars($e->getMessage()));
}

function gerarSQL(PDO $pdo): string {
    $usuario = $_SESSION['usuario'] ?? 'sistema';
    $out  = "-- ============================================================\n";
    $out .= "-- Backup Impakto Mídia\n";
    $out .= "-- Gerado em: " . date('d/m/Y H:i:s') . "\n";
    $out .= "-- Usuário: " . $usuario . "\n";
    $out .= "-- ============================================================\n\n";
    $out .= "SET NAMES utf8mb4;\n";
    $out .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
        $out .= "-- ------------------------------------------------------------\n";
        $out .= "-- Tabela: `{$table}`\n";
        $out .= "-- ------------------------------------------------------------\n";
        $out .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $out .= $create[1] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
            $vals = [];
            foreach ($rows as $row) {
                $v = array_map(function($x) use ($pdo) { return $x === null ? 'NULL' : $pdo->quote($x); }, array_values($row));
                $vals[] = '(' . implode(', ', $v) . ')';
            }
            $out .= "INSERT INTO `{$table}` ({$cols}) VALUES\n" . implode(",\n", $vals) . ";\n\n";
        }
    }

    $out .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    return $out;
}

function nomeArquivoSeguro(string $nome): bool {
    return (bool) preg_match('/^backup_impakto_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $nome);
}

// ── Ação: baixar direto ──────────────────────────────────────────
if (isset($_GET['acao']) && $_GET['acao'] === 'baixar') {
    $sql      = gerarSQL($pdo);
    $filename = 'backup_impakto_' . date('Y-m-d_H-i-s') . '.sql';
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($sql));
    echo $sql;
    exit;
}

// ── Ação: baixar arquivo salvo ───────────────────────────────────
if (isset($_GET['acao']) && $_GET['acao'] === 'dl' && isset($_GET['f'])) {
    $nome = basename($_GET['f']);
    if (nomeArquivoSeguro($nome) && file_exists($backupDir . $nome)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $nome . '"');
        readfile($backupDir . $nome);
        exit;
    }
    die("Arquivo não encontrado.");
}

// ── Ação: salvar no servidor ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar') {
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    $sql      = gerarSQL($pdo);
    $filename = 'backup_impakto_' . date('Y-m-d_H-i-s') . '.sql';
    if (file_put_contents($backupDir . $filename, $sql) !== false) {
        $mensagem = "Backup <strong>{$filename}</strong> salvo com sucesso.";
    } else {
        $erro = "Erro ao salvar. Verifique as permissões da pasta storage/backups/.";
    }
}

// ── Ação: deletar arquivo salvo ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'deletar') {
    $nome = basename($_POST['arquivo'] ?? '');
    if (nomeArquivoSeguro($nome)) {
        $path = $backupDir . $nome;
        if (file_exists($path) && unlink($path)) {
            $mensagem = "Backup <strong>{$nome}</strong> removido.";
        } else {
            $erro = "Não foi possível remover o arquivo.";
        }
    }
}

// ── Listar backups salvos ────────────────────────────────────────
$backups = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . '*.sql');
    if ($files) {
        rsort($files);
        foreach ($files as $f) {
            $backups[] = [
                'nome'     => basename($f),
                'tamanho'  => filesize($f),
                'data'     => filemtime($f),
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup do Banco · SGI</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
    <link rel="stylesheet" href="/public/assets/css/backup.css">
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="container" style="padding-bottom:2rem;">

    <div class="welcome" style="margin-bottom:1.5rem;">
        <h2>💾 Backup do Banco de Dados</h2>
        <p>Gere e gerencie backups completos da base de dados em formato SQL.</p>
    </div>

    <?php if ($mensagem): ?>
        <div class="alerta alerta-ok">✅ <?= $mensagem ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alerta alerta-err">❌ <?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <!-- Ações principais -->
    <div class="backup-actions">
        <a href="/gestor/backup?acao=baixar" class="btn-backup btn-baixar">
            ⬇️ Baixar .sql agora
        </a>
        <form method="POST" action="/gestor/backup" style="margin:0;">
            <input type="hidden" name="acao" value="salvar">
            <button type="submit" class="btn-backup btn-salvar">
                💾 Salvar no servidor
            </button>
        </form>
    </div>

    <!-- Backups salvos -->
    <div class="table-container">
        <table class="backup-table">
            <thead>
                <tr>
                    <th>Arquivo</th>
                    <th>Tamanho</th>
                    <th>Data</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($backups)): ?>
                <tr>
                    <td colspan="4" class="backup-empty">
                        Nenhum backup salvo no servidor ainda.<br>
                        Use "Salvar no servidor" para guardar uma cópia local.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($backups as $b): ?>
                <tr>
                    <td class="backup-nome"><?= htmlspecialchars($b['nome']) ?></td>
                    <td class="backup-size"><?= number_format($b['tamanho'] / 1024, 1) ?> KB</td>
                    <td class="backup-size"><?= date('d/m/Y H:i', $b['data']) ?></td>
                    <td style="display:flex;gap:0.5rem;align-items:center;">
                        <a href="/gestor/backup?acao=dl&f=<?= urlencode($b['nome']) ?>" class="btn-dl">⬇️ Baixar</a>
                        <form method="POST" action="/gestor/backup" style="margin:0;"
                              onsubmit="return confirm('Remover <?= htmlspecialchars($b['nome']) ?>?')">
                            <input type="hidden" name="acao" value="deletar">
                            <input type="hidden" name="arquivo" value="<?= htmlspecialchars($b['nome']) ?>">
                            <button type="submit" class="btn-del">🗑 Remover</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
