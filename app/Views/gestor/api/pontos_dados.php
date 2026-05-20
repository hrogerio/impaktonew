<?php
/**
 * API: Retorna dados dos pontos por IDs (GET /gestor/pontos/dados?ids=1,2,3)
 * Usado pela página de Pré-Seleção via fetch()
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'nao_autenticado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'db_error']);
    exit;
}

// Sanitizar IDs recebidos
$raw = $_GET['ids'] ?? '';
$ids = array_values(array_filter(
    array_map('intval', explode(',', $raw)),
    function($v) { return $v > 0; }
));

if (empty($ids)) {
    echo json_encode([]);
    exit;
}

// Placeholders para prepared statement
$placeholders = implode(',', array_fill(0, count($ids), '?'));

$sql = "
    SELECT p.id, p.numero, p.logradouro, p.descricao, p.cidade, p.regiao,
           p.cliente, p.agencia, p.tipo, p.situacao, p.corredor, p.formato,
           p.inicio_contrato, p.fim_contrato,
           COALESCE(
               (SELECT pf.caminho FROM ponto_fotos pf WHERE pf.ponto_id = p.id AND pf.principal = 1 LIMIT 1),
               p.foto
           ) AS foto
    FROM pontos p
    WHERE p.id IN ($placeholders)
      AND (p.ativo = 1 OR p.ativo IS NULL)
    ORDER BY p.numero ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_values($ids));
$pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($pontos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
