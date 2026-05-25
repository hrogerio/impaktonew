<?php
/**
 * POST /gestor/campanhas/encerrar
 * Encerra a campanha ativa de um ponto e retorna o ponto a "Disponivel".
 */
ini_set('display_errors', 0);
ob_start(); // captura qualquer saída acidental

if (session_status() === PHP_SESSION_NONE) session_start();

// Garante JSON mesmo em caso de erro inesperado
function responder($dados) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados);
    exit;
}

if (!isset($_SESSION['usuario']))          responder(['erro' => 'nao_logado']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(['erro' => 'metodo_invalido']);

require_once __DIR__ . '/../../../config/database.php';

try {
    $pdo = getDatabase();
} catch (Exception $e) {
    responder(['erro' => 'db_connection']);
}

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$pontoId = (int)($body['ponto_id'] ?? 0);

if (!$pontoId) responder(['erro' => 'ponto_id invalido']);

try {
    // Encerra campanhas ativas do ponto
    $stmt = $pdo->prepare("UPDATE campanhas SET ativo=0, encerrado_em=NOW() WHERE ponto_id=? AND ativo=1");
    $stmt->execute([$pontoId]);

    // Atualiza situação do ponto (sem acento — tabela latin1)
    $pdo->prepare("UPDATE pontos SET situacao='Disponivel' WHERE id=?")->execute([$pontoId]);

    responder(['ok' => true]);

} catch (PDOException $e) {
    error_log("encerrar_campanha ponto=$pontoId: " . $e->getMessage());
    responder(['erro' => 'db_error', 'msg' => $e->getMessage()]);
}
