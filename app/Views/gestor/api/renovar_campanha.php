<?php
/**
 * POST /gestor/campanhas/renovar
 * Cria nova campanha baseada em uma existente (ativa ou encerrada),
 * com novas datas de início e fim.
 * Body JSON: { campanha_id: id_antiga, ponto_id, inicio?, fim }
 */
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();

function respRenovar($dados) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados);
    exit;
}

if (!isset($_SESSION['usuario']))          respRenovar(['erro' => 'nao_logado']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respRenovar(['erro' => 'metodo_invalido']);

require_once __DIR__ . '/../../../../config/database.php';
$pdo = getDatabase();

$body         = json_decode(file_get_contents('php://input'), true) ?? [];
$campanhaIdAntiga = (int)($body['campanha_id'] ?? 0);
$pontoId      = (int)($body['ponto_id']    ?? 0);
$novoInicio   = trim($body['inicio']       ?? '');
$novoFim      = trim($body['fim']          ?? '');
$usuario      = $_SESSION['usuario'] ?? '';

if (!$novoFim)   respRenovar(['erro' => 'fim_obrigatorio']);
if (!$pontoId && !$campanhaIdAntiga) respRenovar(['erro' => 'ponto_ou_campanha_obrigatorio']);

// Busca dados da campanha de referência
$campAntiga = [];
if ($campanhaIdAntiga > 0) {
    $s = $pdo->prepare("SELECT * FROM campanhas WHERE id = ? LIMIT 1");
    $s->execute([$campanhaIdAntiga]);
    $campAntiga = $s->fetch(PDO::FETCH_ASSOC) ?: [];
    if ($campAntiga) $pontoId = (int)$campAntiga['ponto_id'];
}

// Se não temos dados de referência, busca a campanha mais recente do ponto
if (!$campAntiga && $pontoId) {
    $s = $pdo->prepare("SELECT * FROM campanhas WHERE ponto_id = ? ORDER BY id DESC LIMIT 1");
    $s->execute([$pontoId]);
    $campAntiga = $s->fetch(PDO::FETCH_ASSOC) ?: [];
}

if (!$pontoId) respRenovar(['erro' => 'ponto_nao_encontrado']);

// Verifica se ponto existe e está ativo
$sp = $pdo->prepare("SELECT id FROM pontos WHERE id = ? AND (ativo=1 OR ativo IS NULL) LIMIT 1");
$sp->execute([$pontoId]);
if (!$sp->fetch()) respRenovar(['erro' => 'ponto_nao_encontrado']);

$situacao = $campAntiga['situacao'] ?? 'Ocupado';
if ($situacao === 'Vencido') $situacao = 'Ocupado';
$cliente  = $campAntiga['cliente']  ?? null;
$agencia  = $campAntiga['agencia']  ?? null;
$campanha = $campAntiga['campanha'] ?? null;
$contato  = $campAntiga['contato']  ?? null;
$obs      = $campAntiga['observacoes'] ?? null;

try {
    $pdo->beginTransaction();

    // Encerra qualquer campanha ativa atual do ponto
    $pdo->prepare("UPDATE campanhas SET ativo=0, encerrado_em=NOW() WHERE ponto_id=? AND ativo=1")
        ->execute([$pontoId]);

    // Cria nova campanha com ativo=1
    $stmt = $pdo->prepare("
        INSERT INTO campanhas
            (ponto_id, cliente, agencia, campanha, situacao, inicio, fim, contato, observacoes, ativo, criado_por)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
    ");
    $stmt->execute([
        $pontoId,
        $cliente  ?: null,
        $agencia  ?: null,
        $campanha ?: null,
        $situacao,
        $novoInicio ?: null,
        $novoFim,
        $contato  ?: null,
        $obs      ?: null,
        $usuario,
    ]);
    $novaId = (int)$pdo->lastInsertId();

    // Sincroniza situação do ponto
    $pdo->prepare("UPDATE pontos SET situacao=? WHERE id=?")->execute([$situacao, $pontoId]);

    $pdo->commit();
    respRenovar(['ok' => true, 'campanha_id' => $novaId, 'situacao' => $situacao]);

} catch (Exception $e) {
    try { $pdo->rollBack(); } catch (Exception $ex) {}
    error_log("renovar_campanha ponto=$pontoId antiga=$campanhaIdAntiga: " . $e->getMessage());
    respRenovar(['erro' => 'db_error', 'msg' => $e->getMessage()]);
}
