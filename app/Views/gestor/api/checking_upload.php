<?php
/**
 * POST /gestor/campanhas/checking/upload
 * Upload e exclusão de fotos de checking de campanha.
 */
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();

function ckJson($data) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['usuario']))          ckJson(['ok' => false, 'erro' => 'nao_logado']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ckJson(['ok' => false, 'erro' => 'metodo_invalido']);

// CSRF
$csrf = $_POST['csrf_token'] ?? '';
if ($csrf !== ($_SESSION['csrf_token'] ?? '')) ckJson(['ok' => false, 'erro' => 'csrf_invalido']);

require_once __DIR__ . '/../../../../config/database.php';
$pdo = getDatabase();

$action = trim($_POST['action'] ?? 'upload');

// ── Excluir foto ─────────────────────────────────────────────────────────────
if ($action === 'excluir') {
    $fotoId = (int)($_POST['foto_id'] ?? 0);
    if (!$fotoId) ckJson(['ok' => false, 'erro' => 'foto_id_invalido']);

    try {
        $s = $pdo->prepare("SELECT caminho FROM checking_fotos WHERE id = ? LIMIT 1");
        $s->execute([$fotoId]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!$row) ckJson(['ok' => false, 'erro' => 'foto_nao_encontrada']);

        $pdo->prepare("DELETE FROM checking_fotos WHERE id = ?")->execute([$fotoId]);

        // Remove arquivo físico
        $path = __DIR__ . '/../../../../' . $row['caminho'];
        if (file_exists($path)) @unlink($path);

        ckJson(['ok' => true]);
    } catch (Exception $e) {
        error_log("checking excluir id=$fotoId: " . $e->getMessage());
        ckJson(['ok' => false, 'erro' => 'db_error']);
    }
}

// ── Salvar observação da foto ───────────────────────────────────────────────
if ($action === 'salvar_nota') {
    $fotoId = (int)($_POST['foto_id'] ?? 0);
    $nota   = trim($_POST['observacao'] ?? '');
    if (!$fotoId) ckJson(['ok' => false, 'erro' => 'foto_id_invalido']);
    if (mb_strlen($nota) > 300) $nota = mb_substr($nota, 0, 300);

    try {
        $pdo->prepare("UPDATE checking_fotos SET legenda = ? WHERE id = ?")
            ->execute([$nota !== '' ? $nota : null, $fotoId]);
        ckJson(['ok' => true]);
    } catch (Exception $e) {
        error_log("checking salvar_nota id=$fotoId: " . $e->getMessage());
        ckJson(['ok' => false, 'erro' => 'db_error']);
    }
}

// ── Upload de foto ────────────────────────────────────────────────────────────
$pontoId  = (int)($_POST['ponto_id']  ?? 0);
$cliente  = trim($_POST['cliente']  ?? '');
$agencia  = trim($_POST['agencia']  ?? '');
$campanha = trim($_POST['campanha'] ?? '');
$situacao = trim($_POST['situacao'] ?? 'Ocupado');
$inicio   = trim($_POST['inicio']   ?? '') ?: null;
$fim      = trim($_POST['fim']      ?? '') ?: null;
$usuario  = $_SESSION['usuario'] ?? '';

if (!$pontoId) ckJson(['ok' => false, 'erro' => 'ponto_id_invalido']);
if (!isset($_FILES['foto']))  ckJson(['ok' => false, 'erro' => 'nenhum_arquivo']);

$file = $_FILES['foto'];

// Valida erro de upload
if ($file['error'] !== UPLOAD_ERR_OK) {
    ckJson(['ok' => false, 'erro' => 'upload_erro_' . $file['error']]);
}

// Valida tamanho (8MB)
if ($file['size'] > 8 * 1024 * 1024) {
    ckJson(['ok' => false, 'erro' => 'arquivo_muito_grande']);
}

// Valida MIME real
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$exts = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($exts[$mime])) {
    ckJson(['ok' => false, 'erro' => 'formato_invalido']);
}
$ext = $exts[$mime];

// Busca número do ponto para nome do arquivo
try {
    $sp = $pdo->prepare("SELECT numero FROM pontos WHERE id = ? LIMIT 1");
    $sp->execute([$pontoId]);
    $pontoRow = $sp->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { $pontoRow = null; }

$numFmt  = $pontoRow ? str_pad($pontoRow['numero'], 3, '0', STR_PAD_LEFT) : $pontoId;
$nomeArq = 'fotos/checking/P' . $numFmt . '_' . uniqid() . '.' . $ext;
$destDir  = __DIR__ . '/../../../../fotos/checking/';
$destPath = __DIR__ . '/../../../../' . $nomeArq;

// Cria pasta se necessário
if (!is_dir($destDir)) {
    mkdir($destDir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    ckJson(['ok' => false, 'erro' => 'erro_ao_salvar_arquivo']);
}

// Redimensionamento via GD omitido (extensão não disponível no servidor)

// Insere no banco
try {
    $ins = $pdo->prepare("
        INSERT INTO checking_fotos (cliente, agencia, campanha, situacao, inicio, fim, ponto_id, caminho, ordem, criado_por)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
    ");
    $ins->execute([$cliente, $agencia, $campanha, $situacao, $inicio, $fim, $pontoId, $nomeArq, $usuario]);
    $novoId = (int)$pdo->lastInsertId();
} catch (Exception $e) {
    @unlink($destPath);
    error_log("checking insert ponto=$pontoId: " . $e->getMessage());
    ckJson(['ok' => false, 'erro' => 'db_error', 'msg' => $e->getMessage()]);
}

ckJson([
    'ok'   => true,
    'foto' => ['id' => $novoId, 'caminho' => $nomeArq],
]);
