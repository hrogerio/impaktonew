<?php
/**
 * POST /gestor/midia-kit/mover
 * Troca a ordem de um item com seu vizinho (direcao: 'cima' | 'baixo').
 */
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();

function mkJson($data) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['usuario']))          mkJson(['ok' => false, 'erro' => 'nao_logado']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') mkJson(['ok' => false, 'erro' => 'metodo_invalido']);

$csrf = $_POST['csrf_token'] ?? '';
if ($csrf !== ($_SESSION['csrf_token'] ?? '')) mkJson(['ok' => false, 'erro' => 'csrf_invalido']);

require_once __DIR__ . '/../../../../config/database.php';
$pdo = getDatabase();

$id        = (int)($_POST['id'] ?? 0);
$direcao   = trim($_POST['direcao'] ?? '');
if (!$id || !in_array($direcao, ['cima', 'baixo'], true)) mkJson(['ok' => false, 'erro' => 'parametros_invalidos']);

try {
    $s = $pdo->prepare("SELECT id, ordem FROM midia_kit_paginas WHERE id = ? LIMIT 1");
    $s->execute([$id]);
    $atual = $s->fetch(PDO::FETCH_ASSOC);
    if (!$atual) mkJson(['ok' => false, 'erro' => 'nao_encontrado']);

    if ($direcao === 'cima') {
        $sv = $pdo->prepare("SELECT id, ordem FROM midia_kit_paginas WHERE ordem < ? ORDER BY ordem DESC LIMIT 1");
    } else {
        $sv = $pdo->prepare("SELECT id, ordem FROM midia_kit_paginas WHERE ordem > ? ORDER BY ordem ASC LIMIT 1");
    }
    $sv->execute([$atual['ordem']]);
    $vizinho = $sv->fetch(PDO::FETCH_ASSOC);
    if (!$vizinho) mkJson(['ok' => true]); // já está na ponta, nada a fazer

    $pdo->beginTransaction();
    $up = $pdo->prepare("UPDATE midia_kit_paginas SET ordem = ? WHERE id = ?");
    $up->execute([$vizinho['ordem'], $atual['id']]);
    $up->execute([$atual['ordem'], $vizinho['id']]);
    $pdo->commit();

    mkJson(['ok' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('midia_kit_mover id=' . $id . ': ' . $e->getMessage());
    mkJson(['ok' => false, 'erro' => 'db_error']);
}
