<?php
// ==VERSAO-2025-05-25==
if (session_status() === PHP_SESSION_NONE) session_start();

$modoPublico = isset($_GET['view']) && $_GET['view'] === 'publico';

if (!$modoPublico && !isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

try {
    require_once __DIR__ . '/../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("Erro na conexão: " . $e->getMessage());
}

$slug = $_GET['slug'] ?? null;
$id   = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($slug) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM pontos WHERE numero = ? LIMIT 1");
        $stmt->execute([$slug]);
        $ponto = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $ponto = null; }
    if (!$ponto && is_numeric($slug)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM pontos WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$slug]);
            $ponto = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { $ponto = null; }
    }
} elseif ($id) {
    $stmt = $pdo->prepare("SELECT * FROM pontos WHERE id = ?");
    $stmt->execute([$id]);
    $ponto = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $ponto = null;
}

if (!$ponto) {
    http_response_code(404);
    if (!$modoPublico) { header("Location: " . (defined('BASE') ? BASE : '') . "/gestor/pontos"); exit; }
    die("Ponto não encontrado.");
}

// Fotos
$stmtF = $pdo->prepare("SELECT * FROM ponto_fotos WHERE ponto_id = ? ORDER BY principal DESC, ordem ASC, id ASC");
$stmtF->execute([$ponto['id']]);
$fotos = $stmtF->fetchAll(PDO::FETCH_ASSOC);
if (empty($fotos) && !empty($ponto['foto'])) {
    $fotos = [['id' => 0, 'caminho' => $ponto['foto'], 'principal' => 1, 'ordem' => 0]];
}
$fotosJson = json_encode(array_column($fotos, 'caminho'), JSON_UNESCAPED_UNICODE);

function fmtData($d) {
    if (!$d || $d === '0000-00-00') return '—';
    try { return (new DateTime($d))->format('d/m/Y'); } catch (Exception $e) { return '—'; }
}

$SITUACOES = [
    'Disponivel'  => ['label'=>'Disponível',  'color'=>'#1a9059'],
    'Disponível'  => ['label'=>'Disponível',  'color'=>'#1a9059'],
    'Ocupado'     => ['label'=>'Ocupado',     'color'=>'#dc3545'],
    'Reservado'   => ['label'=>'Reservado',   'color'=>'#fd7e14'],
    'Vencido'     => ['label'=>'Vencido',     'color'=>'#6c757d'],
    'Permuta'     => ['label'=>'Permuta',     'color'=>'#51086e'],
    'Bisemana'    => ['label'=>'Bisemana',    'color'=>'#0284c7'],
];
$sit = $ponto['situacao'] ?? '';
$sitCor   = $SITUACOES[$sit]['color']   ?? '#888';
$sitLabel = $SITUACOES[$sit]['label']   ?? $sit;

$paginaAtual = 'pontos'; // nav active
$numFmt = str_pad($ponto['numero'] ?? '', 3, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/public/assets/img/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <title>Ponto <?= htmlspecialchars($numFmt) ?> — <?= htmlspecialchars($ponto['logradouro'] ?? '') ?></title>
    <?php if (!$modoPublico): ?>
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
    <?php endif; ?>
    <link rel="stylesheet" href="/public/assets/css/detalhes.css">
</head>
<body class="<?= $modoPublico ? 'modo-publico' : 'modo-admin' ?>">

<?php if (!$modoPublico): ?>
<?php require __DIR__ . '/../layouts/_nav.php'; ?>
<?php else: ?>
<!-- Cabeçalho público simples -->
<div class="pub-header">
    <div class="pub-header-inner">
        <img src="/public/assets/img/logo.png" alt="Impakto Mídia" class="pub-logo">
        <span class="pub-badge">📋 Informações do Ponto</span>
    </div>
</div>
<?php endif; ?>

<!-- ── HERO ──────────────────────────────────────────────── -->
<div class="det-hero">
    <div class="det-hero-inner">
        <div class="det-hero-left">
            <div class="det-num"><?= htmlspecialchars($numFmt) ?></div>
            <div class="det-hero-text">
                <div class="det-logradouro"><?= htmlspecialchars($ponto['logradouro'] ?? '—') ?></div>
                <div class="det-local">
                    <?php
                    $localParts = array_filter([
                        $ponto['bairro']  ?? '',
                        $ponto['cidade']  ?? '',
                        $ponto['regiao']  ?? '',
                    ]);
                    echo htmlspecialchars(implode(' · ', $localParts));
                    ?>
                </div>
            </div>
        </div>
        <div class="det-hero-right">
            <span class="sit-hero" style="background:<?= $sitCor ?>">
                <?= htmlspecialchars($sitLabel ?: '—') ?>
            </span>
            <?php if (!$modoPublico): ?>
            <div class="det-hero-actions">
                <a href="/gestor/pontos/editar?id=<?= (int)$ponto['id'] ?>" class="btn-det btn-editar">✏️ Editar</a>
                <button class="btn-det btn-cart" id="btnCart" onclick="toggleCart()">🛒 Adicionar</button>
                <a href="/gestor/pontos" class="btn-det btn-voltar">← Pontos</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── CONTEÚDO ────────────────────────────────────────── -->
<div class="det-page">
<div class="det-grid">

    <!-- ── Coluna esquerda: info ───────────────────────── -->
    <div class="det-left">

        <!-- Localização -->
        <div class="det-card">
            <div class="det-card-head">📍 Localização</div>
            <div class="det-fields">
                <div class="det-field"><span class="det-lbl">Logradouro</span><span class="det-val"><?= htmlspecialchars($ponto['logradouro'] ?? '—') ?></span></div>
                <?php if (!empty($ponto['descricao'])): ?>
                <div class="det-field"><span class="det-lbl">Descrição</span><span class="det-val"><?= htmlspecialchars($ponto['descricao']) ?></span></div>
                <?php endif; ?>
                <div class="det-field"><span class="det-lbl">Bairro</span><span class="det-val"><?= htmlspecialchars($ponto['bairro'] ?? '—') ?></span></div>
                <div class="det-field"><span class="det-lbl">Cidade</span><span class="det-val"><?= htmlspecialchars($ponto['cidade'] ?? '—') ?></span></div>
                <div class="det-field"><span class="det-lbl">Região</span><span class="det-val"><?= htmlspecialchars($ponto['regiao'] ?? '—') ?></span></div>
                <?php if (!empty($ponto['sentido'])): ?>
                <div class="det-field"><span class="det-lbl">Sentido</span><span class="det-val"><?= htmlspecialchars($ponto['sentido']) ?></span></div>
                <?php endif; ?>
                <?php if (!empty($ponto['corredor'])): ?>
                <div class="det-field"><span class="det-lbl">Corredor</span><span class="det-val"><?= htmlspecialchars($ponto['corredor']) ?></span></div>
                <?php endif; ?>
                <?php if (!empty($ponto['latitude']) && !empty($ponto['longitude'])): ?>
                <div class="det-field">
                    <span class="det-lbl">Coordenadas</span>
                    <span class="det-val">
                        <?= htmlspecialchars($ponto['latitude']) ?>, <?= htmlspecialchars($ponto['longitude']) ?>
                        <a href="https://www.google.com/maps?q=<?= $ponto['latitude'] ?>,<?= $ponto['longitude'] ?>" target="_blank" class="link-maps">🌐 Maps</a>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Técnico -->
        <div class="det-card">
            <div class="det-card-head">📐 Técnico</div>
            <div class="det-fields">
                <div class="det-field"><span class="det-lbl">Tipo</span><span class="det-val"><?= htmlspecialchars($ponto['tipo'] ?? '—') ?></span></div>
                <div class="det-field"><span class="det-lbl">Formato</span><span class="det-val"><?= htmlspecialchars($ponto['formato'] ?? '—') ?></span></div>
            </div>
        </div>

        <!-- Comercial — admin only -->
        <?php if (!$modoPublico): ?>
        <div class="det-card det-card-comercial">
            <div class="det-card-head">💼 Comercial</div>
            <div class="det-fields">
                <div class="det-field"><span class="det-lbl">Situação</span>
                    <span class="det-val">
                        <span class="sit-badge" style="background:<?= $sitCor ?>"><?= htmlspecialchars($sitLabel ?: '—') ?></span>
                    </span>
                </div>
                <div class="det-field"><span class="det-lbl">Cliente</span><span class="det-val"><?= htmlspecialchars($ponto['cliente'] ?? '—') ?></span></div>
                <div class="det-field"><span class="det-lbl">Agência</span><span class="det-val"><?= htmlspecialchars($ponto['agencia'] ?? '—') ?></span></div>
                <div class="det-field"><span class="det-lbl">Início</span><span class="det-val"><?= fmtData($ponto['inicio_contrato']) ?></span></div>
                <div class="det-field">
                    <span class="det-lbl">Fim</span>
                    <span class="det-val">
                        <?php
                        $fimStr = fmtData($ponto['fim_contrato']);
                        if ($ponto['fim_contrato'] && $ponto['fim_contrato'] !== '0000-00-00') {
                            $dias = (new DateTime($ponto['fim_contrato']))->diff(new DateTime())->days;
                            $futuro = new DateTime($ponto['fim_contrato']) >= new DateTime();
                            if ($futuro && $dias <= 7)        echo '<span class="prazo-badge prazo-urgente">' . $dias . 'd</span> ';
                            elseif ($futuro && $dias <= 30)   echo '<span class="prazo-badge prazo-alerta">' . $dias . 'd</span> ';
                        }
                        echo htmlspecialchars($fimStr);
                        ?>
                    </span>
                </div>
                <div class="det-field"><span class="det-lbl">Contato</span><span class="det-val"><?= htmlspecialchars($ponto['contato'] ?? '—') ?></span></div>
                <?php if (!empty($ponto['observacoes'])): ?>
                <div class="det-field det-field-obs"><span class="det-lbl">Observações</span><span class="det-val"><?= nl2br(htmlspecialchars($ponto['observacoes'])) ?></span></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /det-left -->

    <!-- ── Coluna direita: mídia ───────────────────────── -->
    <div class="det-right">

        <!-- Galeria -->
        <div class="det-card det-card-media">
            <div class="det-card-head">
                📷 Fotos
                <span class="det-count"><?= count($fotos) ?></span>
                <?php if (!$modoPublico && !empty($ponto['id'])): ?>
                <a href="/gestor/pontos/editar?id=<?= (int)$ponto['id'] ?>&aba=fotos" class="det-gerenciar">✏️ Gerenciar</a>
                <?php endif; ?>
            </div>
            <?php if (!empty($fotos)): ?>
            <div class="galeria-container">
                <div class="galeria-main" id="galMain" onclick="abrirLb(galIdx)">
                    <img id="galMainImg"
                         src="/<?= htmlspecialchars($fotos[0]['caminho']) ?>"
                         alt="Foto ponto <?= htmlspecialchars($numFmt) ?>"
                         onerror="this.style.display='none'">
                    <div class="galeria-zoom-hint">🔍</div>
                </div>
                <?php if (count($fotos) > 1): ?>
                <div class="galeria-thumbs">
                    <?php foreach ($fotos as $i => $f): ?>
                    <div class="galeria-thumb <?= $i === 0 ? 'ativo' : '' ?>" onclick="trocarFoto(<?= $i ?>)">
                        <img src="/<?= htmlspecialchars($f['caminho']) ?>"
                             alt="Foto <?= $i + 1 ?>"
                             onerror="this.parentElement.style.display='none'">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="sem-foto">
                <div class="sem-foto-icon">📸</div>
                <div>Sem foto cadastrada</div>
                <?php if (!$modoPublico): ?>
                <a href="/gestor/pontos/editar?id=<?= (int)$ponto['id'] ?>&aba=fotos" class="btn-det btn-editar" style="margin-top:0.75rem">+ Adicionar foto</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Mapa -->
        <div class="det-card det-card-media">
            <div class="det-card-head">📍 Localização no Mapa</div>
            <?php if (!empty($ponto['latitude']) && !empty($ponto['longitude'])): ?>
            <div class="mapa-container" id="map"></div>
            <div class="mapa-footer">
                <span><?= htmlspecialchars($ponto['latitude']) ?>°, <?= htmlspecialchars($ponto['longitude']) ?>°</span>
                <a href="https://www.google.com/maps?q=<?= $ponto['latitude'] ?>,<?= $ponto['longitude'] ?>" target="_blank">🌐 Abrir no Google Maps</a>
            </div>
            <?php else: ?>
            <div class="sem-foto" style="height:220px">
                <div class="sem-foto-icon">📍</div>
                <div>Coordenadas não cadastradas</div>
                <?php if (!$modoPublico): ?>
                <a href="/gestor/pontos/editar?id=<?= (int)$ponto['id'] ?>" class="btn-det btn-editar" style="margin-top:0.75rem">+ Adicionar coordenadas</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /det-right -->
</div><!-- /det-grid -->
</div><!-- /det-page -->

<!-- ── Lightbox ─────────────────────────────────────────── -->
<div class="lb-overlay" id="lbOverlay" onclick="fecharLb()">
    <?php if (count($fotos) > 1): ?>
    <button class="lb-nav lb-prev" onclick="event.stopPropagation();navLb(-1)">&#8249;</button>
    <?php endif; ?>
    <img class="lb-img" id="lbImg" src="" alt="">
    <?php if (count($fotos) > 1): ?>
    <button class="lb-nav lb-next" onclick="event.stopPropagation();navLb(1)">&#8250;</button>
    <div class="lb-counter" id="lbCounter"></div>
    <?php endif; ?>
    <button class="lb-close" onclick="fecharLb()">✕</button>
</div>

<!-- ── Toast ────────────────────────────────────────────── -->
<div class="det-toast" id="detToast"></div>

<script>
var galeriaFotos = <?= $fotosJson ?>;
var galIdx = 0;
var PONTO_ID = <?= (int)$ponto['id'] ?>;

// ── Galeria ─────────────────────────────────────────────
function trocarFoto(idx) {
    galIdx = idx;
    document.getElementById('galMainImg').src = '/' + galeriaFotos[idx];
    document.querySelectorAll('.galeria-thumb').forEach(function(t, i) {
        t.classList.toggle('ativo', i === idx);
    });
}
function abrirLb(idx) {
    if (!galeriaFotos.length) return;
    galIdx = idx;
    document.getElementById('lbImg').src = '/' + galeriaFotos[idx];
    var c = document.getElementById('lbCounter');
    if (c) c.textContent = (idx + 1) + ' / ' + galeriaFotos.length;
    document.getElementById('lbOverlay').classList.add('aberto');
    document.body.style.overflow = 'hidden';
}
function fecharLb() {
    document.getElementById('lbOverlay').classList.remove('aberto');
    document.getElementById('lbImg').src = '';
    document.body.style.overflow = '';
}
function navLb(dir) {
    galIdx = (galIdx + dir + galeriaFotos.length) % galeriaFotos.length;
    document.getElementById('lbImg').src = '/' + galeriaFotos[galIdx];
    var c = document.getElementById('lbCounter');
    if (c) c.textContent = (galIdx + 1) + ' / ' + galeriaFotos.length;
}
document.addEventListener('keydown', function(e) {
    if (!document.getElementById('lbOverlay').classList.contains('aberto')) return;
    if (e.key === 'Escape')    fecharLb();
    if (e.key === 'ArrowLeft') navLb(-1);
    if (e.key === 'ArrowRight') navLb(1);
});

// ── Carrinho ─────────────────────────────────────────────
<?php if (!$modoPublico): ?>
function getCart() {
    try { return JSON.parse(localStorage.getItem('impakto_cart') || '[]'); } catch(e) { return []; }
}
function setCart(c) { localStorage.setItem('impakto_cart', JSON.stringify(c)); }
function atualizarBtnCart() {
    var c = getCart();
    var btn = document.getElementById('btnCart');
    if (!btn) return;
    var noCart = c.indexOf(PONTO_ID) === -1 && c.indexOf(String(PONTO_ID)) === -1;
    if (noCart) {
        btn.textContent = '🛒 Adicionar';
        btn.classList.remove('btn-cart-active');
    } else {
        btn.textContent = '✅ No carrinho';
        btn.classList.add('btn-cart-active');
    }
}
function toggleCart() {
    var c = getCart();
    var idx = c.indexOf(PONTO_ID);
    var idx2 = c.indexOf(String(PONTO_ID));
    if (idx !== -1 || idx2 !== -1) {
        c = c.filter(function(x){ return x != PONTO_ID; });
        setCart(c);
        mostrarToast('Removido do carrinho.');
    } else {
        c.push(PONTO_ID);
        setCart(c);
        mostrarToast('✅ Adicionado ao carrinho! Total: ' + c.length + ' ponto(s).');
    }
    atualizarBtnCart();
}
atualizarBtnCart();
<?php endif; ?>

// ── Toast ─────────────────────────────────────────────────
function mostrarToast(msg) {
    var t = document.getElementById('detToast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(function() { t.classList.remove('show'); }, 3000);
}
</script>

<?php
$gmKey = getenv('GOOGLE_MAPS_KEY');
if (!empty($ponto['latitude']) && !empty($ponto['longitude']) && $gmKey):
?>
<script>
function initMapPonto() {
    var pos = { lat: <?= floatval($ponto['latitude']) ?>, lng: <?= floatval($ponto['longitude']) ?> };

    var map = new google.maps.Map(document.getElementById('map'), {
        center: pos,
        zoom: 18,
        mapTypeId: 'hybrid',
        mapTypeControl: true,
        mapTypeControlOptions: {
            style: google.maps.MapTypeControlStyle.HORIZONTAL_BAR,
            position: google.maps.ControlPosition.TOP_RIGHT,
            mapTypeIds: ['hybrid', 'roadmap', 'satellite']
        },
        streetViewControl: true,
        fullscreenControl: true,
        zoomControl: true,
        gestureHandling: 'cooperative',
        styles: []
    });

    var marker = new google.maps.Marker({
        position: pos,
        map: map,
        title: 'Ponto <?= htmlspecialchars(addslashes($ponto['numero'])) ?>',
        icon: {
            path: google.maps.SymbolPath.CIRCLE,
            fillColor: '#C0392B',
            fillOpacity: 1,
            strokeColor: '#ffffff',
            strokeWeight: 3,
            scale: 10
        },
        animation: google.maps.Animation.DROP
    });

    var infoWindow = new google.maps.InfoWindow({
        content:
            '<div style="font-family:Montserrat,sans-serif;padding:4px 2px;min-width:180px">' +
            '<div style="font-size:14px;font-weight:800;color:#C0392B;margin-bottom:4px">Ponto <?= htmlspecialchars(addslashes($numFmt)) ?></div>' +
            '<div style="font-size:12px;font-weight:700;color:#1a1a1a;margin-bottom:2px"><?= htmlspecialchars(addslashes($ponto['logradouro'] ?? '')) ?></div>' +
            '<div style="font-size:11px;color:#6c757d"><?= htmlspecialchars(addslashes(implode(' · ', array_filter([$ponto['bairro'] ?? '', $ponto['cidade'] ?? ''])))) ?></div>' +
            '</div>'
    });

    infoWindow.open(map, marker);
    marker.addListener('click', function () {
        infoWindow.open(map, marker);
    });
}
</script>
<script
    src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($gmKey) ?>&callback=initMapPonto&language=pt-BR&region=BR"
    async defer>
</script>
<?php endif; ?>

</body>
</html>
