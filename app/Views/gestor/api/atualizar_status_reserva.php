<?php
/**
 * API: Atualiza status de uma reserva (POST /gestor/reservas/status)
 * Body JSON: { id: 42, status: "enviada" }
 * Retorna JSON: { ok: true, status: "enviada" }
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'nao_autenticado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$body   = json_decode(file_get_contents('php://input'), true);
$id     = isset($body['id'])     ? (int)$body['id']          : 0;
$status = isset($body['status']) ? trim($body['status'])      : '';

$permitidos = ['rascunho', 'enviada', 'aprovada', 'recusada'];
if ($id <= 0 || !in_array($status, $permitidos)) {
    http_response_code(422);
    echo json_encode(['erro' => 'dados_invalidos']);
    exit;
}

try {
    require_once __DIR__ . '/../../../../config/database.php';
    $pdo = getDatabase();
    $stmt = $pdo->prepare("UPDATE pre_selecoes SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    echo json_encode(['ok' => true, 'status' => $status]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'db_error']);
}
