<?php
/**
 * POST /gestor/campanhas/encerrar
 * Encerra a campanha ativa de um ponto e retorna o ponto a "Disponível".
 * Body JSON: { ponto_id }
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { http_response_code(401); echo json_encode(['erro'=>'nao_logado']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/database.php';
$pdo = getDatabase();

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$pontoId = (int)($body['ponto_id'] ?? 0);

if (!$pontoId) { echo json_encode(['erro'=>'ponto_id inválido']); exit; }

try {
    $pdo->beginTransaction();

    $pdo->prepare("
        UPDATE campanhas SET ativo=0, encerrado_em=NOW()
        WHERE ponto_id=? AND ativo=1
    ")->execute([$pontoId]);

    $pdo->prepare("
        UPDATE pontos SET situacao='Disponível' WHERE id=?
    ")->execute([$pontoId]);

    $pdo->commit();

    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("encerrar_campanha ponto=$pontoId: " . $e->getMessage());
    echo json_encode(['erro' => 'erro interno']);
}
