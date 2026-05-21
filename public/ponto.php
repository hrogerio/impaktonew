<?php
$slug = preg_replace('/[^a-zA-Z0-9\-]/', '', $_GET['slug'] ?? '');
if (!$slug) { http_response_code(404); die('Ponto não encontrado.'); }

require_once __DIR__ . '/../config/database.php';
$pdo = getDatabase();

$pontoId = null;

// Busca pelo numero
try {
    $s = $pdo->prepare("SELECT id FROM pontos WHERE numero = ? LIMIT 1");
    $s->execute([$slug]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if ($row) $pontoId = $row['id'];
} catch (PDOException $e) {}

// Fallback: busca pelo id
if (!$pontoId && is_numeric($slug)) {
    try {
        $s = $pdo->prepare("SELECT id FROM pontos WHERE id = ? LIMIT 1");
        $s->execute([(int)$slug]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if ($row) $pontoId = $row['id'];
    } catch (PDOException $e) {}
}

if (!$pontoId) { http_response_code(404); die('Ponto não encontrado.'); }

// Redireciona para URL que o OPcache antigo já suporta (?id=X&view=publico)
header("Location: /gestor/pontos/detalhes?id={$pontoId}&view=publico");
exit;
