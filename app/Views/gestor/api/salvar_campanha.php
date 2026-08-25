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
$nome       = trim($body['nome']       ?? '');
$inicio     = trim($body['inicio']     ?? '');
$fim        = trim($body['fim']        ?? '');
$contato    = trim($body['contato']    ?? '');
$obs        = trim($body['observacoes'] ?? '');
$usuario    = $_SESSION['usuario'] ?? '';

if (!$pontoId) responderSalvar(['erro'=>'ponto_id invalido']);

// Liga o texto do cliente ao cadastro (clientes): casa por razão social
// (case-insensitive) ou cria um cadastro mínimo automaticamente.
function resolverClienteId(PDO $pdo, string $cliente): ?int {
    if ($cliente === '') return null;

    $busca = $pdo->prepare("SELECT id FROM clientes WHERE LOWER(TRIM(razao_social)) = LOWER(TRIM(?)) LIMIT 1");
    $busca->execute([$cliente]);
    $existente = $busca->fetchColumn();
    if ($existente) return (int)$existente;

    $pdo->prepare("INSERT INTO clientes (razao_social, ativo, criado_por) VALUES (?, 1, 'auto-campanha')")
        ->execute([$cliente]);
    return (int)$pdo->lastInsertId();
}
$clienteId = resolverClienteId($pdo, $cliente);

// Mesma lógica pro cadastro de agências: casa por nome (case-insensitive) ou
// cria um cadastro mínimo, senão a campanha nunca linka com a ficha da agência.
function resolverAgenciaId(PDO $pdo, string $agencia): ?int {
    if ($agencia === '' || strtolower($agencia) === 'direto') return null;

    $busca = $pdo->prepare("SELECT id FROM agencias WHERE LOWER(TRIM(nome)) = LOWER(TRIM(?)) LIMIT 1");
    $busca->execute([$agencia]);
    $existente = $busca->fetchColumn();
    if ($existente) return (int)$existente;

    $pdo->prepare("INSERT INTO agencias (nome, ativo, criado_por) VALUES (?, 1, 'auto-campanha')")
        ->execute([$agencia]);
    return (int)$pdo->lastInsertId();
}
$agenciaId = resolverAgenciaId($pdo, $agencia);

// Verifica se ponto existe
$sp = $pdo->prepare("SELECT id, situacao FROM pontos WHERE id = ? AND (ativo=1 OR ativo IS NULL) LIMIT 1");
$sp->execute([$pontoId]);
$ponto = $sp->fetch(PDO::FETCH_ASSOC);
if (!$ponto) responderSalvar(['erro'=>'ponto nao encontrado']);

try {
    $pdo->beginTransaction();

    if ($campanhaId > 0) {
        // ── EDITAR campanha existente ──────────────────────
        // checking_fotos e campanha_documentos são vinculados por chave composta
        // (cliente/campanha/situacao/inicio/fim), não por campanha_id. Precisamos
        // buscar os valores antigos para poder "migrar" os arquivos já enviados
        // para a nova chave, senão eles ficam órfãos e somem da tela.
        $so = $pdo->prepare("SELECT cliente, agencia, campanha, situacao, inicio, fim, ativo FROM campanhas WHERE id=? AND ponto_id=? LIMIT 1");
        $so->execute([$campanhaId, $pontoId]);
        $antiga = $so->fetch(PDO::FETCH_ASSOC);
        // Situação não é mais escolhida no form — preserva a que já estava
        // gravada (pode ser Reservado/Permuta/Bisemana definida por outro fluxo).
        $situacao = $antiga['situacao'] ?? 'Ocupado';

        $stmt = $pdo->prepare("
            UPDATE campanhas
            SET cliente=?, cliente_id=?, agencia=?, agencia_id=?, campanha=?, nome=?, situacao=?,
                inicio=?, fim=?, contato=?, observacoes=?
            WHERE id=? AND ponto_id=?
        ");
        $stmt->execute([
            $cliente ?: null, $clienteId, $agencia ?: null, $agenciaId, $campanha ?: null, $nome ?: null, $situacao,
            $inicio ?: null,  $fim ?: null,     $contato ?: null,  $obs ?: null,
            $campanhaId, $pontoId
        ]);

        if ($antiga) {
            $pdo->prepare("
                UPDATE checking_fotos
                SET cliente=?, agencia=?, campanha=?, situacao=?, inicio=?, fim=?
                WHERE ponto_id=? AND cliente<=>? AND campanha<=>? AND situacao<=>? AND inicio<=>? AND fim<=>?
            ")->execute([
                $cliente ?: '', $agencia ?: '', $campanha ?: '', $situacao, $inicio ?: null, $fim ?: null,
                $pontoId,
                $antiga['cliente'], $antiga['campanha'], $antiga['situacao'], $antiga['inicio'], $antiga['fim'],
            ]);

            $pdo->prepare("
                UPDATE campanha_documentos
                SET cliente=?, agencia=?, campanha=?, inicio=?, fim=?
                WHERE cliente<=>? AND agencia<=>? AND campanha<=>? AND inicio<=>? AND fim<=>?
            ")->execute([
                $cliente ?: '', $agencia ?: '', $campanha ?: '', $inicio ?: null, $fim ?: null,
                $antiga['cliente'], $antiga['agencia'], $antiga['campanha'], $antiga['inicio'], $antiga['fim'],
            ]);
        }
    } else {
        // Situação não é mais escolhida no form — toda campanha nova entra
        // como Ocupado; Vencido é calculado na exibição a partir da data fim.
        $situacao = 'Ocupado';

        // ── NOVA campanha: encerra a atual (se houver) ─────
        $pdo->prepare("
            UPDATE campanhas
            SET ativo=0, encerrado_em=NOW()
            WHERE ponto_id=? AND ativo=1
        ")->execute([$pontoId]);

        // Cria nova campanha
        $stmt = $pdo->prepare("
            INSERT INTO campanhas (ponto_id, cliente, cliente_id, agencia, agencia_id, campanha, nome, situacao, inicio, fim, contato, observacoes, ativo, criado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
        ");
        $stmt->execute([
            $pontoId,
            $cliente ?: null, $clienteId, $agencia ?: null, $agenciaId, $campanha ?: null, $nome ?: null,
            $situacao,
            $inicio ?: null, $fim ?: null,
            $contato ?: null, $obs ?: null,
            $usuario
        ]);
        $campanhaId = (int)$pdo->lastInsertId();
    }

    // Sincroniza situação do ponto — só quando a campanha editada é a vigente
    // (ativo=1). Editar uma campanha já encerrada não pode sobrescrever a
    // situação atual do ponto, que pode já pertencer a outra campanha.
    $campanhaEhVigente = (!empty($antiga)) ? (int)$antiga['ativo'] === 1 : true;
    if ($campanhaEhVigente) {
        $pdo->prepare("UPDATE pontos SET situacao=? WHERE id=?")->execute([$situacao, $pontoId]);
    }

    $pdo->commit();
    responderSalvar(['ok' => true, 'campanha_id' => $campanhaId, 'situacao' => $situacao]);
} catch (PDOException $e) {
    try { $pdo->rollBack(); } catch (Exception $ex) {}
    error_log("salvar_campanha ponto=$pontoId: " . $e->getMessage());
    responderSalvar(['erro' => 'db_error', 'msg' => $e->getMessage()]);
}
