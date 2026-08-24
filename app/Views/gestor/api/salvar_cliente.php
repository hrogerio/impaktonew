<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /gestor/clientes");
    exit;
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    die("Token de segurança inválido.");
}

require_once __DIR__ . '/../../../../config/database.php';
$pdo = getDatabase();

// Edição inline (ex: modal em Relatórios > Clientes) — atualiza só Razão Social,
// Nome Fantasia, CNPJ e E-mail, sem sair da página, e responde em JSON.
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

$id           = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$razaoSocialBruta = trim($_POST['razao_social'] ?? '');
$nomeFantasiaBruto = trim($_POST['nome_fantasia'] ?? '');
$cnpj         = trim($_POST['cnpj'] ?? '');
$endereco     = trim($_POST['endereco'] ?? '');
$email        = mb_strtolower(trim($_POST['email'] ?? ''));
$telefone     = trim($_POST['telefone'] ?? '');
$observacoes  = trim($_POST['observacoes'] ?? '');

// Sem auto-formatação de maiúsculas/minúsculas: salva exatamente como foi digitado.
// Uma heurística de "nome próprio" nunca acerta siglas em geral (ex: ACLF, ADTSA, AGL
// têm vogal e escapavam da detecção de sigla) — melhor confiar em quem está digitando.
$contato      = trim($_POST['contato'] ?? '');
$razaoSocial  = $razaoSocialBruta;
$nomeFantasia = $nomeFantasiaBruto;

function voltarComErroCliente($msg, $id) {
    $url = $id > 0 ? "/gestor/clientes/editar?id={$id}" : "/gestor/clientes/novo";
    header("Location: {$url}&erro=" . urlencode($msg));
    exit;
}

function erroCliente(bool $isAjax, string $msg, int $id): void {
    if ($isAjax) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'erro' => $msg]);
        exit;
    }
    voltarComErroCliente($msg, $id);
}

// Pra onde voltar depois de salvar (de onde o usuário veio, ex: Relatórios > Clientes),
// restrito a páginas internas do próprio gestor por segurança.
$voltarBruto = $_POST['voltar'] ?? '';
$voltarPath  = parse_url($voltarBruto, PHP_URL_PATH) ?: '';
$voltarUrl   = str_starts_with($voltarPath, '/gestor/') ? $voltarBruto : '/gestor/clientes';

function redirecionarComMsg(string $url, string $msg): void {
    $hashPos = strpos($url, '#');
    $base = $hashPos !== false ? substr($url, 0, $hashPos) : $url;
    $hash = $hashPos !== false ? substr($url, $hashPos) : '';
    $separador = strpos($base, '?') !== false ? '&' : '?';
    header("Location: {$base}{$separador}msg=" . urlencode($msg) . $hash);
    exit;
}

if ($razaoSocial === '') {
    erroCliente($isAjax, "Razão social é obrigatória.", $id);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    erroCliente($isAjax, "E-mail inválido.", $id);
}

// ── Upload da logomarca (opcional) ──────────────────────────────────────────
$logoPath = null;
if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['logo'];

    if ($file['size'] > 2 * 1024 * 1024) {
        erroCliente($isAjax, "Logomarca muito grande (máx. 2 MB).", $id);
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
        erroCliente($isAjax, "Formato de logo inválido. Use PNG, JPG, WEBP ou SVG.", $id);
    }

    $nomeArq = 'fotos/clientes/logo_' . uniqid() . '.' . $ext;
    $destDir = __DIR__ . '/../../../../fotos/clientes/';
    $destino = __DIR__ . '/../../../../' . $nomeArq;

    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }
    if (!move_uploaded_file($file['tmp_name'], $destino)) {
        erroCliente($isAjax, "Falha ao salvar a logomarca.", $id);
    }
    $logoPath = $nomeArq;
}

if ($isAjax) {
    if ($id === 0) {
        erroCliente($isAjax, "Cliente inválido.", $id);
    }
    $stmt = $pdo->prepare("SELECT logo FROM clientes WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $atual = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$atual) {
        erroCliente($isAjax, "Cliente não encontrado.", $id);
    }

    if ($logoPath === null) {
        $logoPath = $atual['logo'];
    } elseif (!empty($atual['logo'])) {
        $antigo = __DIR__ . '/../../../../' . $atual['logo'];
        if (file_exists($antigo)) @unlink($antigo);
    }

    $pdo->prepare("UPDATE clientes SET razao_social = ?, nome_fantasia = ?, logo = ?, cnpj = ?, email = ? WHERE id = ?")
        ->execute([$razaoSocial, $nomeFantasia !== '' ? $nomeFantasia : null, $logoPath, $cnpj !== '' ? $cnpj : null, $email !== '' ? $email : null, $id]);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'cliente' => [
            'id' => $id,
            'razao_social' => $razaoSocial,
            'nome_fantasia' => $nomeFantasia !== '' ? $nomeFantasia : null,
            'logo' => $logoPath,
            'cnpj' => $cnpj !== '' ? $cnpj : null,
            'email' => $email !== '' ? $email : null,
        ],
    ]);
    exit;
}

if ($id === 0) {
    $pdo->prepare("INSERT INTO clientes (razao_social, nome_fantasia, logo, cnpj, endereco, email, telefone, contato, observacoes, ativo, criado_por)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)")
        ->execute([
            $razaoSocial, $nomeFantasia !== '' ? $nomeFantasia : null, $logoPath,
            $cnpj !== '' ? $cnpj : null, $endereco !== '' ? $endereco : null, $email !== '' ? $email : null,
            $telefone !== '' ? $telefone : null, $contato !== '' ? $contato : null, $observacoes !== '' ? $observacoes : null,
            $_SESSION['usuario'] ?? null,
        ]);

    redirecionarComMsg($voltarUrl, 'criado');
} else {
    $stmtChk = $pdo->prepare("SELECT logo FROM clientes WHERE id = ? LIMIT 1");
    $stmtChk->execute([$id]);
    $atual = $stmtChk->fetch(PDO::FETCH_ASSOC);
    if (!$atual) {
        header("Location: /gestor/clientes");
        exit;
    }

    if ($logoPath === null) {
        $logoPath = $atual['logo'];
    } elseif (!empty($atual['logo'])) {
        $antigo = __DIR__ . '/../../../../' . $atual['logo'];
        if (file_exists($antigo)) @unlink($antigo);
    }

    $pdo->prepare("UPDATE clientes SET razao_social = ?, nome_fantasia = ?, logo = ?, cnpj = ?, endereco = ?, email = ?, telefone = ?, contato = ?, observacoes = ? WHERE id = ?")
        ->execute([
            $razaoSocial, $nomeFantasia !== '' ? $nomeFantasia : null, $logoPath,
            $cnpj !== '' ? $cnpj : null, $endereco !== '' ? $endereco : null, $email !== '' ? $email : null,
            $telefone !== '' ? $telefone : null, $contato !== '' ? $contato : null, $observacoes !== '' ? $observacoes : null,
            $id,
        ]);

    redirecionarComMsg($voltarUrl, 'atualizado');
}
