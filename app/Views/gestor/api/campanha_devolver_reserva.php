<?php
/**
 * POST /gestor/campanhas/devolver-reserva
 * Body JSON: { campIds: [1,2,...], pontoIds: [10,11,...] }
 *
 * Devolve uma campanha ativa para o estágio de Reserva (pré-seleção):
 * encerra as campanhas atuais, cria uma pré-seleção com status "enviada"
 * (pontos aprovados pelo cliente, aguardando período/P.I.) e recria as
 * campanhas ligadas a ela como "Reservado" — mesmo efeito colateral do
 * status "enviada" em atualizar_status_reserva.php, só que partindo de
 * uma campanha já oficial em vez de uma pré-seleção nova.
 */
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();

function respDevRes($dados) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados);
    exit;
}

if (!isset($_SESSION['usuario']))          respDevRes(['erro' => 'nao_logado']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respDevRes(['erro' => 'metodo_invalido']);

require_once __DIR__ . '/../../../../config/database.php';
try {
    $pdo = getDatabase();
} catch (Exception $e) {
    error_log("campanha_devolver_reserva: db_connection " . $e->getMessage());
    respDevRes(['erro' => 'db_connection']);
}

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$campIds  = array_map('intval', $body['campIds']  ?? []);
$pontoIds = array_map('intval', $body['pontoIds'] ?? []);

if (empty($campIds) || count($campIds) !== count($pontoIds)) {
    respDevRes(['erro' => 'dados_invalidos']);
}

$usuario = $_SESSION['usuario'];

try {
    $pdo->beginTransaction();

    // Busca dados da primeira campanha do grupo pra montar a pré-seleção
    $stmt = $pdo->prepare("SELECT * FROM campanhas WHERE id = ? LIMIT 1");
    $stmt->execute([$campIds[0]]);
    $camp = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$camp) {
        $pdo->rollBack();
        respDevRes(['erro' => 'campanha_nao_encontrada']);
    }

    $semPeriodo = empty($camp['inicio']) && empty($camp['fim']) ? 1 : 0;

    $pdo->prepare("
        INSERT INTO pre_selecoes
            (cliente, agencia, periodo_ini, periodo_fim, sem_periodo, total_pontos, status, criado_por)
        VALUES (?, ?, ?, ?, ?, ?, 'enviada', ?)
    ")->execute([
        $camp['cliente'],
        $camp['agencia'] ?: null,
        $camp['inicio'] ?: null,
        $camp['fim'] ?: null,
        $semPeriodo,
        count($pontoIds),
        $usuario,
    ]);
    $preSelecaoId = (int)$pdo->lastInsertId();

    foreach ($campIds as $i => $campId) {
        $pontoId = $pontoIds[$i];

        // Encerra a campanha oficial atual
        $pdo->prepare("UPDATE campanhas SET ativo=0, encerrado_em=NOW() WHERE id=?")
            ->execute([$campId]);

        // Cria pré-seleção pra este ponto
        $pdo->prepare("
            INSERT INTO pre_selecao_pontos (pre_selecao_id, ponto_id, ordem)
            VALUES (?, ?, ?)
        ")->execute([$preSelecaoId, $pontoId, $i]);

        // Recria a campanha como "Reservado", ligada à pré-seleção
        $pdo->prepare("
            INSERT INTO campanhas
                (ponto_id, cliente, cliente_id, agencia, agencia_id, campanha, situacao, inicio, fim, ativo, criado_por, pre_selecao_id)
            VALUES (?, ?, ?, ?, ?, ?, 'Reservado', ?, ?, 1, ?, ?)
        ")->execute([
            $pontoId,
            $camp['cliente'],
            $camp['cliente_id'] ?: null,
            $camp['agencia'] ?: null,
            $camp['agencia_id'] ?: null,
            null, // nome da campanha fica em branco (reserva)
            $camp['inicio'] ?: null,
            $camp['fim'] ?: null,
            $usuario,
            $preSelecaoId,
        ]);

        // Ponto continua reservado pra este cliente (não volta a Disponível)
        $pdo->prepare("UPDATE pontos SET situacao='Reservado' WHERE id=?")
            ->execute([$pontoId]);
    }

    $pdo->commit();
    respDevRes(['ok' => true, 'pre_selecao_id' => $preSelecaoId]);

} catch (PDOException $e) {
    try { $pdo->rollBack(); } catch (Exception $ex) {}
    error_log("campanha_devolver_reserva: " . $e->getMessage());
    respDevRes(['erro' => 'db_error', 'msg' => $e->getMessage()]);
}
