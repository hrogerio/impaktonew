<?php
/**
 * GET /gestor/campanhas/buscar?busca=&cliente=&situacao=
 * Retorna o HTML dos cards de campanha filtrados. Só é chamado quando o
 * usuário efetivamente aplica um filtro — a tela de Campanhas não carrega
 * a lista inteira de cara.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['erro' => 'nao_logado']);
    exit;
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../campanhas/_helpers.php';

try {
    $pdo = getDatabase();

    $busca    = strtolower(trim($_GET['busca'] ?? ''));
    $cliente  = strtolower(trim($_GET['cliente'] ?? ''));
    $campanhaFiltro = strtolower(trim($_GET['campanha'] ?? ''));
    $situacao = trim($_GET['situacao'] ?? '');
    if (!in_array($situacao, ['', 'Encerradas', 'Vencidas', 'Reservadas'], true)) $situacao = '';

    $hoje   = date('Y-m-d');
    $grupos = campanhasBuscarGrupos($pdo, $situacao);

    $html = '';
    $total = 0;
    foreach ($grupos as $g) {
        if ($cliente !== '' && strtolower($g['cliente']) !== $cliente) continue;
        if ($campanhaFiltro !== '' && strtolower($g['titulo']) !== $campanhaFiltro) continue;
        if ($busca !== '' && strpos(campanhaBuscaStr($g), $busca) === false) continue;
        $html .= renderCampanhaCard($g, $CORES, $hoje);
        $total++;
    }

    echo json_encode(['ok' => true, 'html' => $html, 'total' => $total], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('campanhas_buscar (situacao=' . ($situacao ?? '?') . '): ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'erro_interno'], JSON_UNESCAPED_UNICODE);
}
