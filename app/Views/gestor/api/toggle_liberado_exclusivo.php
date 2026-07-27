<?php
/**
 * API: Alterna liberado_comercializacao de um ponto exclusivo (POST /gestor/pontos/toggle-liberado)
 * Body JSON: { id: 42 }
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
    http_response_code(422);
    echo json_encode(['erro' => 'dados_invalidos']);
    exit;
}

try {
    require_once __DIR__ . '/../../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'db_connection']);
    exit;
}

$stmt = $pdo->prepare("SELECT exclusivo, liberado_comercializacao FROM pontos WHERE id = ?");
$stmt->execute([$id]);
$ponto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ponto) {
    http_response_code(404);
    echo json_encode(['erro' => 'nao_encontrado']);
    exit;
}
if ((int)$ponto['exclusivo'] !== 1) {
    http_response_code(422);
    echo json_encode(['erro' => 'ponto_nao_exclusivo']);
    exit;
}

$novoValor = (int)$ponto['liberado_comercializacao'] === 1 ? 0 : 1;
$pdo->prepare("UPDATE pontos SET liberado_comercializacao = ? WHERE id = ?")
    ->execute([$novoValor, $id]);

echo json_encode(['ok' => true, 'liberado_comercializacao' => $novoValor]);
