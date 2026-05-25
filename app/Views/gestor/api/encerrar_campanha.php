<?php
/**
 * POST /gestor/campanhas/encerrar
 * Encerra a campanha ativa de um ponto e retorna o ponto a "Disponivel".
 */
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();

function respEnc($dados) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados);
    exit;
}

if (!isset($_SESSION['usuario']))          respEnc(['erro' => 'nao_logado']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respEnc(['erro' => 'metodo_invalido']);

$cfgPath = __DIR__ . '/../../../../config/database.php';
if (!file_exists($cfgPath)) {
    error_log("encerrar_campanha: database.php nao encontrado em $cfgPath");
    respEnc(['erro' => 'config_nao_encontrada', 'path' => $cfgPath]);
}
require_once $cfgPath;

try {
    $pdo = getDatabase();
} catch (Exception $e) {
    error_log("encerrar_campanha: db_connection " . $e->getMessage());
    respEnc(['erro' => 'db_connection', 'msg' => $e->getMessage()]);
}

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$pontoId = (int)($body['ponto_id'] ?? 0);

if (!$pontoId) respEnc(['erro' => 'ponto_id invalido']);

try {
    $pdo->prepare("UPDATE campanhas SET ativo=0, encerrado_em=NOW() WHERE ponto_id=? AND ativo=1")
        ->execute([$pontoId]);
    $pdo->prepare("UPDATE pontos SET situacao='Disponivel' WHERE id=?")
        ->execute([$pontoId]);
    respEnc(['ok' => true]);
} catch (PDOException $e) {
    error_log("encerrar_campanha ponto=$pontoId: " . $e->getMessage());
    respEnc(['erro' => 'db_error', 'msg' => $e->getMessage()]);
}
