<?php
/**
 * POST /gestor/financeiro/pedidos/salvar
 * Cria ou atualiza um Pedido de Inserção (PI) / Pedido de Produção (PP).
 * Body JSON: { id?, tipo, status, data_emissao, periodo_inicio, periodo_fim, nome_campanha,
 *              cliente_razao_social, cliente_cnpj, cliente_ie, cliente_endereco,
 *              cliente_cidade, cliente_cep, cliente_telefone, cliente_email,
 *              observacoes, qtd_parcelas, valor_bruto,
 *              itens: [{campo1,campo2,quantidade,valor_unitario,valor_total}],
 *              parcelas: [{numero,data_vencimento,valor}] }
 */
ini_set('display_errors', 0);
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();

function responderPedido($dados) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados);
    exit;
}

if (!isset($_SESSION['usuario']))          responderPedido(['erro' => 'nao_logado']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') responderPedido(['erro' => 'metodo_invalido']);

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../financeiro/_helpers.php';
$pdo = getDatabase();

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$id     = (int)($body['id'] ?? 0);
$tipo   = strtoupper(trim($body['tipo'] ?? ''));
$status = ($body['status'] ?? 'rascunho') === 'emitido' ? 'emitido' : 'rascunho';
$usuario = $_SESSION['usuario'] ?? '';

if (!in_array($tipo, ['PI', 'PP'], true)) responderPedido(['erro' => 'tipo_invalido']);

$dataEmissao   = trim($body['data_emissao'] ?? '') ?: date('Y-m-d');
$nomeCampanha  = trim($body['nome_campanha'] ?? '') ?: null;

// Selects de mês/ano (<input type="month"> => "AAAA-MM") viram o 1º dia do mês
function mesParaData(string $mes): ?string {
    $mes = trim($mes);
    if (!preg_match('/^\d{4}-\d{2}$/', $mes)) return null;
    return $mes . '-01';
}
$periodoInicio = mesParaData($body['periodo_inicio'] ?? '');
$periodoFim    = mesParaData($body['periodo_fim'] ?? '');

$clienteRazao  = trim($body['cliente_razao_social'] ?? '');
$clienteCnpj   = trim($body['cliente_cnpj'] ?? '') ?: null;
$clienteIe     = trim($body['cliente_ie'] ?? '') ?: null;
$clienteEnd    = trim($body['cliente_endereco'] ?? '') ?: null;
$clienteCidade = trim($body['cliente_cidade'] ?? '') ?: null;
$clienteCep    = trim($body['cliente_cep'] ?? '') ?: null;
$clienteTel    = trim($body['cliente_telefone'] ?? '') ?: null;
$clienteEmail  = trim($body['cliente_email'] ?? '') ?: null;

$observacoes  = trim($body['observacoes'] ?? '') ?: null;
$qtdParcelas  = max(1, (int)($body['qtd_parcelas'] ?? 1));

if ($clienteRazao === '') responderPedido(['erro' => 'cliente_obrigatorio']);

// Itens: normaliza e descarta linhas totalmente vazias
$itensBrutos = is_array($body['itens'] ?? null) ? $body['itens'] : [];
$itens = [];
foreach ($itensBrutos as $it) {
    $campo1 = trim($it['campo1'] ?? '');
    $campo2 = trim($it['campo2'] ?? '');
    $qtd    = ($it['quantidade'] ?? '') !== '' ? (float)$it['quantidade'] : null;
    $vUnit  = ($it['valor_unitario'] ?? '') !== '' ? (float)$it['valor_unitario'] : null;
    $vTotal = (float)($it['valor_total'] ?? 0);

    if ($campo1 === '' && $campo2 === '' && $vTotal == 0) continue; // linha em branco
    $itens[] = [$campo1 ?: null, $campo2 ?: null, $qtd, $vUnit, $vTotal];
}
if (empty($itens)) responderPedido(['erro' => 'itens_obrigatorios']);

$valorTotal = array_sum(array_column($itens, 4)); // valor mensal (soma dos itens)
$valorBruto = ($body['valor_bruto'] ?? '') !== '' ? (float)$body['valor_bruto'] : $valorTotal * $qtdParcelas;

// Parcelas: normaliza e descarta linhas sem data
$parcelasBrutas = is_array($body['parcelas'] ?? null) ? $body['parcelas'] : [];
$parcelas = [];
foreach ($parcelasBrutas as $i => $p) {
    $dataVenc = trim($p['data_vencimento'] ?? '');
    if ($dataVenc === '') continue;
    $valorParcela = ($p['valor'] ?? '') !== '' ? (float)$p['valor'] : null;
    $parcelas[] = [$i + 1, $dataVenc, $valorParcela];
}

try {
    $pdo->beginTransaction();

    if ($id > 0) {
        // ── EDITAR pedido existente ──────────────────────
        $sp = $pdo->prepare("SELECT id, tipo, numero_data, numero_seq FROM pedidos_financeiros WHERE id = ? LIMIT 1");
        $sp->execute([$id]);
        $existente = $sp->fetch(PDO::FETCH_ASSOC);
        if (!$existente) { $pdo->rollBack(); responderPedido(['erro' => 'pedido_nao_encontrado']); }
        if ($existente['tipo'] !== $tipo) { $pdo->rollBack(); responderPedido(['erro' => 'tipo_nao_pode_mudar']); }

        $stmt = $pdo->prepare("
            UPDATE pedidos_financeiros SET
                status=?, data_emissao=?, periodo_inicio=?, periodo_fim=?, nome_campanha=?,
                cliente_razao_social=?, cliente_cnpj=?, cliente_ie=?, cliente_endereco=?,
                cliente_cidade=?, cliente_cep=?, cliente_telefone=?, cliente_email=?,
                observacoes=?, qtd_parcelas=?, valor_total=?, valor_bruto=?
            WHERE id=?
        ");
        $stmt->execute([
            $status, $dataEmissao, $periodoInicio, $periodoFim, $nomeCampanha,
            $clienteRazao, $clienteCnpj, $clienteIe, $clienteEnd,
            $clienteCidade, $clienteCep, $clienteTel, $clienteEmail,
            $observacoes, $qtdParcelas, $valorTotal, $valorBruto,
            $id,
        ]);
        // Numeração é definida na criação e nunca muda depois.
        $numeroData = $existente['numero_data'];
        $numeroSeq  = (int)$existente['numero_seq'];

        $pdo->prepare("DELETE FROM pedidos_financeiros_itens WHERE pedido_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM pedidos_financeiros_parcelas WHERE pedido_id = ?")->execute([$id]);
        $pedidoId = $id;
    } else {
        // ── NOVO pedido: numeração = data de emissão + sequência do dia ──
        $numeroData = $dataEmissao;
        $stmtNum = $pdo->prepare("SELECT COALESCE(MAX(numero_seq),0) FROM pedidos_financeiros WHERE tipo = ? AND numero_data = ? FOR UPDATE");
        $stmtNum->execute([$tipo, $numeroData]);
        $numeroSeq = (int)$stmtNum->fetchColumn() + 1;

        $stmt = $pdo->prepare("
            INSERT INTO pedidos_financeiros
                (tipo, numero_data, numero_seq, status, data_emissao, periodo_inicio, periodo_fim, nome_campanha,
                 cliente_razao_social, cliente_cnpj, cliente_ie, cliente_endereco,
                 cliente_cidade, cliente_cep, cliente_telefone, cliente_email,
                 observacoes, qtd_parcelas, valor_total, valor_bruto, criado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $tipo, $numeroData, $numeroSeq, $status, $dataEmissao, $periodoInicio, $periodoFim, $nomeCampanha,
            $clienteRazao, $clienteCnpj, $clienteIe, $clienteEnd,
            $clienteCidade, $clienteCep, $clienteTel, $clienteEmail,
            $observacoes, $qtdParcelas, $valorTotal, $valorBruto, $usuario,
        ]);
        $pedidoId = (int)$pdo->lastInsertId();
    }

    $insItem = $pdo->prepare("
        INSERT INTO pedidos_financeiros_itens (pedido_id, ordem, campo1, campo2, quantidade, valor_unitario, valor_total)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($itens as $i => [$campo1, $campo2, $qtd, $vUnit, $vTotal]) {
        $insItem->execute([$pedidoId, $i, $campo1, $campo2, $qtd, $vUnit, $vTotal]);
    }

    $insParcela = $pdo->prepare("
        INSERT INTO pedidos_financeiros_parcelas (pedido_id, numero, data_vencimento, valor)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($parcelas as [$numeroParcela, $dataVenc, $valorParcela]) {
        $insParcela->execute([$pedidoId, $numeroParcela, $dataVenc, $valorParcela]);
    }

    $pdo->commit();

    responderPedido([
        'ok' => true, 'id' => $pedidoId, 'tipo' => $tipo,
        'numero' => numeroPedidoFmt($tipo, $numeroData, $numeroSeq),
    ]);
} catch (PDOException $e) {
    try { $pdo->rollBack(); } catch (Exception $ex) {}
    error_log("pedido_salvar id={$id}: " . $e->getMessage());
    responderPedido(['erro' => 'db_error', 'msg' => $e->getMessage()]);
}
