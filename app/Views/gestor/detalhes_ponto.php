<?php
// ==VERSAO-2025-05-21==
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Modo público — acesso via link da pré-seleção (sem login)
$modoPublico = isset($_GET['view']) && $_GET['view'] === 'publico';

if (!$modoPublico && !isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

// Conectar ao banco
try {
    require_once __DIR__ . '/../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// Suporta URL amigável /ponto/009 (slug = número) ou ?id=7
$slug = $_GET['slug'] ?? null;
$id   = $_GET['id']   ?? null;

if ($slug) {
    // Busca pelo número (ex: 009, 42) — sem filtro ativo para máxima compatibilidade
    try {
        $stmt = $pdo->prepare("SELECT * FROM pontos WHERE numero = ? LIMIT 1");
        $stmt->execute([$slug]);
        $ponto = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("detalhes_ponto slug numero={$slug} ERRO: " . $e->getMessage());
        $ponto = null;
    }
    // Fallback: tenta pelo ID numérico
    if (!$ponto && is_numeric($slug)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM pontos WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$slug]);
            $ponto = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("detalhes_ponto slug id={$slug} ERRO: " . $e->getMessage());
            $ponto = null;
        }
    }
    if (!$ponto) {
        error_log("detalhes_ponto: ponto nao encontrado para slug={$slug}");
    }
} elseif ($id && is_numeric($id)) {
    $stmt = $pdo->prepare("SELECT * FROM pontos WHERE id = ?");
    $stmt->execute([$id]);
    $ponto = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $ponto = null;
}

if (!$ponto) {
    http_response_code(404);
    if ($modoPublico) die("Ponto não encontrado. [slug=" . htmlspecialchars($slug ?? 'NULL') . " | id=" . htmlspecialchars($id ?? 'NULL') . " | GET=" . htmlspecialchars(json_encode($_GET)) . "]");
    header("Location: " . (defined('BASE') ? BASE : '') . "/gestor/pontos");
    exit;
}

// Carregar fotos da tabela ponto_fotos (principal primeiro)
// Usa $ponto['id'] — garante funcionamento tanto via ?id= quanto via /ponto/slug
$stmtF = $pdo->prepare("SELECT * FROM ponto_fotos WHERE ponto_id = ? ORDER BY principal DESC, ordem ASC, id ASC");
$stmtF->execute([$ponto['id']]);
$fotos = $stmtF->fetchAll(PDO::FETCH_ASSOC);

// Fallback para campo foto legado se ponto_fotos estiver vazio
if (empty($fotos) && !empty($ponto['foto'])) {
    $fotos = [['id' => 0, 'caminho' => $ponto['foto'], 'principal' => 1, 'ordem' => 0]];
}

$fotosJson = json_encode(array_column($fotos, 'caminho'), JSON_UNESCAPED_UNICODE);

function formatarDataCompleta($data) {
    if (!$data || $data === '0000-00-00') return '-';
    try { return (new DateTime($data))->format('d/m/Y'); }
    catch (Exception $e) { return 'Data inválida'; }
}

function badgeSituacao($situacao) {
    $situacao = trim($situacao);
    $classes = [
        'Disponível' => 'situacao-disponivel',
        'Ocupado'    => 'situacao-ocupado',
        'Reservado'  => 'situacao-reservado',
        'Vencido'    => 'situacao-vencido',
        'Permuta'    => 'situacao-permuta',
        'Bisemana'   => 'situacao-bisemana',
    ];
    $class = $classes[$situacao] ?? 'situacao-outro';
    return "<span class='badge-situacao {$class}'>" . htmlspecialchars($situacao) . "</span>";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <title>Ponto <?= htmlspecialchars($ponto['numero']) ?> · SGI</title>
    <link rel="stylesheet" href="/public/assets/css/detalhes.css">
</head>
<body>

<div class="header">
    <div class="header-content">
        <div class="logo">
            <img src="/public/assets/img/logo.png" alt="SGI · Impakto Mídia OOH" class="logo-img">
        </div>
        <?php if ($modoPublico): ?>
            <span class="badge-publico">📋 Informações do Ponto</span>
        <?php else: ?>
            <a href="/gestor/pontos" class="btn-voltar">← Voltar</a>
        <?php endif; ?>
    </div>
</div>

<div class="container">

    <?php if ($modoPublico): ?>
    <div class="banner-publico">
        ℹ️ Você está visualizando as informações técnicas deste ponto de mídia.
    </div>
    <?php endif; ?>

    <div class="numero-titulo"><?= htmlspecialchars(str_pad($ponto['numero'], 3, '0', STR_PAD_LEFT)) ?></div>

    <div class="grid">
        <!-- Coluna Esquerda -->
        <div class="coluna-esquerda">

            <!-- Informações do Ponto (sempre visível) -->
            <div class="card">
                <h2 class="card-title">Informações do Ponto</h2>
                <div class="info-row">
                    <div class="info-label">Logradouro:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['logradouro'] ?? '-') ?></div>
                </div>
                <?php if (!empty($ponto['descricao'])): ?>
                <div class="info-row">
                    <div class="info-label">Descrição:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['descricao']) ?></div>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <div class="info-label">Bairro:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['bairro'] ?? '-') ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Cidade:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['cidade'] ?? '-') ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Região:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['regiao'] ?? '-') ?></div>
                </div>
                <?php if (!empty($ponto['sentido'])): ?>
                <div class="info-row">
                    <div class="info-label">Sentido:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['sentido']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($ponto['corredor'])): ?>
                <div class="info-row">
                    <div class="info-label">Corredor:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['corredor']) ?></div>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <div class="info-label">Tipo:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['tipo'] ?? '-') ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Formato:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['formato'] ?? '-') ?></div>
                </div>
            </div>

            <!-- Informações Comerciais — OCULTO no modo público -->
            <?php if (!$modoPublico): ?>
            <div class="card">
                <h2 class="card-title">Informações Comerciais</h2>
                <div class="info-row">
                    <div class="info-label">Cliente:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['cliente'] ?? '-') ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Agência:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['agencia'] ?? '-') ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Situação:</div>
                    <div class="info-value"><?= badgeSituacao($ponto['situacao'] ?? 'N/A') ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Início:</div>
                    <div class="info-value"><?= formatarDataCompleta($ponto['inicio_contrato']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Fim:</div>
                    <div class="info-value"><?= formatarDataCompleta($ponto['fim_contrato']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Observações:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['observacoes'] ?? '-') ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Contato:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['contato'] ?? '-') ?></div>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- Coluna Direita -->
        <div class="coluna-direita">
            <!-- Galeria de fotos -->
            <div class="card media-card">
                <h2 class="card-title">📷 Fotos do Ponto <span style="font-size:0.75rem;font-weight:600;color:var(--text-muted)">(<?= count($fotos) ?>)</span></h2>
                <?php if (!empty($fotos)): ?>
                <div class="galeria-container">
                    <div class="galeria-main" id="galMain" onclick="abrirLb(galIdx)">
                        <img id="galMainImg"
                             src="/<?= htmlspecialchars($fotos[0]['caminho']) ?>"
                             alt="Foto do ponto <?= htmlspecialchars($ponto['numero']) ?>"
                             onerror="this.style.display='none'">
                    </div>
                    <?php if (count($fotos) > 1): ?>
                    <div class="galeria-thumbs">
                        <?php foreach ($fotos as $i => $f): ?>
                        <div class="galeria-thumb <?= $i === 0 ? 'ativo' : '' ?>"
                             onclick="trocarFoto(<?= $i ?>)">
                            <img src="/<?= htmlspecialchars($f['caminho']) ?>"
                                 alt="Foto <?= $i + 1 ?>"
                                 onerror="this.parentElement.style.display='none'">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="foto-container">
                    <div class="sem-foto">📸 Sem foto cadastrada</div>
                </div>
                <?php endif; ?>
                <?php if (!$modoPublico && !empty($ponto['id'])): ?>
                <div style="margin-top:0.75rem;text-align:right;">
                    <a href="/gestor/pontos/editar?id=<?= (int)$ponto['id'] ?>&aba=fotos"
                       style="font-size:0.78rem;font-weight:700;color:var(--primary);text-decoration:none;">
                        ✏️ Gerenciar fotos
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Mapa -->
            <div class="card media-card">
                <h2 class="card-title">📍 Localização</h2>
                <?php if (!empty($ponto['latitude']) && !empty($ponto['longitude'])): ?>
                    <div class="mapa-container" id="map"></div>
                    <div class="coordenadas">
                        <strong>Ponto <?= htmlspecialchars($ponto['numero']) ?></strong> — <?= htmlspecialchars($ponto['logradouro']) ?><br>
                        <?= htmlspecialchars($ponto['latitude']) ?>°S <?= htmlspecialchars($ponto['longitude']) ?>°W<br>
                        <a href="https://www.google.com/maps?q=<?= $ponto['latitude'] ?>,<?= $ponto['longitude'] ?>" target="_blank">
                            🌐 Ver no Google Maps
                        </a>
                    </div>
                <?php else: ?>
                    <div style="padding:3rem;text-align:center;color:var(--text-muted);">
                        <div style="font-size:3rem;margin-bottom:1rem;">📍</div>
                        <div style="font-weight:600;">Coordenadas não cadastradas</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Lightbox -->
<div class="lb-overlay" id="lbOverlay" onclick="fecharLb()">
    <?php if (count($fotos) > 1): ?>
    <button class="lb-nav lb-prev" onclick="event.stopPropagation();navLb(-1)">&#8249;</button>
    <?php endif; ?>
    <img class="lb-img" id="lbImg" src="" alt="">
    <?php if (count($fotos) > 1): ?>
    <button class="lb-nav lb-next" onclick="event.stopPropagation();navLb(1)">&#8250;</button>
    <div class="lb-counter" id="lbCounter"></div>
    <?php endif; ?>
</div>

<script>
var galeriaFotos = <?= $fotosJson ?>;
var galIdx = 0;

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
    var counter = document.getElementById('lbCounter');
    if (counter) counter.textContent = (idx + 1) + ' / ' + galeriaFotos.length;
    document.getElementById('lbOverlay').classList.add('aberto');
}
function fecharLb() {
    document.getElementById('lbOverlay').classList.remove('aberto');
    document.getElementById('lbImg').src = '';
}
function navLb(dir) {
    galIdx = (galIdx + dir + galeriaFotos.length) % galeriaFotos.length;
    document.getElementById('lbImg').src = '/' + galeriaFotos[galIdx];
    var counter = document.getElementById('lbCounter');
    if (counter) counter.textContent = (galIdx + 1) + ' / ' + galeriaFotos.length;
}
document.addEventListener('keydown', function(e) {
    if (!document.getElementById('lbOverlay').classList.contains('aberto')) return;
    if (e.key === 'Escape')      fecharLb();
    if (e.key === 'ArrowLeft')   navLb(-1);
    if (e.key === 'ArrowRight')  navLb(1);
});
</script>

<?php if (!empty($ponto['latitude']) && !empty($ponto['longitude'])): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var lat = <?= floatval($ponto['latitude']) ?>;
    var lng = <?= floatval($ponto['longitude']) ?>;

    var map = L.map('map', {
        center: [lat, lng],
        zoom: 17,
        zoomControl: true,
        scrollWheelZoom: true
    });

    // Camada Satélite (Esri) — visual igual ao Google Maps satélite
    var satelite = L.tileLayer(
        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        {
            attribution: 'Tiles © Esri — Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
            maxZoom: 19
        }
    );

    // Camada Ruas (OpenStreetMap)
    var ruas = L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19
        }
    );

    // Satélite como padrão
    satelite.addTo(map);

    // Controle para alternar camadas
    L.control.layers(
        { 'Satélite': satelite, 'Mapa': ruas },
        {},
        { position: 'topright' }
    ).addTo(map);

    // Marcador customizado vermelho
    var icone = L.divIcon({
        className: '',
        html: '<div style="width:14px;height:14px;background:#C0392B;border:3px solid white;border-radius:50%;box-shadow:0 2px 6px rgba(0,0,0,0.4);"></div>',
        iconSize: [14, 14],
        iconAnchor: [7, 7],
        popupAnchor: [0, -12]
    });

    var marker = L.marker([lat, lng], { icon: icone }).addTo(map);

    // Popup com info do ponto
    marker.bindPopup(
        '<div style="font-family:Montserrat,sans-serif;min-width:180px;padding:2px">' +
        '<div style="font-size:14px;font-weight:800;color:#C0392B;margin-bottom:4px">Ponto <?= htmlspecialchars($ponto['numero']) ?></div>' +
        '<div style="font-size:12px;font-weight:600;color:#2c3e50;margin-bottom:3px"><?= htmlspecialchars($ponto['logradouro']) ?></div>' +
        '<div style="font-size:11px;color:#6c757d">📍 <?= htmlspecialchars($ponto['cidade']) ?><?= !empty($ponto['regiao']) ? ' · ' . htmlspecialchars($ponto['regiao']) : '' ?></div>' +
        '</div>',
        { maxWidth: 250 }
    ).openPopup();
});
</script>
<?php endif; ?>

</body>
</html>