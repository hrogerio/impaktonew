<?php
/**
 * POST /gestor/campanhas/processar-vencidos
 * Encerra automaticamente todas as campanhas cujo fim_contrato < hoje
 * e retorna os pontos à situação 'Disponivel'.
 */
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();

function resp($dados) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados);
    exit;
}

if (!isset($_SESSION['usuario']))          resp(['erro' => 'nao_logado']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') resp(['erro' => 'metodo_invalido']);

$cfgPath = __DIR__ . '/../../../../config/database.php';
if (!file_exists($cfgPath)) resp(['erro' => 'config_nao_encontrada']);
require_once $cfgPath;

try {
    $pdo = getDatabase();
} catch (Exception $e) {
    resp(['erro' => 'db_connection', 'msg' => $e->getMessage()]);
}

try {
    $pdo->beginTransaction();

    // Buscar campanhas ativas vencidas (fim < hoje, situacao Ocupado)
    $stmt = $pdo->query("
        SELECT c.id AS camp_id, c.ponto_id, c.cliente,
               p.numero, p.logradouro
        FROM campanhas c
        JOIN pontos p ON p.id = c.ponto_id
        WHERE c.ativo = 1
          AND c.situacao = 'Ocupado'
          AND c.fim IS NOT NULL
          AND DATE(c.fim) < CURDATE()
    ");
    $vencidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $processados = [];
    foreach ($vencidos as $v) {
        // Encerrar campanha
        $pdo->prepare("UPDATE campanhas SET ativo=0, encerrado_em=NOW() WHERE id=?")
            ->execute([$v['camp_id']]);
        // Liberar ponto
        $pdo->prepare("UPDATE pontos SET situacao='Disponivel' WHERE id=?")
            ->execute([$v['ponto_id']]);
        $processados[] = [
            'ponto_id'   => $v['ponto_id'],
            'numero'     => $v['numero'],
            'logradouro' => $v['logradouro'],
            'cliente'    => $v['cliente'],
        ];
    }

    $pdo->commit();
    resp(['ok' => true, 'processados' => count($processados), 'pontos' => $processados]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("processar_vencidos: " . $e->getMessage());
    resp(['erro' => 'db_error', 'msg' => $e->getMessage()]);
}
