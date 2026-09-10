<?php
/**
 * POST /gestor/financeiro/pedidos/excluir
 * Body JSON: { id }
 */
ini_set('display_errors', 0);
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();

function responderExclusao($dados) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados);
    exit;
}

if (!isset($_SESSION['usuario']))          responderExclusao(['erro' => 'nao_logado']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') responderExclusao(['erro' => 'metodo_invalido']);

require_once __DIR__ . '/../../../../config/database.php';
$pdo = getDatabase();

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$id = (int)($body['id'] ?? 0);
if (!$id) responderExclusao(['erro' => 'id_invalido']);

try {
    $pdo->prepare("DELETE FROM pedidos_financeiros WHERE id = ?")->execute([$id]);
    responderExclusao(['ok' => true]);
} catch (PDOException $e) {
    error_log("pedido_excluir id={$id}: " . $e->getMessage());
    responderExclusao(['erro' => 'db_error']);
}
