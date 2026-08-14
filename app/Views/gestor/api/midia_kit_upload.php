<?php
/**
 * POST /gestor/midia-kit/upload
 * Upload de foto para um case do mídia kit.
 */
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();

function mkJson($data) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['usuario']))          mkJson(['ok' => false, 'erro' => 'nao_logado']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') mkJson(['ok' => false, 'erro' => 'metodo_invalido']);

// CSRF
$csrf = $_POST['csrf_token'] ?? '';
if ($csrf !== ($_SESSION['csrf_token'] ?? '')) mkJson(['ok' => false, 'erro' => 'csrf_invalido']);

if (!isset($_FILES['foto'])) mkJson(['ok' => false, 'erro' => 'nenhum_arquivo']);

$file = $_FILES['foto'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    mkJson(['ok' => false, 'erro' => 'upload_erro_' . $file['error']]);
}

if ($file['size'] > 8 * 1024 * 1024) {
    mkJson(['ok' => false, 'erro' => 'arquivo_muito_grande']);
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$exts = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($exts[$mime])) {
    mkJson(['ok' => false, 'erro' => 'formato_invalido']);
}
$ext = $exts[$mime];

$nomeArq  = 'fotos/midia_kit/' . uniqid() . '.' . $ext;
$destDir  = __DIR__ . '/../../../../fotos/midia_kit/';
$destPath = __DIR__ . '/../../../../' . $nomeArq;

if (!is_dir($destDir)) {
    mkdir($destDir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    mkJson(['ok' => false, 'erro' => 'erro_ao_salvar_arquivo']);
}

mkJson(['ok' => true, 'caminho' => $nomeArq]);
