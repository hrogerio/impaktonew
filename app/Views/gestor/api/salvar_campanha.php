<?php
/**
 * POST /gestor/campanhas/salvar
 * Cria ou atualiza uma campanha de um ponto.
 * Body JSON: { ponto_id, campanha_id?, cliente, agencia, campanha, situacao, inicio, fim, contato, observacoes }
 */
ini_set('display_errors', 0);
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();

function responderSalvar($dados) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados);
    exit;
}

if (!isset($_SESSION['usuario']))          responderSalvar(['erro'=>'nao_logado']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') responderSalvar(['erro'=>'metodo_invalido']);

require_once __DIR__ . '/../../../../config/database.php';
$pdo = getDatabase();

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$pontoId    = (int)($body['ponto_id']    ?? 0);
$campanhaId = (int)($body['campanha_id'] ?? 0); // 0 = nova
$cliente    = trim($body['cliente']    ?? '');
$agencia    = trim($body['agencia']    ?? '');
$campanha   = trim($body['campanha']   ?? '');
$situacao   = trim($body['situacao']   ?? 'Ocupado');
$inicio     = trim($body['inicio']     ?? '');
$fim        = trim($body['fim']        ?? '');
$contato    = trim($body['contato']    ?? '');
$obs        = trim($body['observacoes'] ?? '');
$usuario    = $_SESSION['usuario'] ?? '';

$situacoesValidas = ['Ocupado','Reservado','Permuta','Bisemana','Vencido'];
if (!in_array($situacao, $situacoesValidas)) $situacao = 'Ocupado';

if (!$pontoId) responderSalvar(['erro'=>'ponto_id invalido']);

// Verifica se ponto existe
$sp = $pdo->prepare("SELECT id, situacao FROM pontos WHERE id = ? AND (ativo=1 OR ativo IS NULL) LIMIT 1");
$sp->execute([$pontoId]);
$ponto = $sp->fetch(PDO::FETCH_ASSOC);
if (!$ponto) responderSalvar(['erro'=>'ponto nao encontrado']);

try {
    $pdo->beginTransaction();

    if ($campanhaId > 0) {
        // ── EDITAR campanha existente ──────────────────────
        $stmt = $pdo->prepare("
            UPDATE campanhas
            SET cliente=?, agencia=?, campanha=?, situacao=?,
                inicio=?, fim=?, contato=?, observacoes=?
            WHERE id=? AND ponto_id=?
        ");
        $stmt->execute([
            $cliente ?: null, $agencia ?: null, $campanha ?: null, $situacao,
            $inicio ?: null,  $fim ?: null,     $contato ?: null,  $obs ?: null,
            $campanhaId, $pontoId
        ]);
    } else {
        // ── NOVA campanha: encerra a atual (se houver) ─────
        $pdo->prepare("
            UPDATE campanhas
            SET ativo=0, encerrado_em=NOW()
            WHERE ponto_id=? AND ativo=1
        ")->execute([$pontoId]);

        // Cria nova campanha
        $stmt = $pdo->prepare("
            INSERT INTO campanhas (ponto_id, cliente, agencia, campanha, situacao, inicio, fim, contato, observacoes, ativo, criado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
        ");
        $stmt->execute([
            $pontoId,
            $cliente ?: null, $agencia ?: null, $campanha ?: null,
            $situacao,
            $inicio ?: null, $fim ?: null,
            $contato ?: null, $obs ?: null,
            $usuario
        ]);
        $campanhaId = (int)$pdo->lastInsertId();
    }

    // Sincroniza situação do ponto
    $pdo->prepare("UPDATE pontos SET situacao=? WHERE id=?")->execute([$situacao, $pontoId]);

    $pdo->commit();
    responderSalvar(['ok' => true, 'campanha_id' => $campanhaId, 'situacao' => $situacao]);
} catch (PDOException $e) {
    try { $pdo->rollBack(); } catch (Exception $ex) {}
    error_log("salvar_campanha ponto=$pontoId: " . $e->getMessage());
    responderSalvar(['erro' => 'db_error', 'msg' => $e->getMessage()]);
}
