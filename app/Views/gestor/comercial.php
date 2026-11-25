<?php
/**
 * GET /gestor/comercial
 * Central Comercial: concentra documentos e links comerciais da
 * Impakto Mídia e da Impakto Led para acesso rápido.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

$paginaAtual = 'comercial';

try {
    require_once __DIR__ . '/../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("Erro na conexão com o banco de dados.");
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

$empresas = [
    'midia' => ['nome' => 'Impakto Mídia', 'sub' => 'Mídia exterior OOH · painéis e outdoors'],
    'led'   => ['nome' => 'Impakto Led',   'sub' => 'Painéis de LED · mídia digital'],
];
$empresaAtual = ($_GET['empresa'] ?? '') === 'led' ? 'led' : 'midia';

// Ordem fixa das categorias padrão; categorias novas aparecem depois, em ordem alfabética
$categoriasPadrao = [
    'Apresentação'        => '🎯',
    'Mídia Kit'           => '🖼️',
    'Tabela de Preços'    => '💰',
    'Propostas e Modelos' => '📝',
    'Contratos'           => '📑',
    'Dados da Empresa'    => '🏢',
    'Portfólio / Cases'   => '⭐',
    'Links Úteis'         => '🔗',
    'Outros'              => '📁',
];

$itens = [];
$tabelaOk = true;
try {
    $stmt = $pdo->query("SELECT * FROM comercial_itens ORDER BY destaque DESC, titulo ASC");
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $tabelaOk = false;
    error_log('comercial: ' . $e->getMessage());
}

$porEmpresa = ['midia' => [], 'led' => []];
$categoriasUsadas = [];
foreach ($itens as $it) {
    $porEmpresa[$it['empresa']][$it['categoria']][] = $it;
    $categoriasUsadas[$it['categoria']] = true;
}
$todasCategorias = array_unique(array_merge(array_keys($categoriasPadrao), array_keys($categoriasUsadas)));

function comercialOrdenarCategorias(array $grupos, array $padrao): array {
    $ordem = array_flip(array_keys($padrao));
    uksort($grupos, function ($a, $b) use ($ordem) {
        $oa = $ordem[$a] ?? 999; $ob = $ordem[$b] ?? 999;
        return $oa === $ob ? strcasecmp($a, $b) : $oa <=> $ob;
    });
    return $grupos;
}

function comercialTamanho(?int $bytes): string {
    if (!$bytes) return '';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    return max(1, round($bytes / 1024)) . ' KB';
}

function comercialIcone(array $it): string {
    if ($it['tipo'] === 'link') {
        $host = parse_url($it['url'] ?? '', PHP_URL_HOST) ?: '';
        if (strpos($host, 'drive.google') !== false || strpos($host, 'docs.google') !== false) return '🟢';
        if (strpos($host, 'instagram') !== false) return '📸';
        if (strpos($host, 'youtube') !== false || strpos($host, 'youtu.be') !== false) return '▶️';
        if (strpos($host, 'wa.me') !== false || strpos($host, 'whatsapp') !== false) return '💬';
        return '🔗';
    }
    $ext = strtolower(pathinfo($it['caminho'] ?? '', PATHINFO_EXTENSION));
    $mapa = [
        'pdf' => '📕', 'ppt' => '📙', 'pptx' => '📙', 'doc' => '📘', 'docx' => '📘',
        'xls' => '📗', 'xlsx' => '📗', 'csv' => '📗', 'zip' => '🗜️',
        'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'webp' => '🖼️', 'gif' => '🖼️',
        'mp4' => '🎬', 'mov' => '🎬', 'cdr' => '🎨', 'ai' => '🎨', 'psd' => '🎨',
    ];
    return $mapa[$ext] ?? '📄';
}

$mensagem = '';
$erro = '';
$msgs = ['criado' => 'Item adicionado.', 'atualizado' => 'Item atualizado.', 'excluido' => 'Item excluído.'];
$erros = [
    'titulo'         => 'Informe um título.',
    'url'            => 'Informe um link válido, começando com http:// ou https://.',
    'sem_arquivo'    => 'Selecione um arquivo para enviar.',
    'formato'        => 'Formato de arquivo não permitido.',
    'arquivo_grande' => 'Arquivo grande demais para o servidor (limite: ' . ini_get('upload_max_filesize') . '). Para arquivos maiores, use um link do Google Drive.',
    'upload'         => 'Falha ao enviar o arquivo. Tente novamente.',
    'nao_encontrado' => 'Item não encontrado.',
    'db'             => 'Erro ao salvar no banco de dados.',
];
if (isset($_GET['msg'])) $mensagem = $msgs[$_GET['msg']] ?? '';
if (isset($_GET['erro'])) $erro = $erros[$_GET['erro']] ?? 'Erro desconhecido.';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central Comercial · Impakto</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css?v=2">
    <link rel="stylesheet" href="/public/assets/css/backup.css">
    <link rel="stylesheet" href="/public/assets/css/comercial.css?v=2">
</head>
<body>
<?php include __DIR__ . '/../partials/env_banner.php'; ?>
<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="container com-container">

    <div class="welcome com-topo">
        <div>
            <h2>📁 Central Comercial</h2>
            <p>Apresentações, tabelas, modelos e links das nossas marcas — tudo num só lugar.</p>
        </div>
        <button type="button" class="btn-backup btn-baixar com-btn-novo" onclick="comAbrirModal()">➕ Adicionar</button>
    </div>

    <?php if (!$tabelaOk): ?>
        <div class="alerta alerta-err">⚠️ A tabela <code>comercial_itens</code> ainda não existe. Rode a migration <code>database/migrations/criar_comercial_itens.sql</code>.</div>
    <?php endif; ?>
    <?php if ($mensagem): ?><div class="alerta alerta-ok">✅ <?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alerta alerta-err">⚠️ <?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <!-- Abas por empresa -->
    <div class="com-abas" role="tablist">
        <?php foreach ($empresas as $chave => $emp):
            $qtd = 0; foreach ($porEmpresa[$chave] as $g) $qtd += count($g); ?>
        <button type="button" role="tab" class="com-aba com-aba-<?= $chave ?><?= $chave === $empresaAtual ? ' ativa' : '' ?>"
                data-empresa="<?= $chave ?>" onclick="comTrocarAba('<?= $chave ?>')">
            <span class="com-aba-nome"><?= htmlspecialchars($emp['nome']) ?></span>
            <span class="com-aba-sub"><?= htmlspecialchars($emp['sub']) ?></span>
            <span class="com-aba-qtd"><?= $qtd ?></span>
        </button>
        <?php endforeach; ?>
    </div>

    <div class="com-busca">
        <input type="search" id="comBusca" placeholder="🔎 Buscar documento, link ou categoria..." oninput="comFiltrar()" autocomplete="off">
    </div>

    <?php foreach ($empresas as $chave => $emp):
        $grupos = comercialOrdenarCategorias($porEmpresa[$chave], $categoriasPadrao);
        $destaques = [];
        foreach ($grupos as $lista) foreach ($lista as $it) if ($it['destaque']) $destaques[] = $it;
    ?>
    <section class="com-painel" data-empresa="<?= $chave ?>"<?= $chave === $empresaAtual ? '' : ' hidden' ?>>

        <?php if ($destaques): ?>
        <div class="com-grupo">
            <h3 class="com-grupo-titulo">📌 Mais usados</h3>
            <div class="com-cards">
                <?php foreach ($destaques as $it) { $comCard = $it; include __DIR__ . '/comercial_card.php'; } ?>
            </div>
        </div>
        <?php endif; ?>

        <?php foreach ($grupos as $categoria => $lista):
            $lista = array_values(array_filter($lista, fn($i) => !$i['destaque']));
            if (!$lista) continue; ?>
        <div class="com-grupo">
            <h3 class="com-grupo-titulo"><?= $categoriasPadrao[$categoria] ?? '📁' ?> <?= htmlspecialchars($categoria) ?> <span class="com-grupo-qtd"><?= count($lista) ?></span></h3>
            <div class="com-cards">
                <?php foreach ($lista as $it) { $comCard = $it; include __DIR__ . '/comercial_card.php'; } ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (!$grupos): ?>
        <div class="com-vazio">
            <div class="com-vazio-icone">📂</div>
            <p>Nenhum documento da <strong><?= htmlspecialchars($emp['nome']) ?></strong> ainda.</p>
            <button type="button" class="btn-backup btn-baixar com-btn-novo" onclick="comAbrirModal()">➕ Adicionar o primeiro</button>
        </div>
        <?php endif; ?>

        <div class="com-sem-resultado" hidden>Nada encontrado para essa busca.</div>
    </section>
    <?php endforeach; ?>
</div>

<!-- Modal adicionar/editar -->
<div class="com-modal-fundo" id="comModal" hidden onclick="if (event.target === this) comFecharModal()">
    <form class="com-modal" method="POST" action="/gestor/comercial/salvar" enctype="multipart/form-data" id="comForm">
        <div class="com-modal-topo">
            <h3 id="comModalTitulo">Adicionar item</h3>
            <button type="button" class="com-modal-fechar" onclick="comFecharModal()" aria-label="Fechar">✕</button>
        </div>

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="salvar">
        <input type="hidden" name="id" id="comId" value="">

        <label class="com-campo">
            <span>Empresa</span>
            <div class="com-segmento">
                <label><input type="radio" name="empresa" value="midia" checked> Impakto Mídia</label>
                <label><input type="radio" name="empresa" value="led"> Impakto Led</label>
            </div>
        </label>

        <label class="com-campo">
            <span>Categoria</span>
            <input type="text" name="categoria" id="comCategoria" list="comCategorias" required maxlength="80" placeholder="Escolha ou digite uma nova">
            <datalist id="comCategorias">
                <?php foreach ($todasCategorias as $c): ?><option value="<?= htmlspecialchars($c) ?>"><?php endforeach; ?>
            </datalist>
        </label>

        <label class="com-campo">
            <span>Título</span>
            <input type="text" name="titulo" id="comTitulo" required maxlength="200" placeholder="Ex.: Apresentação institucional 2026">
        </label>

        <label class="com-campo">
            <span>Descrição <em>(opcional)</em></span>
            <input type="text" name="descricao" id="comDescricao" maxlength="500" placeholder="Ex.: versão enviada para agências">
        </label>

        <div class="com-campo">
            <span>Tipo</span>
            <div class="com-segmento">
                <label><input type="radio" name="tipo" value="arquivo" checked onchange="comAtualizarTipo()"> 📎 Arquivo</label>
                <label><input type="radio" name="tipo" value="link" onchange="comAtualizarTipo()"> 🔗 Link</label>
            </div>
        </div>

        <label class="com-campo" id="comCampoArquivo">
            <span>Arquivo <em id="comArquivoAtual"></em></span>
            <input type="file" name="arquivo" id="comArquivo"
                   accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,.ppt,.pptx,.doc,.docx,.xls,.xlsx,.csv,.zip,.mp4,.mov,.cdr,.ai,.psd">
            <small>PDF, imagens, PowerPoint, Word, Excel, ZIP, vídeo · até <?= htmlspecialchars(ini_get('upload_max_filesize')) ?></small>
        </label>

        <label class="com-campo" id="comCampoLink" hidden>
            <span>Link</span>
            <input type="url" name="url" id="comUrl" maxlength="1000" placeholder="https://drive.google.com/...">
        </label>

        <label class="com-check">
            <input type="checkbox" name="destaque" id="comDestaque" value="1"> 📌 Mostrar em “Mais usados”
        </label>

        <div class="com-modal-acoes">
            <button type="button" class="com-btn-sec" onclick="comFecharModal()">Cancelar</button>
            <button type="submit" class="btn-backup btn-baixar com-btn-novo" id="comSalvar">Salvar</button>
        </div>
    </form>
</div>

<!-- Form oculto para excluir / destacar -->
<form method="POST" action="/gestor/comercial/salvar" id="comFormAcao" hidden>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="action" value="">
    <input type="hidden" name="id" value="">
    <input type="hidden" name="empresa" value="">
</form>

<div class="com-toast" id="comToast" hidden></div>

<script src="/public/assets/js/comercial.js?v=1"></script>
</body>
</html>
