<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /gestor/agencias/importar");
    exit;
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    die("Token de segurança inválido.");
}

require_once __DIR__ . '/../../../../config/database.php';
$pdo = getDatabase();

$nomes = $_POST['nomes'] ?? [];
if (!is_array($nomes) || empty($nomes)) {
    header("Location: /gestor/agencias/importar?erro=" . urlencode("Selecione ao menos uma agência."));
    exit;
}

$insAgencia = $pdo->prepare("INSERT INTO agencias (nome, ativo, criado_por) VALUES (?, 1, ?)");
$vinculaCampanhas = $pdo->prepare("UPDATE campanhas SET agencia_id = ? WHERE agencia_id IS NULL AND LOWER(TRIM(agencia)) = LOWER(TRIM(?))");

$usuario = $_SESSION['usuario'] ?? null;
$criadas = 0;

$pdo->beginTransaction();
try {
    foreach ($nomes as $nomeBruto) {
        $nome = trim((string)$nomeBruto);
        if ($nome === '') continue;

        // Evita duplicar se já foi criada entre o carregamento da lista e o envio do form
        $chk = $pdo->prepare("SELECT id FROM agencias WHERE LOWER(TRIM(nome)) = LOWER(TRIM(?)) LIMIT 1");
        $chk->execute([$nome]);
        $existente = $chk->fetchColumn();

        $agenciaId = $existente ?: null;
        if (!$agenciaId) {
            $insAgencia->execute([$nome, $usuario]);
            $agenciaId = (int)$pdo->lastInsertId();
            $criadas++;
        }

        $vinculaCampanhas->execute([$agenciaId, $nome]);
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("importar_agencias: " . $e->getMessage());
    header("Location: /gestor/agencias/importar?erro=" . urlencode("Erro ao importar agências."));
    exit;
}

header("Location: /gestor/agencias?msg=importado&qtd=" . $criadas);
exit;
