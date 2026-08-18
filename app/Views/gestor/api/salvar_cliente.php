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

$id           = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$razaoSocialBruta = trim($_POST['razao_social'] ?? '');
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

function voltarComErroCliente($msg, $id) {
    $url = $id > 0 ? "/gestor/clientes/editar?id={$id}" : "/gestor/clientes/novo";
    header("Location: {$url}&erro=" . urlencode($msg));
    exit;
}

if ($razaoSocial === '') {
    voltarComErroCliente("Razão social é obrigatória.", $id);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    voltarComErroCliente("E-mail inválido.", $id);
}

$params = [
    $razaoSocial,
    $cnpj !== '' ? $cnpj : null,
    $endereco !== '' ? $endereco : null,
    $email !== '' ? $email : null,
    $telefone !== '' ? $telefone : null,
    $contato !== '' ? $contato : null,
    $observacoes !== '' ? $observacoes : null,
];

if ($id === 0) {
    $pdo->prepare("INSERT INTO clientes (razao_social, cnpj, endereco, email, telefone, contato, observacoes, ativo, criado_por)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)")
        ->execute(array_merge($params, [$_SESSION['usuario'] ?? null]));

    header("Location: /gestor/clientes?msg=criado");
    exit;
} else {
    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        header("Location: /gestor/clientes");
        exit;
    }

    $pdo->prepare("UPDATE clientes SET razao_social = ?, cnpj = ?, endereco = ?, email = ?, telefone = ?, contato = ?, observacoes = ? WHERE id = ?")
        ->execute(array_merge($params, [$id]));

    header("Location: /gestor/clientes?msg=atualizado");
    exit;
}
