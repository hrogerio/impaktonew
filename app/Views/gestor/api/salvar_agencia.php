<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /gestor/agencias");
    exit;
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    die("Token de segurança inválido.");
}

require_once __DIR__ . '/../../../../config/database.php';
$pdo = getDatabase();

$id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$nome        = trim($_POST['nome'] ?? '');
$endereco    = trim($_POST['endereco'] ?? '');
$telefone    = trim($_POST['telefone'] ?? '');
$observacoes = trim($_POST['observacoes'] ?? '');

$voltarBruto = $_POST['voltar'] ?? '';
$voltarPath  = parse_url($voltarBruto, PHP_URL_PATH) ?: '';
$voltarUrl   = str_starts_with($voltarPath, '/gestor/') ? $voltarBruto : '/gestor/agencias';

function voltarComErroAgencia($msg, $id) {
    $url = $id > 0 ? "/gestor/agencias/editar?id={$id}" : "/gestor/agencias/novo";
    header("Location: {$url}&erro=" . urlencode($msg));
    exit;
}

if ($nome === '') {
    voltarComErroAgencia("Nome é obrigatório.", $id);
}

// ── Upload da logomarca (opcional) ──────────────────────────────────────────
$logoPath = null;
if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['logo'];

    if ($file['size'] > 2 * 1024 * 1024) {
        voltarComErroAgencia("Logomarca muito grande (máx. 2 MB).", $id);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $ext = $extMap[$mime] ?? null;

    // SVG às vezes é detectado como text/plain ou text/html pelo finfo — confere
    // a assinatura do conteúdo como fallback, mas nunca serve inline (sempre via <img>).
    if ($ext === null) {
        $inicio = @file_get_contents($file['tmp_name'], false, null, 0, 512);
        if ($inicio !== false && stripos($inicio, '<svg') !== false) {
            $ext = 'svg';
        }
    }

    if ($ext === null) {
        voltarComErroAgencia("Formato de logo inválido. Use PNG, JPG, WEBP ou SVG.", $id);
    }

    $nomeArq = 'fotos/agencias/logo_' . uniqid() . '.' . $ext;
    $destDir = __DIR__ . '/../../../../fotos/agencias/';
    $destino = __DIR__ . '/../../../../' . $nomeArq;

    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }
    if (!move_uploaded_file($file['tmp_name'], $destino)) {
        voltarComErroAgencia("Falha ao salvar a logomarca.", $id);
    }
    $logoPath = $nomeArq;
}

// ── Diretoria / Mídia ────────────────────────────────────────────────────────
function coletarContatos(string $prefixo): array {
    $nomes  = $_POST[$prefixo . '_nome']  ?? [];
    $emails = $_POST[$prefixo . '_email'] ?? [];
    $lista = [];
    foreach ($nomes as $i => $nome) {
        $nome  = trim($nome);
        $email = trim($emails[$i] ?? '');
        if ($nome === '') continue;
        $lista[] = ['nome' => $nome, 'email' => $email !== '' ? mb_strtolower($email) : null];
    }
    return $lista;
}
$diretoria = coletarContatos('diretoria');
$midia     = coletarContatos('midia');

foreach (array_merge($diretoria, $midia) as $c) {
    if ($c['email'] !== null && !filter_var($c['email'], FILTER_VALIDATE_EMAIL)) {
        voltarComErroAgencia("E-mail inválido: {$c['email']}", $id);
    }
}

try {
    $pdo->beginTransaction();

    if ($id === 0) {
        $stmt = $pdo->prepare("INSERT INTO agencias (nome, endereco, telefone, logo, observacoes, ativo, criado_por)
                                VALUES (?, ?, ?, ?, ?, 1, ?)");
        $stmt->execute([
            $nome,
            $endereco !== '' ? $endereco : null,
            $telefone !== '' ? $telefone : null,
            $logoPath,
            $observacoes !== '' ? $observacoes : null,
            $_SESSION['usuario'] ?? null,
        ]);
        $id = (int)$pdo->lastInsertId();
        $msg = 'criado';

        // Liga retroativamente campanhas que já usam esse nome em texto livre
        $pdo->prepare("UPDATE campanhas SET agencia_id = ? WHERE agencia_id IS NULL AND LOWER(TRIM(agencia)) = LOWER(TRIM(?))")
            ->execute([$id, $nome]);
    } else {
        $stmtChk = $pdo->prepare("SELECT logo FROM agencias WHERE id = ? LIMIT 1");
        $stmtChk->execute([$id]);
        $atual = $stmtChk->fetch(PDO::FETCH_ASSOC);
        if (!$atual) {
            $pdo->rollBack();
            header("Location: /gestor/agencias");
            exit;
        }

        if ($logoPath === null) {
            $logoPath = $atual['logo'];
        } elseif (!empty($atual['logo'])) {
            $antigo = __DIR__ . '/../../../../' . $atual['logo'];
            if (file_exists($antigo)) @unlink($antigo);
        }

        $stmt = $pdo->prepare("UPDATE agencias SET nome = ?, endereco = ?, telefone = ?, logo = ?, observacoes = ? WHERE id = ?");
        $stmt->execute([
            $nome,
            $endereco !== '' ? $endereco : null,
            $telefone !== '' ? $telefone : null,
            $logoPath,
            $observacoes !== '' ? $observacoes : null,
            $id,
        ]);
        $msg = 'atualizado';
    }

    $pdo->prepare("DELETE FROM agencia_contatos WHERE agencia_id = ?")->execute([$id]);
    $insC = $pdo->prepare("INSERT INTO agencia_contatos (agencia_id, tipo, nome, email, ordem) VALUES (?, ?, ?, ?, ?)");
    foreach ($diretoria as $i => $c) {
        $insC->execute([$id, 'diretoria', $c['nome'], $c['email'], $i]);
    }
    foreach ($midia as $i => $c) {
        $insC->execute([$id, 'midia', $c['nome'], $c['email'], $i]);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("salvar_agencia id={$id}: " . $e->getMessage());
    voltarComErroAgencia("Erro ao salvar a agência.", $id);
}

$hashPos = strpos($voltarUrl, '#');
$base = $hashPos !== false ? substr($voltarUrl, 0, $hashPos) : $voltarUrl;
$hash = $hashPos !== false ? substr($voltarUrl, $hashPos) : '';
$separador = strpos($base, '?') !== false ? '&' : '?';
header("Location: {$base}{$separador}msg=" . urlencode($msg) . $hash);
exit;
