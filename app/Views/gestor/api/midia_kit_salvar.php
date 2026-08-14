<?php
/**
 * POST /gestor/midia-kit/salvar
 * Cria ou atualiza um item (case ou divisor) do mídia kit.
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

$id          = (int)($_POST['id'] ?? 0);
$tipo        = trim($_POST['tipo'] ?? 'case');
$pontoId     = (int)($_POST['ponto_id'] ?? 0) ?: null;
$titulo      = trim($_POST['titulo'] ?? '');
$subtitulo   = trim($_POST['subtitulo'] ?? '') ?: null;
$localizacao = trim($_POST['localizacao'] ?? '') ?: null;
$foto        = trim($_POST['foto'] ?? '') ?: null;
$logo        = trim($_POST['logo'] ?? '') ?: null;
$ativo       = isset($_POST['ativo']) ? (int)!!$_POST['ativo'] : 1;
$usuario     = $_SESSION['usuario'] ?? '';

if (!in_array($tipo, ['case', 'divisor'], true)) mkJson(['ok' => false, 'erro' => 'tipo_invalido']);

if ($titulo === '') mkJson(['ok' => false, 'erro' => 'titulo_obrigatorio']);
if ($tipo === 'case') {
    if (!$foto && !$id) mkJson(['ok' => false, 'erro' => 'foto_obrigatoria']);
    if (!$logo && !$id) mkJson(['ok' => false, 'erro' => 'logo_obrigatoria']);
}

try {
    if ($id) {
        // Atualização — mantém foto/logo atuais se nada novo foi enviado
        $campos = ['tipo=?', 'ponto_id=?', 'titulo=?', 'subtitulo=?', 'localizacao=?', 'ativo=?'];
        $params = [$tipo, $pontoId, $titulo, $subtitulo, $localizacao, $ativo];
        if ($foto) { $campos[] = 'foto=?'; $params[] = $foto; }
        if ($logo) { $campos[] = 'logo=?'; $params[] = $logo; }
        $params[] = $id;

        $sql = "UPDATE midia_kit_paginas SET " . implode(', ', $campos) . " WHERE id=?";
        $pdo->prepare($sql)->execute($params);
        mkJson(['ok' => true, 'id' => $id]);
    } else {
        $maxOrdem = (int)$pdo->query("SELECT COALESCE(MAX(ordem), 0) FROM midia_kit_paginas")->fetchColumn();
        $ins = $pdo->prepare("
            INSERT INTO midia_kit_paginas (tipo, ponto_id, titulo, subtitulo, localizacao, foto, logo, ordem, ativo, criado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([$tipo, $pontoId, $titulo, $subtitulo, $localizacao, $foto, $logo, $maxOrdem + 10, $ativo, $usuario]);
        mkJson(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    }
} catch (Exception $e) {
    error_log('midia_kit_salvar: ' . $e->getMessage());
    mkJson(['ok' => false, 'erro' => 'db_error']);
}
