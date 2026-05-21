<?php
/**
 * API: Retorna dados de uma pré-seleção salva (GET /gestor/pre-selecao/dados?id=42)
 * Retorna JSON: { pre_selecao: {...}, pontos_ids: [1,2,3] }
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'nao_autenticado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
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

$ps = $pdo->prepare("SELECT * FROM pre_selecoes WHERE id = ? LIMIT 1");
$ps->execute([$id]);
$preSelecao = $ps->fetch(PDO::FETCH_ASSOC);

if (!$preSelecao) {
    http_response_code(404);
    echo json_encode(['erro' => 'nao_encontrado']);
    exit;
}

$pp = $pdo->prepare("SELECT ponto_id FROM pre_selecao_pontos WHERE pre_selecao_id = ? ORDER BY ordem ASC");
$pp->execute([$id]);
$pontosIds = $pp->fetchAll(PDO::FETCH_COLUMN);

echo json_encode([
    'pre_selecao' => $preSelecao,
    'pontos_ids'  => array_map('strval', $pontosIds)
], JSON_UNESCAPED_UNICODE);
