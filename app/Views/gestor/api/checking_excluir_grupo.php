<?php
/**
 * POST /gestor/campanhas/checking/excluir-grupo
 * Exclui todas as fotos de um checking (grupo cliente/campanha/período).
 */
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();

function ckgJson($data) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['usuario']))          ckgJson(['ok' => false, 'erro' => 'nao_logado']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ckgJson(['ok' => false, 'erro' => 'metodo_invalido']);

$csrf = $_POST['csrf_token'] ?? '';
if ($csrf !== ($_SESSION['csrf_token'] ?? '')) ckgJson(['ok' => false, 'erro' => 'csrf_invalido']);

require_once __DIR__ . '/../../../../config/database.php';
$pdo = getDatabase();

$cliente  = trim($_POST['cliente']  ?? '');
$agencia  = trim($_POST['agencia']  ?? '');
$campanha = trim($_POST['campanha'] ?? '');
$situacao = trim($_POST['situacao'] ?? '');
$inicio   = trim($_POST['inicio']   ?? '') ?: null;
$fim      = trim($_POST['fim']      ?? '') ?: null;

if ($cliente === '' && $campanha === '') ckgJson(['ok' => false, 'erro' => 'grupo_invalido']);

try {
    $s = $pdo->prepare("
        SELECT id, caminho FROM checking_fotos
        WHERE cliente=? AND agencia=? AND campanha=? AND situacao=? AND inicio<=>? AND fim<=>?
    ");
    $s->execute([$cliente, $agencia, $campanha, $situacao, $inicio, $fim]);
    $fotos = $s->fetchAll(PDO::FETCH_ASSOC);

    if (empty($fotos)) ckgJson(['ok' => false, 'erro' => 'checking_nao_encontrado']);

    $ids = array_column($fotos, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("DELETE FROM checking_fotos WHERE id IN ($ph)")->execute($ids);

    foreach ($fotos as $f) {
        $path = __DIR__ . '/../../../../' . $f['caminho'];
        if (file_exists($path)) @unlink($path);
    }

    ckgJson(['ok' => true, 'removidas' => count($fotos)]);
} catch (Exception $e) {
    error_log("checking excluir_grupo cliente=$cliente campanha=$campanha: " . $e->getMessage());
    ckgJson(['ok' => false, 'erro' => 'db_error']);
}
