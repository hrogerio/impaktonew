<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

$paginaAtual = 'mapa';

try {
    require_once __DIR__ . '/../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("Erro na conexão com o banco de dados.");
}

// Pontos com coordenadas cadastradas
$pontos = $pdo->query("
    SELECT p.id, p.numero, p.logradouro, p.bairro, p.cidade, p.regiao,
           p.tipo, p.situacao, p.formato,
           p.latitude  + 0 AS latitude,
           p.longitude + 0 AS longitude,
           COALESCE(
               (SELECT pf.caminho FROM ponto_fotos pf WHERE pf.ponto_id = p.id AND pf.principal = 1 LIMIT 1),
               p.foto
           ) AS foto
    FROM pontos p
    WHERE (p.ativo = 1 OR p.ativo IS NULL)
      AND p.latitude  IS NOT NULL AND p.latitude  != 0
      AND p.longitude IS NOT NULL AND p.longitude != 0
    ORDER BY p.numero ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Quantidade sem coordenadas (para aviso)
$semCoord = (int)$pdo->query("
    SELECT COUNT(*) FROM pontos
    WHERE (ativo = 1 OR ativo IS NULL)
      AND (latitude IS NULL OR latitude = 0 OR longitude IS NULL OR longitude = 0)
")->fetchColumn();

// Opções de filtro (só pontos com coords)
$cidades = $pdo->query("
    SELECT DISTINCT cidade FROM pontos
    WHERE (ativo=1 OR ativo IS NULL) AND latitude IS NOT NULL AND latitude != 0
      AND cidade IS NOT NULL AND cidade != ''
    ORDER BY cidade
")->fetchAll(PDO::FETCH_COLUMN);

$tipos = $pdo->query("
    SELECT DISTINCT tipo FROM pontos
    WHERE (ativo=1 OR ativo IS NULL) AND latitude IS NOT NULL AND latitude != 0
      AND tipo IS NOT NULL AND tipo != ''
    ORDER BY tipo
")->fetchAll(PDO::FETCH_COLUMN);

$pontosJson = json_encode($pontos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

// Centro inicial: média das coordenadas ou fallback SP
$centroLat = count($pontos) ? array_sum(array_column($pontos, 'latitude'))  / count($pontos) : -23.55;
$centroLng = count($pontos) ? array_sum(array_column($pontos, 'longitude')) / count($pontos) : -46.63;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa de Pontos — Impakto Mídia</title>
    <link rel="icon" href="/public/assets/img/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
    <link rel="stylesheet" href="/public/assets/css/mapa.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <style>
        body { overflow: hidden; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="mapa-layout" id="mapaLayout">

    <!-- ══════════════════════════════════════════
         SIDEBAR
    ══════════════════════════════════════════════ -->
    <div class="mapa-sidebar">

        <div class="mapa-sidebar-header">
            <div class="mapa-titulo">📍 Pontos no Mapa</div>
            <div class="mapa-filtros">

                <div class="mapa-busca">
                    <span class="mapa-busca-icon">🔍</span>
                    <input type="text" id="mapaBusca" placeholder="Nº, logradouro, cidade..." autocomplete="off">
                </div>

                <select class="mapa-select" id="mapaFiltroSit">
                    <option value="">Todas as situações</option>
                    <option value="Disponivel">Disponível</option>
                    <option value="Ocupado">Ocupado</option>
                    <option value="Reservado">Reservado</option>
                    <option value="Vencido">Vencido</option>
                    <option value="Permuta">Permuta</option>
                    <option value="Bisemana">Bisemana</option>
                </select>

                <select class="mapa-select" id="mapaFiltroCidade">
                    <option value="">Todas as cidades</option>
                    <?php foreach ($cidades as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="mapa-select" id="mapaFiltroTipo">
                    <option value="">Todos os tipos</option>
                    <?php foreach ($tipos as $t): ?>
                    <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                </select>

                <button class="mapa-btn-limpar" id="mapaBtnLimpar" onclick="limparFiltros()">
                    ✕ Limpar filtros
                </button>
            </div>
        </div>

        <div class="mapa-contador">
            <span>
                <span class="mapa-contador-num" id="mapaContador"><?= count($pontos) ?></span>
                <span class="mapa-contador-label"> pontos</span>
            </span>
            <?php if ($semCoord > 0): ?>
            <span class="mapa-aviso-coord" title="Pontos sem coordenadas cadastradas">
                ⚠️ <?= $semCoord ?> sem coords
            </span>
            <?php endif; ?>
        </div>

        <div class="mapa-lista" id="mapaLista"></div>

    </div>

    <!-- ══════════════════════════════════════════
         MAPA
    ══════════════════════════════════════════════ -->
    <div id="map"></div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
var PONTOS = <?= $pontosJson ?>;
var filtros = { busca: '', situacao: '', cidade: '', tipo: '' };
var _visiveis = [];
var markerGroup;
var map;

// ── Cores por situação ────────────────────────────────────────
var CORES = {
    'Disponivel': '#1a9059', 'Disponível': '#1a9059',
    'Ocupado':    '#dc3545',
    'Reservado':  '#fd7e14',
    'Vencido':    '#6c757d',
    'Permuta':    '#51086e',
    'Bisemana':   '#0284c7'
};
function corSit(sit) { return CORES[sit] || '#888888'; }

// ── Helpers ───────────────────────────────────────────────────
function esc(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function normalizar(str) {
    return (str || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'');
}

// ── Inicializar mapa ──────────────────────────────────────────
function initMap() {
    map = L.map('map', {
        center: [<?= round($centroLat, 6) ?>, <?= round($centroLng, 6) ?>],
        zoom: PONTOS.length === 1 ? 16 : 12,
        zoomControl: true,
        scrollWheelZoom: true
    });

    var ruas = L.tileLayer(
        'https://mt{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}',
        { subdomains: '0123', attribution: '© Google Maps', maxZoom: 20 }
    );
    var satelite = L.tileLayer(
        'https://mt{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',
        { subdomains: '0123', attribution: '© Google Maps', maxZoom: 20 }
    );
    ruas.addTo(map);
    L.control.layers({ 'Mapa': ruas, 'Satélite': satelite }, {}, { position: 'topright' }).addTo(map);

    markerGroup = L.layerGroup().addTo(map);
    renderizar();
}

// ── Criar ícone do marcador ───────────────────────────────────
function criarIcone(sit, destaque) {
    var cor  = corSit(sit);
    var size = destaque ? 18 : 14;
    var border = destaque ? '3px solid white' : '2.5px solid white';
    return L.divIcon({
        className: '',
        html: '<div style="width:'+size+'px;height:'+size+'px;background:'+cor+';border:'+border+';border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,0.45);transition:all 0.15s"></div>',
        iconSize:   [size, size],
        iconAnchor: [size/2, size/2],
        popupAnchor:[0, -size/2 - 2]
    });
}

// ── HTML do popup ─────────────────────────────────────────────
function popupHtml(p) {
    var cor = corSit(p.situacao);
    var html = '<div style="font-family:Montserrat,sans-serif;min-width:200px;max-width:240px">';
    if (p.foto) {
        html += '<img src="/'+esc(p.foto)+'" style="width:100%;height:90px;object-fit:cover;border-radius:6px;margin-bottom:8px;display:block" onerror="this.style.display=\'none\'">';
    }
    html += '<div style="font-size:0.95rem;font-weight:800;color:#C0392B;margin-bottom:3px">Ponto #'+esc(p.numero)+'</div>';
    html += '<div style="font-size:0.8rem;font-weight:600;color:#1a1a1a;margin-bottom:2px">'+esc(p.logradouro)+'</div>';
    if (p.cidade) {
        html += '<div style="font-size:0.72rem;color:#6c757d;margin-bottom:6px">'+esc(p.cidade)+(p.regiao?' · '+esc(p.regiao):'')+'</div>';
    }
    if (p.situacao) {
        html += '<span style="background:'+cor+';color:white;font-size:0.65rem;font-weight:800;padding:2px 8px;border-radius:10px;text-transform:uppercase;letter-spacing:0.5px">'+esc(p.situacao)+'</span>';
    }
    if (p.tipo) {
        html += '<span style="margin-left:4px;background:#f3f4f6;color:#374151;font-size:0.65rem;font-weight:700;padding:2px 8px;border-radius:10px">'+esc(p.tipo)+'</span>';
    }
    html += '<div style="margin-top:8px;display:flex;gap:6px">';
    html += '<a href="/gestor/pontos/detalhes?id='+p.id+'" style="font-size:0.75rem;font-weight:700;color:#C0392B;text-decoration:none">Ver detalhes →</a>';
    html += '<a href="/gestor/pontos/editar?id='+p.id+'" style="font-size:0.75rem;font-weight:700;color:#6c757d;text-decoration:none;margin-left:auto">✏️ Editar</a>';
    html += '</div>';
    html += '</div>';
    return html;
}

// ── Renderizar marcadores e lista ─────────────────────────────
function renderizar() {
    markerGroup.clearLayers();
    _visiveis = [];

    var busca = normalizar(filtros.busca);
    var temFiltro = filtros.busca || filtros.situacao || filtros.cidade || filtros.tipo;

    PONTOS.forEach(function(p, i) {
        if (filtros.situacao && p.situacao !== filtros.situacao) return;
        if (filtros.cidade   && p.cidade   !== filtros.cidade)   return;
        if (filtros.tipo     && p.tipo     !== filtros.tipo)     return;
        if (busca) {
            var campos = [p.numero, p.logradouro, p.bairro, p.cidade, p.regiao];
            if (!campos.some(function(c) { return normalizar(c).indexOf(busca) !== -1; })) return;
        }

        var lat = parseFloat(p.latitude);
        var lng = parseFloat(p.longitude);
        if (isNaN(lat) || isNaN(lng)) return;

        var marker = L.marker([lat, lng], { icon: criarIcone(p.situacao, false) });
        marker.bindPopup(popupHtml(p), { maxWidth: 260 });
        marker._pontoIdx = i;
        marker.addTo(markerGroup);
        _visiveis.push({ p: p, idx: i, marker: marker });
    });

    document.getElementById('mapaContador').textContent = _visiveis.length;
    document.getElementById('mapaBtnLimpar').className = 'mapa-btn-limpar' + (temFiltro ? ' visivel' : '');
    renderLista();

    // Ajusta zoom para conter todos os marcadores visíveis
    if (_visiveis.length > 1) {
        var coords = _visiveis.map(function(v) { return [parseFloat(v.p.latitude), parseFloat(v.p.longitude)]; });
        map.fitBounds(L.latLngBounds(coords), { padding: [40, 40], maxZoom: 14 });
    } else if (_visiveis.length === 1) {
        map.setView([parseFloat(_visiveis[0].p.latitude), parseFloat(_visiveis[0].p.longitude)], 16);
    }
}

function renderLista() {
    var lista = document.getElementById('mapaLista');
    if (_visiveis.length === 0) {
        lista.innerHTML = '<div class="mapa-lista-vazia">Nenhum ponto encontrado</div>';
        return;
    }
    var html = '';
    _visiveis.forEach(function(item) {
        var p   = item.p;
        var cor = corSit(p.situacao);
        html += '<div class="mapa-item" onclick="focarPonto('+item.idx+')" data-idx="'+item.idx+'">';
        html += '<div class="mapa-item-dot" style="background:'+cor+'"></div>';
        html += '<div class="mapa-item-info">';
        html += '<div class="mapa-item-num">#'+esc(p.numero)+'</div>';
        html += '<div class="mapa-item-local">'+esc(p.logradouro)+'</div>';
        if (p.cidade) html += '<div class="mapa-item-cidade">'+esc(p.cidade)+(p.regiao?' · '+esc(p.regiao):'')+'</div>';
        html += '</div></div>';
    });
    lista.innerHTML = html;
}

// ── Focar ponto ao clicar na lista ───────────────────────────
function focarPonto(idx) {
    var item = _visiveis.find(function(v) { return v.idx === idx; });
    if (!item) return;

    // Destaca item da lista
    document.querySelectorAll('.mapa-item').forEach(function(el) { el.classList.remove('ativo'); });
    var el = document.querySelector('.mapa-item[data-idx="'+idx+'"]');
    if (el) { el.classList.add('ativo'); el.scrollIntoView({ block:'nearest', behavior:'smooth' }); }

    map.flyTo([parseFloat(item.p.latitude), parseFloat(item.p.longitude)], 17, { duration: 0.7 });
    setTimeout(function() { item.marker.openPopup(); }, 750);
}

// ── Filtros ───────────────────────────────────────────────────
var debTimer;
document.getElementById('mapaBusca').addEventListener('input', function() {
    clearTimeout(debTimer);
    var val = this.value;
    debTimer = setTimeout(function() { filtros.busca = val; renderizar(); }, 150);
});

document.getElementById('mapaFiltroSit').addEventListener('change', function() {
    filtros.situacao = this.value;
    this.className = 'mapa-select' + (this.value ? ' ativo' : '');
    renderizar();
});
document.getElementById('mapaFiltroCidade').addEventListener('change', function() {
    filtros.cidade = this.value;
    this.className = 'mapa-select' + (this.value ? ' ativo' : '');
    renderizar();
});
document.getElementById('mapaFiltroTipo').addEventListener('change', function() {
    filtros.tipo = this.value;
    this.className = 'mapa-select' + (this.value ? ' ativo' : '');
    renderizar();
});

function limparFiltros() {
    filtros = { busca: '', situacao: '', cidade: '', tipo: '' };
    document.getElementById('mapaBusca').value = '';
    ['mapaFiltroSit','mapaFiltroCidade','mapaFiltroTipo'].forEach(function(id) {
        document.getElementById(id).value = '';
        document.getElementById(id).className = 'mapa-select';
    });
    renderizar();
}

// ── Altura do layout (viewport - header) ─────────────────────
function ajustarAltura() {
    var header = document.querySelector('.header');
    var h = window.innerHeight - (header ? header.offsetHeight : 60);
    document.getElementById('mapaLayout').style.height = h + 'px';
}
ajustarAltura();
window.addEventListener('resize', function() { ajustarAltura(); if (map) map.invalidateSize(); });

// ── Init ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', initMap);
</script>

</body>
</html>
