<?php
// Consulta um CNPJ na BrasilAPI (base aberta da Receita Federal) e devolve os dados
// já no formato dos campos do cadastro de clientes. Também avisa se o CNPJ já está
// cadastrado em outro cliente.
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

function respostaCnpj(int $status, array $corpo): void {
    http_response_code($status);
    echo json_encode($corpo, JSON_UNESCAPED_UNICODE);
    exit;
}

// Aceita o CNPJ numérico e o alfanumérico (Receita, a partir de jul/2026):
// 12 caracteres [A-Z0-9] + 2 dígitos verificadores, cada caractere valendo ord(c) - 48.
function cnpjValido(string $cnpj): bool {
    if (!preg_match('/^[A-Z0-9]{12}[0-9]{2}$/', $cnpj)) return false;
    if (preg_match('/^(.)\1{13}$/', $cnpj)) return false;
    foreach ([12, 13] as $tam) {
        $soma = 0;
        $peso = $tam - 7;
        for ($i = 0; $i < $tam; $i++) {
            $soma += (ord($cnpj[$i]) - 48) * $peso;
            $peso = $peso === 2 ? 9 : $peso - 1;
        }
        $dv = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
        if ((int)$cnpj[$tam] !== $dv) return false;
    }
    return true;
}

function formatarCnpj(string $c): string {
    return substr($c, 0, 2) . '.' . substr($c, 2, 3) . '.' . substr($c, 5, 3) . '/' . substr($c, 8, 4) . '-' . substr($c, 12, 2);
}

function formatarTelefone(?string $tel): string {
    $d = preg_replace('/\D/', '', (string)$tel);
    if (strlen($d) === 10) return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6));
    if (strlen($d) === 11) return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7));
    return $d;
}

function montarEndereco(array $r): string {
    $tipo  = trim((string)($r['descricao_tipo_de_logradouro'] ?? ''));
    $logr  = trim((string)($r['logradouro'] ?? ''));
    if ($tipo !== '' && $logr !== '' && stripos($logr, $tipo) !== 0) {
        $logr = "{$tipo} {$logr}";
    }
    $linha = implode(', ', array_filter([$logr, trim((string)($r['numero'] ?? '')), trim((string)($r['complemento'] ?? ''))], 'strlen'));

    $cidade = trim((string)($r['municipio'] ?? ''));
    $uf     = trim((string)($r['uf'] ?? ''));
    $cidadeUf = $cidade !== '' && $uf !== '' ? "{$cidade}/{$uf}" : $cidade . $uf;

    $cep = preg_replace('/\D/', '', (string)($r['cep'] ?? ''));
    $cep = strlen($cep) === 8 ? 'CEP ' . substr($cep, 0, 5) . '-' . substr($cep, 5) : '';

    return implode(' - ', array_filter([$linha, trim((string)($r['bairro'] ?? '')), $cidadeUf, $cep], 'strlen'));
}

function buscarBrasilApi(string $cnpj): array {
    $url = "https://brasilapi.com.br/api/cnpj/v1/{$cnpj}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_USERAGENT      => 'ImpaktoGestor/1.0',
    ]);
    $corpo  = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro   = curl_error($ch);
    curl_close($ch);

    if ($corpo === false) {
        error_log("[consultar_cnpj] Falha de conexão com a BrasilAPI: {$erro}");
        return [0, null];
    }
    return [$status, json_decode($corpo, true)];
}

$cnpj = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_GET['cnpj'] ?? ''));
$idAtual = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!cnpjValido($cnpj)) {
    respostaCnpj(422, ['ok' => false, 'erro' => 'CNPJ inválido. Confira os números digitados.']);
}

// CNPJ já cadastrado em outro cliente? (o campo é gravado com ou sem máscara)
require_once __DIR__ . '/../../../../config/database.php';
$pdo = getDatabase();
$stmt = $pdo->prepare("
    SELECT id, razao_social FROM clientes
    WHERE UPPER(REPLACE(REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '/', ''), '-', ''), ' ', '')) = ?
      AND id <> ?
    LIMIT 1
");
$stmt->execute([$cnpj, $idAtual]);
$duplicado = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

[$status, $r] = buscarBrasilApi($cnpj);

if ($status === 404) {
    respostaCnpj(404, ['ok' => false, 'erro' => 'CNPJ não encontrado na base da Receita Federal.', 'duplicado' => $duplicado]);
}
if ($status !== 200 || !is_array($r)) {
    $msg = $status === 429
        ? 'Muitas consultas seguidas. Aguarde um minuto e tente de novo.'
        : 'Não foi possível consultar o CNPJ agora. Tente novamente em instantes.';
    if ($status !== 429) error_log("[consultar_cnpj] BrasilAPI respondeu HTTP {$status} para {$cnpj}");
    respostaCnpj(502, ['ok' => false, 'erro' => $msg, 'duplicado' => $duplicado]);
}

$situacao = trim((string)($r['descricao_situacao_cadastral'] ?? ''));

respostaCnpj(200, [
    'ok'        => true,
    'duplicado' => $duplicado,
    'situacao'  => $situacao,
    'dados'     => [
        'cnpj'          => formatarCnpj($cnpj),
        'razao_social'  => trim((string)($r['razao_social'] ?? '')),
        'nome_fantasia' => trim((string)($r['nome_fantasia'] ?? '')),
        'endereco'      => montarEndereco($r),
        'email'         => mb_strtolower(trim((string)($r['email'] ?? ''))),
        'telefone'      => formatarTelefone($r['ddd_telefone_1'] ?? ''),
    ],
]);
