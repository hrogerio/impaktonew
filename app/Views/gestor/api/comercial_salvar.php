<?php
/**
 * POST /gestor/comercial/salvar
 * Cria, edita ou exclui itens da Central Comercial (arquivos ou links).
 * Arquivos ficam em fotos/comercial/ (fora do deploy, não são sobrescritos).
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /gestor/comercial");
    exit;
}

// Upload maior que post_max_size chega com $_POST vazio — avisa em vez de dar "token inválido"
if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    header("Location: /gestor/comercial?erro=arquivo_grande");
    exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    die("Token de segurança inválido.");
}

require_once __DIR__ . '/../../../../config/database.php';
$pdo = getDatabase();

$raiz    = realpath(__DIR__ . '/../../../../');
$pastaRel = 'fotos/comercial';
$action  = $_POST['action'] ?? 'salvar';
$empresa = ($_POST['empresa'] ?? '') === 'led' ? 'led' : 'midia';

function comercialVoltar(string $empresa, string $qs): void {
    header("Location: /gestor/comercial?empresa={$empresa}&{$qs}");
    exit;
}

function comercialApagarArquivo(string $raiz, ?string $caminho): void {
    if (!$caminho || strpos($caminho, 'fotos/comercial/') !== 0) return;
    $path = $raiz . '/' . $caminho;
    if (is_file($path)) @unlink($path);
}

// ── Excluir ───────────────────────────────────────────────────────────────
if ($action === 'excluir') {
    $id = (int)($_POST['id'] ?? 0);
    $s = $pdo->prepare("SELECT caminho, empresa FROM comercial_itens WHERE id = ?");
    $s->execute([$id]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $pdo->prepare("DELETE FROM comercial_itens WHERE id = ?")->execute([$id]);
        comercialApagarArquivo($raiz, $row['caminho']);
        $empresa = $row['empresa'];
    }
    comercialVoltar($empresa, 'msg=excluido');
}

// ── Alternar destaque ─────────────────────────────────────────────────────
if ($action === 'destaque') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("UPDATE comercial_itens SET destaque = 1 - destaque WHERE id = ?")->execute([$id]);
    comercialVoltar($empresa, 'msg=atualizado');
}

// ── Criar / editar ────────────────────────────────────────────────────────
$id        = (int)($_POST['id'] ?? 0);
$categoria = mb_substr(trim($_POST['categoria'] ?? ''), 0, 80) ?: 'Outros';
$titulo    = mb_substr(trim($_POST['titulo'] ?? ''), 0, 200);
$descricao = mb_substr(trim($_POST['descricao'] ?? ''), 0, 500);
$tipo      = ($_POST['tipo'] ?? '') === 'link' ? 'link' : 'arquivo';
$url       = trim($_POST['url'] ?? '');
$destaque  = !empty($_POST['destaque']) ? 1 : 0;
$usuario   = $_SESSION['usuario'] ?? '';

if ($titulo === '') comercialVoltar($empresa, 'erro=titulo');

$atual = null;
if ($id) {
    $s = $pdo->prepare("SELECT * FROM comercial_itens WHERE id = ?");
    $s->execute([$id]);
    $atual = $s->fetch(PDO::FETCH_ASSOC);
    if (!$atual) comercialVoltar($empresa, 'erro=nao_encontrado');
}

$caminho = $atual['caminho'] ?? null;
$nomeOriginal = $atual['nome_original'] ?? null;
$tamanho = $atual['tamanho'] ?? null;
$arquivoAntigoParaApagar = null;

if ($tipo === 'link') {
    if (!preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        comercialVoltar($empresa, 'erro=url');
    }
    $url = mb_substr($url, 0, 1000);
    // Virou link: o arquivo antigo (se havia) sai do disco depois de salvar
    $arquivoAntigoParaApagar = $caminho;
    $caminho = $nomeOriginal = $tamanho = null;
} else {
    $url = null;
    $temArquivo = isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] !== UPLOAD_ERR_NO_FILE;

    if (!$temArquivo && !$caminho) comercialVoltar($empresa, 'erro=sem_arquivo');

    if ($temArquivo) {
        $file = $_FILES['arquivo'];
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            comercialVoltar($empresa, 'erro=arquivo_grande');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            error_log("comercial upload: erro code={$file['error']} nome=" . ($file['name'] ?? '?'));
            comercialVoltar($empresa, 'erro=upload');
        }

        $permitidas = ['pdf','jpg','jpeg','png','webp','gif','ppt','pptx','doc','docx','xls','xlsx','csv','zip','mp4','mov','cdr','ai','psd'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $permitidas, true)) comercialVoltar($empresa, 'erro=formato');

        $dir = $raiz . '/' . $pastaRel;
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $novoRel = $pastaRel . '/' . $empresa . '_' . uniqid() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $raiz . '/' . $novoRel)) {
            error_log("comercial upload: falha ao mover para $novoRel");
            comercialVoltar($empresa, 'erro=upload');
        }

        $arquivoAntigoParaApagar = $caminho;
        $caminho      = $novoRel;
        $nomeOriginal = mb_substr($file['name'], 0, 255);
        $tamanho      = (int)$file['size'];
    }
}

try {
    if ($id) {
        $pdo->prepare("
            UPDATE comercial_itens
               SET empresa = ?, categoria = ?, titulo = ?, descricao = ?, tipo = ?,
                   caminho = ?, nome_original = ?, tamanho = ?, url = ?, destaque = ?,
                   atualizado_em = NOW()
             WHERE id = ?
        ")->execute([$empresa, $categoria, $titulo, $descricao, $tipo, $caminho, $nomeOriginal, $tamanho, $url, $destaque, $id]);
    } else {
        $pdo->prepare("
            INSERT INTO comercial_itens
                (empresa, categoria, titulo, descricao, tipo, caminho, nome_original, tamanho, url, destaque, criado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$empresa, $categoria, $titulo, $descricao, $tipo, $caminho, $nomeOriginal, $tamanho, $url, $destaque, $usuario]);
    }
} catch (Exception $e) {
    error_log("comercial salvar: " . $e->getMessage());
    // Se acabou de subir um arquivo novo, não deixa órfão no disco
    if ($caminho && $caminho !== ($atual['caminho'] ?? null)) comercialApagarArquivo($raiz, $caminho);
    comercialVoltar($empresa, 'erro=db');
}

if ($arquivoAntigoParaApagar && $arquivoAntigoParaApagar !== $caminho) {
    comercialApagarArquivo($raiz, $arquivoAntigoParaApagar);
}

comercialVoltar($empresa, 'msg=' . ($id ? 'atualizado' : 'criado'));
