<?php
/**
 * API: Atualiza status de uma reserva (POST /gestor/reservas/status)
 * Body JSON: { id: 42, status: "enviada" }
 *
 * Efeitos colaterais por status:
 *   enviada  → cria campanha "Reservado" nos pontos que estão Disponivel
 *   aprovada → muda campanha linked de "Reservado" para "Ocupado"
 *   recusada → encerra campanhas linked "Reservado", libera pontos
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'nao_autenticado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$body   = json_decode(file_get_contents('php://input'), true);
$id     = isset($body['id'])     ? (int)$body['id']     : 0;
$status = isset($body['status']) ? trim($body['status']) : '';

$permitidos = ['rascunho', 'enviada', 'aprovada', 'recusada'];
if ($id <= 0 || !in_array($status, $permitidos)) {
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

try {
    $pdo->beginTransaction();

    // ── Atualiza status da pré-seleção ──────────────────────────
    $pdo->prepare("UPDATE pre_selecoes SET status = ? WHERE id = ?")
        ->execute([$status, $id]);

    // ── Busca dados da pré-seleção ───────────────────────────────
    $ps = $pdo->prepare("SELECT * FROM pre_selecoes WHERE id = ? LIMIT 1");
    $ps->execute([$id]);
    $ps = $ps->fetch(PDO::FETCH_ASSOC);
    if (!$ps) {
        $pdo->rollBack();
        echo json_encode(['erro' => 'reserva_nao_encontrada']);
        exit;
    }

    // ── Busca pontos desta pré-seleção ───────────────────────────
    $stmtPontos = $pdo->prepare("
        SELECT psp.ponto_id, p.situacao
        FROM pre_selecao_pontos psp
        JOIN pontos p ON p.id = psp.ponto_id
        WHERE psp.pre_selecao_id = ?
    ");
    $stmtPontos->execute([$id]);
    $pontos = $stmtPontos->fetchAll(PDO::FETCH_ASSOC);

    $ini = (!$ps['sem_periodo'] && $ps['periodo_ini']) ? $ps['periodo_ini'] : null;
    $fim = (!$ps['sem_periodo'] && $ps['periodo_fim']) ? $ps['periodo_fim'] : null;
    $usuario = $_SESSION['usuario'];

    // ── ENVIADA: cria campanha "Reservado" nos pontos disponíveis ─
    if ($status === 'enviada') {
        foreach ($pontos as $p) {
            $sit = strtolower(trim($p['situacao'] ?? ''));
            // Só reserva se estiver disponível
            if ($sit === 'disponivel' || $sit === 'disponível') {
                // Encerra campanha ativa atual (se houver)
                $pdo->prepare("
                    UPDATE campanhas SET ativo=0, encerrado_em=NOW()
                    WHERE ponto_id=? AND ativo=1
                ")->execute([$p['ponto_id']]);

                // Cria campanha "Reservado" ligada a esta pré-seleção
                $pdo->prepare("
                    INSERT INTO campanhas
                        (ponto_id, cliente, agencia, campanha, situacao, inicio, fim, ativo, criado_por, pre_selecao_id)
                    VALUES (?, ?, ?, ?, 'Reservado', ?, ?, 1, ?, ?)
                ")->execute([
                    $p['ponto_id'],
                    $ps['cliente'],
                    $ps['agencia'] ?: null,
                    null,   // nome da campanha fica em branco (reserva)
                    $ini,
                    $fim,
                    $usuario,
                    $id
                ]);

                // Atualiza situação do ponto
                $pdo->prepare("UPDATE pontos SET situacao='Reservado' WHERE id=?")
                    ->execute([$p['ponto_id']]);
            }
            // Se já está Reservado ou Ocupado por outro cliente: não sobrescreve
        }
    }

    // ── APROVADA: Reservado → Ocupado nos pontos desta reserva ───
    if ($status === 'aprovada') {
        // Pega os pontos que têm campanha linked a esta pré-seleção ainda Reservado
        $linked = $pdo->prepare("
            SELECT ponto_id FROM campanhas
            WHERE pre_selecao_id=? AND situacao='Reservado' AND ativo=1
        ");
        $linked->execute([$id]);
        $pontosLinked = $linked->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($pontosLinked)) {
            // Muda campanhas para Ocupado
            $pdo->prepare("
                UPDATE campanhas SET situacao='Ocupado'
                WHERE pre_selecao_id=? AND situacao='Reservado' AND ativo=1
            ")->execute([$id]);

            // Atualiza pontos
            foreach ($pontosLinked as $pontoId) {
                $pdo->prepare("UPDATE pontos SET situacao='Ocupado' WHERE id=?")
                    ->execute([$pontoId]);
            }
        }
    }

    // ── RECUSADA: encerra campanhas "Reservado" desta reserva ────
    if ($status === 'recusada') {
        // Pega pontos linked ainda com Reservado ativo
        $linked = $pdo->prepare("
            SELECT ponto_id FROM campanhas
            WHERE pre_selecao_id=? AND situacao='Reservado' AND ativo=1
        ");
        $linked->execute([$id]);
        $pontosLinked = $linked->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($pontosLinked)) {
            // Encerra as campanhas
            $pdo->prepare("
                UPDATE campanhas SET ativo=0, encerrado_em=NOW()
                WHERE pre_selecao_id=? AND situacao='Reservado' AND ativo=1
            ")->execute([$id]);

            // Libera os pontos → Disponivel
            foreach ($pontosLinked as $pontoId) {
                $pdo->prepare("UPDATE pontos SET situacao='Disponivel' WHERE id=?")
                    ->execute([$pontoId]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'status' => $status]);

} catch (PDOException $e) {
    try { $pdo->rollBack(); } catch (Exception $ex) {}
    error_log("atualizar_status_reserva id=$id status=$status: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'db_error', 'msg' => $e->getMessage()]);
}
