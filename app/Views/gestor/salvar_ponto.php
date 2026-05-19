<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /gestor/pontos");
    exit;
}
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    die("Token de segurança inválido.");
}

require_once __DIR__ . '/../../../config/database.php';
$pdo = getDatabase();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

function limpar($v, $max = 255) {
    return substr(trim($v ?? ''), 0, $max);
}
function dataOuNull($v) {
    $d = trim($v ?? '');
    return ($d === '' || $d === '0000-00-00') ? null : $d;
}

$numero     = limpar($_POST['numero']   ?? '', 30);
$logradouro = limpar($_POST['logradouro'] ?? '', 255);
$cidade     = limpar($_POST['cidade']   ?? '', 100);

if ($numero === '' || $logradouro === '' || $cidade === '') {
    $redir = $id > 0 ? "/gestor/pontos/editar?id=$id&msg=erro" : "/gestor/pontos/novo?msg=erro";
    header("Location: $redir");
    exit;
}

function coordOuNull($v) {
    $d = trim($v ?? '');
    return $d === '' ? null : (float)$d;
}

$params = [
    ':numero'          => (int)$numero,
    ':logradouro'      => $logradouro,
    ':descricao'       => limpar($_POST['descricao']       ?? '', 65535),
    ':bairro'          => limpar($_POST['bairro']          ?? '',    20),
    ':cidade'          => $cidade,
    ':regiao'          => limpar($_POST['regiao']          ?? '',   100),
    ':sentido'         => limpar($_POST['sentido']         ?? '',   100),
    ':corredor'        => limpar($_POST['corredor']        ?? '',   100),
    ':tipo'            => limpar($_POST['tipo']            ?? '',    45),
    ':formato'         => limpar($_POST['formato']         ?? '',    50),
    ':situacao'        => limpar($_POST['situacao']        ?? '',    50),
    ':cliente'         => limpar($_POST['cliente']         ?? '',   255),
    ':agencia'         => limpar($_POST['agencia']         ?? '',   255),
    ':contato'         => limpar($_POST['contato']         ?? '',   100),
    ':observacoes'     => limpar($_POST['observacoes']     ?? '', 65535),
    ':inicio_contrato' => dataOuNull($_POST['inicio_contrato'] ?? ''),
    ':fim_contrato'    => dataOuNull($_POST['fim_contrato']    ?? ''),
    ':latitude'        => coordOuNull($_POST['latitude']   ?? ''),
    ':longitude'       => coordOuNull($_POST['longitude']  ?? ''),
];

if ($id > 0) {
    $params[':id'] = $id;
    $stmt = $pdo->prepare("
        UPDATE pontos SET
            numero          = :numero,
            logradouro      = :logradouro,
            descricao       = :descricao,
            bairro          = :bairro,
            cidade          = :cidade,
            regiao          = :regiao,
            sentido         = :sentido,
            corredor        = :corredor,
            tipo            = :tipo,
            formato         = :formato,
            situacao        = :situacao,
            cliente         = :cliente,
            agencia         = :agencia,
            contato         = :contato,
            observacoes     = :observacoes,
            inicio_contrato = :inicio_contrato,
            fim_contrato    = :fim_contrato,
            latitude        = :latitude,
            longitude       = :longitude
        WHERE id = :id AND (ativo = 1 OR ativo IS NULL)
    ");
    $stmt->execute($params);
    header("Location: /gestor/pontos/editar?id=$id&msg=salvo");
} else {
    $stmt = $pdo->prepare("
        INSERT INTO pontos
            (numero, logradouro, descricao, bairro, cidade, regiao, sentido, corredor,
             tipo, formato, situacao, cliente, agencia, contato, observacoes,
             inicio_contrato, fim_contrato, latitude, longitude, ativo)
        VALUES
            (:numero, :logradouro, :descricao, :bairro, :cidade, :regiao, :sentido, :corredor,
             :tipo, :formato, :situacao, :cliente, :agencia, :contato, :observacoes,
             :inicio_contrato, :fim_contrato, :latitude, :longitude, 1)
    ");
    $stmt->execute($params);
    $novoId = (int)$pdo->lastInsertId();
    header("Location: /gestor/pontos/editar?id=$novoId&msg=criado&aba=fotos");
}
exit;
