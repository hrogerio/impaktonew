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
    ':latitude'        => coordOuNull($_POST['latitude']   ?? ''),
    ':longitude'       => coordOuNull($_POST['longitude']  ?? ''),
    ':exclusivo'               => isset($_POST['exclusivo']) ? 1 : 0,
    ':cliente_exclusivo'       => limpar($_POST['cliente_exclusivo'] ?? '', 255),
    ':liberado_comercializacao' => isset($_POST['liberado_comercializacao']) ? 1 : 0,
];

// Impede marcar "Ocupado" manualmente sem uma campanha ativa por trás
// (isso já causou pontos travados em "Ocupado" após a campanha real ter
// sido encerrada — ver database/migrations/limpa_pontos_ocupacao_divergente.sql)
if ($params[':situacao'] === 'Ocupado') {
    $temCampanhaAtiva = false;
    if ($id > 0) {
        $stmtCheck = $pdo->prepare("SELECT 1 FROM campanhas WHERE ponto_id = :id AND ativo = 1 LIMIT 1");
        $stmtCheck->execute([':id' => $id]);
        $temCampanhaAtiva = (bool)$stmtCheck->fetchColumn();
    }
    if (!$temCampanhaAtiva) {
        $redir = $id > 0
            ? "/gestor/pontos/editar?id=$id&msg=erro_situacao_sem_campanha"
            : "/gestor/pontos/novo?msg=erro_situacao_sem_campanha";
        header("Location: $redir");
        exit;
    }
}

// Garante que colunas opcionais existem antes de usá-las
$colunasExistentes = [];
$rsCol = $pdo->query("SHOW COLUMNS FROM pontos");
foreach ($rsCol->fetchAll(PDO::FETCH_ASSOC) as $col) {
    $colunasExistentes[$col['Field']] = true;
}

// Remove do params qualquer coluna que ainda não existe no servidor
$colunasOpcionais = ['bairro','sentido','corredor','latitude','longitude',
    'exclusivo','cliente_exclusivo','liberado_comercializacao'];
foreach ($colunasOpcionais as $c) {
    if (!isset($colunasExistentes[$c])) unset($params[":$c"]);
}

try {
    if ($id > 0) {
        $setCols = [
            "numero = :numero", "logradouro = :logradouro", "descricao = :descricao",
            "cidade = :cidade", "regiao = :regiao", "tipo = :tipo",
            "formato = :formato", "situacao = :situacao",
        ];
        // Adiciona colunas opcionais apenas se existirem
        foreach ($colunasOpcionais as $c) {
            if (isset($colunasExistentes[$c])) $setCols[] = "$c = :$c";
        }
        $params[':id'] = $id;
        $stmt = $pdo->prepare("UPDATE pontos SET ".implode(', ', $setCols)."
            WHERE id = :id AND (ativo = 1 OR ativo IS NULL)");
        $stmt->execute($params);
        header("Location: /gestor/pontos/editar?id=$id&msg=salvo");
    } else {
        $cols = ['numero','logradouro','descricao','cidade','regiao',
                 'tipo','formato','situacao','ativo'];
        $vals = [':numero',':logradouro',':descricao',':cidade',':regiao',
                 ':tipo',':formato',':situacao','1'];
        foreach ($colunasOpcionais as $c) {
            if (isset($colunasExistentes[$c])) { $cols[] = $c; $vals[] = ":$c"; }
        }
        $stmt = $pdo->prepare("INSERT INTO pontos (".implode(',',$cols).")
            VALUES (".implode(',',$vals).")");
        $stmt->execute($params);
        $novoId = (int)$pdo->lastInsertId();
        header("Location: /gestor/pontos/editar?id=$novoId&msg=criado&aba=fotos");
    }
} catch (PDOException $e) {
    error_log("ERRO salvar_ponto id=$id: " . $e->getMessage());
    $redir = $id > 0 ? "/gestor/pontos/editar?id=$id&msg=erro_db" : "/gestor/pontos/novo?msg=erro_db";
    header("Location: $redir");
}
exit;
