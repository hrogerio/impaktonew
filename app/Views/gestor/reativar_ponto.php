<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /gestor/pontos");
    exit;
}
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    die("Token de segurança inválido.");
}

// Aceita um único `id` (tela de edição) ou `ids[]` (reativação em lote na tela de inativos)
$ids = [];
if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = array_map('intval', $_POST['ids']);
} elseif (isset($_POST['id'])) {
    $ids = [(int)$_POST['id']];
}
$ids = array_values(array_unique(array_filter($ids, fn($i) => $i > 0)));
$voltarInativos = ($_POST['voltar'] ?? '') === 'inativos';

if (!$ids) {
    header("Location: " . ($voltarInativos ? "/gestor/pontos-inativos" : "/gestor/pontos"));
    exit;
}

require_once __DIR__ . '/../../../config/database.php';
$pdo = getDatabase();

$upd = $pdo->prepare("UPDATE pontos SET ativo = 1 WHERE id = ? AND ativo = 0");
require_once __DIR__ . '/../../Services/PontoAtivoLog.php';
$reativados = 0;
foreach ($ids as $id) {
    $upd->execute([$id]);
    if ($upd->rowCount() > 0) {
        $reativados++;
        registrarMudancaAtivo($pdo, $id, 1, $_SESSION['usuario']);
    }
}

if ($voltarInativos) {
    header("Location: /gestor/pontos-inativos?msg=reativados&qtd=" . $reativados);
} else {
    header("Location: /gestor/pontos/editar?id=" . $ids[0] . "&msg=reativado");
}
exit;
