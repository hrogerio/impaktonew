<?php
require_once __DIR__ . '/../../Controllers/PontoController.php';

$id = validateId($_GET['id'] ?? 0);
if (!$id) {
    echo "<p>ID inválido.</p>";
    exit;
}

$controller = new PontoController();
$ponto = $controller->buscarPorId($id);

if (!$ponto) {
    echo "<p>Ponto não encontrado.</p>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Ponto <?= htmlspecialchars($ponto['numero'] ?? '') ?> - Impakto</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .ponto-info { max-width: 800px; margin: 0 auto; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .info-item { margin-bottom: 10px; }
        .info-item strong { color: #333; }
        .badge { padding: 4px 8px; border-radius: 4px; color: white; font-size: 12px; }
        .disponivel { background: #27AE60; }
        .ocupado { background: #C0392B; }
        .reservado { background: #E67E22; }
        .imagem-ponto { max-width: 100%; height: auto; border-radius: 8px; }
    </style>
</head>
<body>

<div class="ponto-info">
    <h1>Ponto <?= htmlspecialchars($ponto['numero'] ?? '') ?></h1>
    
    <div class="info-grid">
        <div class="detalhes">
            <div class="info-item">
                <strong>Logradouro:</strong> <?= htmlspecialchars($ponto['logradouro'] ?? '') ?>
            </div>
            <div class="info-item">
                <strong>Descrição:</strong> <?= htmlspecialchars($ponto['descricao'] ?? '') ?>
            </div>
            <div class="info-item">
                <strong>Cidade:</strong> <?= htmlspecialchars($ponto['cidade'] ?? '') ?>
            </div>
            <div class="info-item">
                <strong>Região:</strong> <?= htmlspecialchars($ponto['regiao'] ?? '') ?>
            </div>
            <div class="info-item">
                <strong>Cliente:</strong> <?= htmlspecialchars($ponto['cliente'] ?? '') ?>
            </div>
            <div class="info-item">
                <strong>Agência:</strong> <?= htmlspecialchars($ponto['agencia'] ?? '') ?>
            </div>
            <div class="info-item">
                <strong>Tipo:</strong> <?= htmlspecialchars($ponto['tipo'] ?? '') ?>
            </div>
            <div class="info-item">
                <strong>Situação:</strong>
                <span class="badge <?= strtolower($ponto['situacao'] ?? '') ?>">
                    <?= htmlspecialchars($ponto['situacao'] ?? '') ?>
                </span>
            </div>
            <div class="info-item">
                <strong>Início:</strong> <?= htmlspecialchars($ponto['inicio_contrato'] ?? '-') ?>
            </div>
            <div class="info-item">
                <strong>Fim:</strong> <?= htmlspecialchars($ponto['fim_contrato'] ?? '-') ?>
            </div>
        </div>
        
        <div class="visual">
            <?php if (!empty($ponto['foto'])): ?>
                <img src="/<?= htmlspecialchars($ponto['foto']) ?>" alt="Foto do ponto" class="imagem-ponto">
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!empty($ponto['observacoes'])): ?>
        <div class="info-item">
            <strong>Observações:</strong><br>
            <div style="background: #f5f5f5; padding: 10px; border-radius: 4px; margin-top: 5px;">
                <?= nl2br(htmlspecialchars($ponto['observacoes'])) ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 20px;">
        <button onclick="window.close()" class="btn">Fechar</button>
    </div>
</div>

</body>
</html>