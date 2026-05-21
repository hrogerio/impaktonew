<?php
/**
 * API: Retorna as últimas 10 reservas (GET /gestor/reservas/recentes)
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
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT id, cliente, agencia, periodo_ini, periodo_fim, sem_periodo,
               total_pontos, criado_em, criado_por
        FROM pre_selecoes
        ORDER BY criado_em DESC
        LIMIT 10
    ");
    $lista = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($lista, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode([]);
}
