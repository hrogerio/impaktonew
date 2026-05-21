<?php
/**
 * API: Salva pré-seleção no banco (POST /gestor/pre-selecao/salvar)
 * Body JSON: { cliente, agencia, periodo_ini, periodo_fim, sem_periodo, pontos_ids }
 * Retorna JSON: { ok: true, id: 42 }
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
if (!$body) {
    http_response_code(400);
    echo json_encode(['erro' => 'body_invalido']);
    exit;
}

$cliente    = trim($body['cliente']    ?? '');
$agencia    = trim($body['agencia']    ?? '') ?: null;
$periodoIni = $body['periodo_ini']     ?? null;
$periodoFim = $body['periodo_fim']     ?? null;
$semPeriodo = !empty($body['sem_periodo']) ? 1 : 0;
$pontosIds  = array_values(array_filter(
    array_map('intval', (array)($body['pontos_ids'] ?? [])),
    function($v) { return $v > 0; }
));

if ($cliente === '') {
    http_response_code(422);
    echo json_encode(['erro' => 'cliente_obrigatorio']);
    exit;
}
if (empty($pontosIds)) {
    http_response_code(422);
    echo json_encode(['erro' => 'pontos_vazios']);
    exit;
}

// Valida datas
$ini = ($periodoIni && !$semPeriodo) ? $periodoIni : null;
$fim = ($periodoFim && !$semPeriodo) ? $periodoFim : null;

try {
    require_once __DIR__ . '/../../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'db_error']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Inserir cabeçalho
    $stmt = $pdo->prepare("
        INSERT INTO pre_selecoes (cliente, agencia, periodo_ini, periodo_fim, sem_periodo, total_pontos, criado_por)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$cliente, $agencia, $ini, $fim, $semPeriodo, count($pontosIds), $_SESSION['usuario']]);
    $preSelecaoId = (int)$pdo->lastInsertId();

    // Inserir pontos
    $stmtP = $pdo->prepare("
        INSERT INTO pre_selecao_pontos (pre_selecao_id, ponto_id, ordem) VALUES (?, ?, ?)
    ");
    foreach ($pontosIds as $ordem => $pontoId) {
        $stmtP->execute([$preSelecaoId, $pontoId, $ordem]);
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'id' => $preSelecaoId]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['erro' => 'db_error', 'msg' => $e->getMessage()]);
}
