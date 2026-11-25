<?php
/** Card de um item da Central Comercial. Espera $comCard (linha de comercial_itens). */
$ehLink  = $comCard['tipo'] === 'link';
$href    = $ehLink ? $comCard['url'] : '/' . $comCard['caminho'];
$ext     = $ehLink ? '' : strtoupper(pathinfo($comCard['caminho'] ?? '', PATHINFO_EXTENSION));
$meta    = $ehLink ? (parse_url($comCard['url'], PHP_URL_HOST) ?: 'link') : trim($ext . ' · ' . comercialTamanho($comCard['tamanho'] ? (int)$comCard['tamanho'] : null), ' ·');
$dataRef = $comCard['atualizado_em'] ?: $comCard['criado_em'];
$dados   = [
    'id' => (int)$comCard['id'], 'empresa' => $comCard['empresa'], 'categoria' => $comCard['categoria'],
    'titulo' => $comCard['titulo'], 'descricao' => $comCard['descricao'], 'tipo' => $comCard['tipo'],
    'url' => $comCard['url'], 'nome_original' => $comCard['nome_original'], 'destaque' => (int)$comCard['destaque'],
];
$busca = mb_strtolower($comCard['titulo'] . ' ' . $comCard['descricao'] . ' ' . $comCard['categoria'] . ' ' . ($comCard['nome_original'] ?? '') . ' ' . $ext);
?>
<div class="com-card com-filtravel" data-busca="<?= htmlspecialchars($busca) ?>">
    <a href="<?= htmlspecialchars($href) ?>" target="_blank" rel="noopener" class="com-card-principal" title="Abrir">
        <span class="com-card-icone"><?= comercialIcone($comCard) ?></span>
        <span class="com-card-texto">
            <strong><?= htmlspecialchars($comCard['titulo']) ?></strong>
            <?php if ($comCard['descricao'] !== ''): ?><small><?= htmlspecialchars($comCard['descricao']) ?></small><?php endif; ?>
            <span class="com-card-meta"><?= htmlspecialchars($meta) ?> · <?= date('d/m/Y', strtotime($dataRef)) ?></span>
        </span>
    </a>
    <div class="com-card-acoes">
        <?php if (!$ehLink): ?>
        <a href="<?= htmlspecialchars($href) ?>" download="<?= htmlspecialchars($comCard['nome_original'] ?: basename($comCard['caminho'])) ?>" class="com-acao" title="Baixar">⬇️</a>
        <?php endif; ?>
        <button type="button" class="com-acao" title="Copiar link" onclick="comCopiarLink(<?= htmlspecialchars(json_encode($href), ENT_QUOTES) ?>)">📋</button>
        <button type="button" class="com-acao<?= $comCard['destaque'] ? ' ativo' : '' ?>" title="<?= $comCard['destaque'] ? 'Tirar de Mais usados' : 'Fixar em Mais usados' ?>"
                onclick="comAcao('destaque', <?= (int)$comCard['id'] ?>, '<?= $comCard['empresa'] ?>')">📌</button>
        <button type="button" class="com-acao" title="Editar" onclick='comAbrirModal(<?= htmlspecialchars(json_encode($dados, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)'>✏️</button>
        <button type="button" class="com-acao" title="Excluir"
                onclick="comAcao('excluir', <?= (int)$comCard['id'] ?>, '<?= $comCard['empresa'] ?>', <?= htmlspecialchars(json_encode($comCard['titulo'], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)">🗑️</button>
    </div>
</div>
