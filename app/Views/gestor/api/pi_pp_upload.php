<?php
/**
 * POST /gestor/campanhas/documentos/upload
 * Upload e exclusão de documentos financeiros (P.I. / P.P.) de uma campanha.
 */
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();

function docJson($data) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['usuario']))          docJson(['ok' => false, 'erro' => 'nao_logado']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') docJson(['ok' => false, 'erro' => 'metodo_invalido']);

require_once __DIR__ . '/../../../../config/database.php';
$pdo = getDatabase();

$action = trim($_POST['action'] ?? 'upload');

// ── Excluir documento ──────────────────────────────────────────────────────
if ($action === 'excluir') {
    $docId = (int)($_POST['doc_id'] ?? 0);
    if (!$docId) docJson(['ok' => false, 'erro' => 'doc_id_invalido']);

    try {
        $s = $pdo->prepare("SELECT caminho FROM campanha_documentos WHERE id = ? LIMIT 1");
        $s->execute([$docId]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!$row) docJson(['ok' => false, 'erro' => 'documento_nao_encontrado']);

        $pdo->prepare("DELETE FROM campanha_documentos WHERE id = ?")->execute([$docId]);

        $path = __DIR__ . '/../../../../' . $row['caminho'];
        if (file_exists($path)) @unlink($path);

        docJson(['ok' => true]);
    } catch (Exception $e) {
        error_log("pi_pp excluir id=$docId: " . $e->getMessage());
        docJson(['ok' => false, 'erro' => 'db_error']);
    }
}

// ── Upload de documento ─────────────────────────────────────────────────────
$cliente  = trim($_POST['cliente']  ?? '');
$agencia  = trim($_POST['agencia']  ?? '');
$campanha = trim($_POST['campanha'] ?? '');
$inicio   = trim($_POST['inicio']   ?? '') ?: null;
$fim      = trim($_POST['fim']      ?? '') ?: null;
$tipo     = strtoupper(trim($_POST['tipo'] ?? ''));
$usuario  = $_SESSION['usuario'] ?? '';

if (!$cliente) docJson(['ok' => false, 'erro' => 'cliente_invalido']);
if (!in_array($tipo, ['CONTRATO', 'PI', 'PP'], true)) docJson(['ok' => false, 'erro' => 'tipo_invalido']);
if (!isset($_FILES['arquivo'])) docJson(['ok' => false, 'erro' => 'nenhum_arquivo']);

$file = $_FILES['arquivo'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    error_log("pi_pp upload: erro de upload code={$file['error']} nome=" . ($file['name'] ?? '?'));
    docJson(['ok' => false, 'erro' => 'upload_erro_' . $file['error']]);
}

// Valida tamanho (15MB)
if ($file['size'] > 15 * 1024 * 1024) {
    docJson(['ok' => false, 'erro' => 'arquivo_muito_grande']);
}

// Valida MIME real — precisa ser PDF de verdade, não só extensão.
// finfo às vezes classifica PDFs legítimos (de scanner/app de celular) como
// application/octet-stream; nesse caso, confere a assinatura %PDF- como fallback.
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if ($mime !== 'application/pdf') {
    $assinatura = @file_get_contents($file['tmp_name'], false, null, 0, 5);
    if ($assinatura !== '%PDF-') {
        error_log("pi_pp upload: formato_invalido mime=$mime nome=" . ($file['name'] ?? '?'));
        docJson(['ok' => false, 'erro' => 'formato_invalido']);
    }
}

$nomeArq  = 'fotos/pi_pp/' . $tipo . '_' . uniqid() . '.pdf';
$destDir  = __DIR__ . '/../../../../fotos/pi_pp/';
$destPath = __DIR__ . '/../../../../' . $nomeArq;

if (!is_dir($destDir)) {
    mkdir($destDir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    error_log("pi_pp upload: erro_ao_salvar_arquivo destino=$destPath");
    docJson(['ok' => false, 'erro' => 'erro_ao_salvar_arquivo']);
}

try {
    $ins = $pdo->prepare("
        INSERT INTO campanha_documentos (cliente, agencia, campanha, inicio, fim, tipo, caminho, nome_original, criado_por)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $ins->execute([$cliente, $agencia, $campanha, $inicio, $fim, $tipo, $nomeArq, $file['name'], $usuario]);
    $novoId = (int)$pdo->lastInsertId();
} catch (Exception $e) {
    @unlink($destPath);
    error_log("pi_pp insert cliente=$cliente tipo=$tipo: " . $e->getMessage());
    docJson(['ok' => false, 'erro' => 'db_error']);
}

docJson([
    'ok' => true,
    'documento' => [
        'id'            => $novoId,
        'tipo'          => $tipo,
        'caminho'       => $nomeArq,
        'nome_original' => $file['name'],
        'criado_em'     => date('Y-m-d H:i:s'),
    ],
]);
