<?php
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

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    if ($modoPublico) die("Ponto não encontrado.");
    header("Location: " . (defined('BASE') ? BASE : '') . "/gestor/pontos");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM pontos WHERE id = ?");
$stmt->execute([$id]);
$ponto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ponto) {
    if ($modoPublico) die("Ponto não encontrado.");
    header("Location: " . (defined('BASE') ? BASE : '') . "/gestor/pontos");
    exit;
}

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
    <link rel="icon" href="/public/assets/img/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <title>Ponto <?= htmlspecialchars($ponto['numero']) ?> - Impakto Mídia</title>
    <link rel="stylesheet" href="/public/assets/css/detalhes.css">
</head>
<body>

<div class="header">
    <div class="header-content">
        <div class="logo">
            <img src="/public/assets/img/logo.png" alt="Impakto Mídia" class="logo-img">
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
                <div class="info-row">
                    <div class="info-label">Descrição:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['descricao'] ?? '-') ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Sentido:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['sentido'] ?? '-') ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Cidade:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['cidade'] ?? '-') ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Região:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['regiao'] ?? '-') ?></div>
                </div>
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
            <!-- Foto -->
            <div class="card media-card">
                <h2 class="card-title">📷 Foto do Ponto</h2>
                <div class="foto-container">
                    <?php if (!empty($ponto['foto'])): ?>
                        <img src="/<?= htmlspecialchars($ponto['foto']) ?>"
                             alt="Foto do ponto <?= htmlspecialchars($ponto['numero']) ?>"
                             onerror="this.parentElement.innerHTML='<div class=\'sem-foto\'>Foto não disponível</div>'">
                    <?php else: ?>
                        <div class="sem-foto">📸 Sem foto cadastrada</div>
                    <?php endif; ?>
                </div>
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