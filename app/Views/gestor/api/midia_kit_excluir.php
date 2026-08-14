<?php
/**
 * POST /gestor/midia-kit/excluir
 * Remove um item (case ou divisor) do mídia kit e o arquivo de foto associado.
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

$id = (int)($_POST['id'] ?? 0);
if (!$id) mkJson(['ok' => false, 'erro' => 'id_invalido']);

try {
    $s = $pdo->prepare("SELECT foto, logo FROM midia_kit_paginas WHERE id = ? LIMIT 1");
    $s->execute([$id]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if (!$row) mkJson(['ok' => false, 'erro' => 'nao_encontrado']);

    $pdo->prepare("DELETE FROM midia_kit_paginas WHERE id = ?")->execute([$id]);

    foreach (['foto', 'logo'] as $campo) {
        if (!empty($row[$campo])) {
            $path = __DIR__ . '/../../../../' . $row[$campo];
            if (file_exists($path)) @unlink($path);
        }
    }

    mkJson(['ok' => true]);
} catch (Exception $e) {
    error_log('midia_kit_excluir id=' . $id . ': ' . $e->getMessage());
    mkJson(['ok' => false, 'erro' => 'db_error']);
}
