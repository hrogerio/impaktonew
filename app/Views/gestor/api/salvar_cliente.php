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

// Capitaliza como nome próprio: primeira letra de cada palavra maiúscula,
// exceto conectivos comuns (de, da, do...), que ficam minúsculos (a não ser que abram o nome),
// e siglas (GWM, JBS, LTDA...) que permanecem em maiúscula.
function clienteNomeProprio(string $nome): string {
    $conectivos = ['de', 'da', 'do', 'das', 'dos', 'e'];
    $siglasConhecidas = ['ltda', 'me', 'epp', 'mei', 'sa', 's/a', 'cia', 'spe', 'eireli', 'ooh', 'pj', 'pf'];
    $palavras = preg_split('/\s+/', trim(mb_strtolower($nome)));
    foreach ($palavras as $i => $p) {
        if ($p === '') continue;

        $letras = preg_replace('/[^\p{L}]/u', '', $p);
        $temVogal = (bool)preg_match('/[aeiouáéíóúâêôãõ]/u', $letras);
        $ehSigla = $letras !== '' && mb_strlen($letras) <= 5 && (!$temVogal || in_array($letras, $siglasConhecidas, true));
        if ($ehSigla) {
            $palavras[$i] = mb_strtoupper($p);
            continue;
        }

        if ($i > 0 && in_array($p, $conectivos, true)) continue;
        $palavras[$i] = mb_strtoupper(mb_substr($p, 0, 1)) . mb_substr($p, 1);
    }
    return implode(' ', $palavras);
}
$contatoBruto = trim($_POST['contato'] ?? '');
$contato      = $contatoBruto !== '' ? clienteNomeProprio($contatoBruto) : '';
$razaoSocial  = $razaoSocialBruta !== '' ? clienteNomeProprio($razaoSocialBruta) : '';
$nomeFantasia = $nomeFantasiaBruto !== '' ? clienteNomeProprio($nomeFantasiaBruto) : '';

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

if ($isAjax) {
    if ($id === 0) {
        erroCliente($isAjax, "Cliente inválido.", $id);
    }
    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        erroCliente($isAjax, "Cliente não encontrado.", $id);
    }

    $pdo->prepare("UPDATE clientes SET razao_social = ?, nome_fantasia = ?, cnpj = ?, email = ? WHERE id = ?")
        ->execute([$razaoSocial, $nomeFantasia !== '' ? $nomeFantasia : null, $cnpj !== '' ? $cnpj : null, $email !== '' ? $email : null, $id]);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'cliente' => [
            'id' => $id,
            'razao_social' => $razaoSocial,
            'nome_fantasia' => $nomeFantasia !== '' ? $nomeFantasia : null,
            'cnpj' => $cnpj !== '' ? $cnpj : null,
            'email' => $email !== '' ? $email : null,
        ],
    ]);
    exit;
}

$params = [
    $razaoSocial,
    $nomeFantasia !== '' ? $nomeFantasia : null,
    $cnpj !== '' ? $cnpj : null,
    $endereco !== '' ? $endereco : null,
    $email !== '' ? $email : null,
    $telefone !== '' ? $telefone : null,
    $contato !== '' ? $contato : null,
    $observacoes !== '' ? $observacoes : null,
];

if ($id === 0) {
    $pdo->prepare("INSERT INTO clientes (razao_social, nome_fantasia, cnpj, endereco, email, telefone, contato, observacoes, ativo, criado_por)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)")
        ->execute(array_merge($params, [$_SESSION['usuario'] ?? null]));

    redirecionarComMsg($voltarUrl, 'criado');
} else {
    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        header("Location: /gestor/clientes");
        exit;
    }

    $pdo->prepare("UPDATE clientes SET razao_social = ?, nome_fantasia = ?, cnpj = ?, endereco = ?, email = ?, telefone = ?, contato = ?, observacoes = ? WHERE id = ?")
        ->execute(array_merge($params, [$id]));

    redirecionarComMsg($voltarUrl, 'atualizado');
}
