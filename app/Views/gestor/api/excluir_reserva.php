<?php
/**
 * API: Exclui uma reserva (POST /gestor/reservas/excluir)
 * Body JSON: { id: 42 }
 * Retorna JSON: { ok: true }
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'nao_autenticado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$body = json_decode(file_get_contents('php://input'), true);
$id   = isset($body['id']) ? (int)$body['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['erro' => 'id_invalido']);
    exit;
}

try {
    require_once __DIR__ . '/../../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'db_error']);
    exit;
}

try {
    // pre_selecao_pontos é excluído em cascata pelo FK
    $stmt = $pdo->prepare("DELETE FROM pre_selecoes WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['ok' => true, 'deleted' => $stmt->rowCount()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'db_error', 'msg' => $e->getMessage()]);
}
