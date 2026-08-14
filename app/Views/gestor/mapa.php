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
           c.cliente AS cliente,
           p.latitude  + 0 AS latitude,
           p.longitude + 0 AS longitude,
           COALESCE(
               (SELECT pf.caminho FROM ponto_fotos pf WHERE pf.ponto_id = p.id AND pf.principal = 1 LIMIT 1),
               p.foto
           ) AS foto
    FROM pontos p
    LEFT JOIN campanhas c ON c.ponto_id = p.id AND c.ativo = 1 AND c.situacao IN ('Ocupado','Vencido')
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

$clientes = $pdo->query("
    SELECT DISTINCT c.cliente AS cliente
    FROM pontos p
    LEFT JOIN campanhas c ON c.ponto_id = p.id AND c.ativo = 1 AND c.situacao IN ('Ocupado','Vencido')
    WHERE (p.ativo=1 OR p.ativo IS NULL)
      AND p.latitude IS NOT NULL AND p.latitude != 0
      AND c.cliente IS NOT NULL
      AND c.cliente != ''
    ORDER BY cliente
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
    <title>Mapa de Pontos · SGI</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
    <link rel="stylesheet" href="/public/assets/css/mapa.css">
    <!-- Google Maps via API key (sem Leaflet) -->
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

                <select class="mapa-select" id="mapaFiltroCliente">
                    <option value="">Todos os clientes</option>
                    <?php foreach ($clientes as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
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

<script>
var PONTOS  = <?= $pontosJson ?>;
var FILTROS_KEY = 'impakto_mapa_filtros_v1';
var filtros = { busca: '', situacao: '', cidade: '', tipo: '', cliente: '' };
function salvarFiltros() {
    try { sessionStorage.setItem(FILTROS_KEY, JSON.stringify(filtros)); } catch(e) {}
}
// ── Restaurar filtros ao voltar para esta página (sessionStorage) ──
(function restaurarFiltros() {
    var salvo;
    try { salvo = JSON.parse(sessionStorage.getItem(FILTROS_KEY) || 'null'); } catch(e) { salvo = null; }
    if (!salvo) return;
    filtros = Object.assign({ busca:'', situacao:'', cidade:'', tipo:'', cliente:'' }, salvo);
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('mapaBusca').value = filtros.busca;
        var mapaIds = { situacao:'mapaFiltroSit', cidade:'mapaFiltroCidade', tipo:'mapaFiltroTipo', cliente:'mapaFiltroCliente' };
        Object.keys(mapaIds).forEach(function(key) {
            var el = document.getElementById(mapaIds[key]);
            el.value = filtros[key];
            el.className = 'mapa-select' + (filtros[key] ? ' ativo' : '');
        });
    });
})();
var _visiveis  = [];
var _markers   = []; // google.maps.Marker[]
var map, infoWindow;

// ── Cores por situação ─────────────────────────────────────────
var CORES = {
    'Disponivel':'#1a9059','Disponível':'#1a9059',
    'Ocupado':'#dc3545','Reservado':'#fd7e14',
    'Vencido':'#6c757d','Permuta':'#51086e','Bisemana':'#0284c7'
};
function corSit(sit) { return CORES[sit] || '#888888'; }

// ── Helpers ────────────────────────────────────────────────────
function esc(str) {
    return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function normalizar(str) {
    return (str||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'');
}

// ── SVG de ícone circular ──────────────────────────────────────
function criarIcone(sit, destaque) {
    var cor  = corSit(sit);
    var size = destaque ? 20 : 14;
    return {
        url: 'data:image/svg+xml,' + encodeURIComponent(
            '<svg xmlns="http://www.w3.org/2000/svg" width="'+size+'" height="'+size+'" viewBox="0 0 '+size+' '+size+'">' +
            '<circle cx="'+(size/2)+'" cy="'+(size/2)+'" r="'+(size/2-2)+'" fill="'+cor+'" stroke="white" stroke-width="2.5"/>' +
            '</svg>'
        ),
        scaledSize: { width: size, height: size },
        anchor: { x: size/2, y: size/2 }
    };
}

// ── HTML do InfoWindow ─────────────────────────────────────────
function popupHtml(p) {
    var cor = corSit(p.situacao);
    var html = '<div style="font-family:Montserrat,sans-serif;min-width:200px;max-width:240px;padding:2px">';
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
    html += '</div></div>';
    return html;
}

// ── Inicializar mapa ───────────────────────────────────────────
function initMap() {
    map = new google.maps.Map(document.getElementById('map'), {
        center: { lat: <?= round($centroLat, 6) ?>, lng: <?= round($centroLng, 6) ?> },
        zoom: PONTOS.length === 1 ? 16 : 12,
        mapTypeId: 'roadmap',
        mapTypeControl: true,
        mapTypeControlOptions: {
            style: google.maps.MapTypeControlStyle.HORIZONTAL_BAR,
            position: google.maps.ControlPosition.TOP_RIGHT,
            mapTypeIds: ['roadmap', 'hybrid', 'satellite']
        },
        streetViewControl: false,
        fullscreenControl: true,
        zoomControl: true,
        gestureHandling: 'greedy'
    });

    infoWindow = new google.maps.InfoWindow({ maxWidth: 260 });

    // Pré-criar todos os markers (ocultos)
    PONTOS.forEach(function(p, i) {
        var lat = parseFloat(p.latitude);
        var lng = parseFloat(p.longitude);
        if (isNaN(lat) || isNaN(lng)) { _markers.push(null); return; }

        var marker = new google.maps.Marker({
            position: { lat: lat, lng: lng },
            map: null,            // oculto por padrão
            title: 'Ponto #' + p.numero,
            icon: criarIcone(p.situacao, false)
        });
        marker._pontoIdx = i;
        marker.addListener('click', function() {
            infoWindow.setContent(popupHtml(p));
            infoWindow.open(map, marker);
            destacarLista(i);
        });
        _markers.push(marker);
    });

    renderizar();
    ajustarAltura();
}

// ── Renderizar (filtro + mostrar/ocultar markers) ──────────────
function renderizar() {
    // Fecha infoWindow aberto
    if (infoWindow) infoWindow.close();

    _visiveis = [];
    var busca    = normalizar(filtros.busca);
    var temFiltro = filtros.busca || filtros.situacao || filtros.cidade || filtros.tipo || filtros.cliente;

    PONTOS.forEach(function(p, i) {
        var marker = _markers[i];
        if (!marker) return;

        var visivel = true;
        if (filtros.situacao && p.situacao !== filtros.situacao) visivel = false;
        if (filtros.cidade   && p.cidade   !== filtros.cidade)   visivel = false;
        if (filtros.tipo     && p.tipo     !== filtros.tipo)     visivel = false;
        if (filtros.cliente  && (p.cliente||'').trim() !== filtros.cliente) visivel = false;
        if (busca) {
            var campos = [p.numero, p.logradouro, p.bairro, p.cidade, p.regiao];
            if (!campos.some(function(c) { return normalizar(c).indexOf(busca) !== -1; })) visivel = false;
        }

        marker.setMap(visivel ? map : null);
        if (visivel) _visiveis.push({ p: p, idx: i, marker: marker });
    });

    document.getElementById('mapaContador').textContent = _visiveis.length;
    document.getElementById('mapaBtnLimpar').className = 'mapa-btn-limpar' + (temFiltro ? ' visivel' : '');
    renderLista();

    // Ajustar bounds
    if (_visiveis.length > 1) {
        var bounds = new google.maps.LatLngBounds();
        _visiveis.forEach(function(v) {
            bounds.extend({ lat: parseFloat(v.p.latitude), lng: parseFloat(v.p.longitude) });
        });
        map.fitBounds(bounds);
        // limitar zoom máximo
        var listener = google.maps.event.addListenerOnce(map, 'bounds_changed', function() {
            if (map.getZoom() > 14) map.setZoom(14);
        });
    } else if (_visiveis.length === 1) {
        map.panTo({ lat: parseFloat(_visiveis[0].p.latitude), lng: parseFloat(_visiveis[0].p.longitude) });
        map.setZoom(16);
    }
}

// ── Lista lateral ──────────────────────────────────────────────
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

function destacarLista(idx) {
    document.querySelectorAll('.mapa-item').forEach(function(el) { el.classList.remove('ativo'); });
    var el = document.querySelector('.mapa-item[data-idx="'+idx+'"]');
    if (el) { el.classList.add('ativo'); el.scrollIntoView({ block:'nearest', behavior:'smooth' }); }
}

// ── Focar ponto ao clicar na lista ────────────────────────────
function focarPonto(idx) {
    var item = _visiveis.find(function(v) { return v.idx === idx; });
    if (!item) return;
    destacarLista(idx);
    map.panTo({ lat: parseFloat(item.p.latitude), lng: parseFloat(item.p.longitude) });
    map.setZoom(17);
    infoWindow.setContent(popupHtml(item.p));
    infoWindow.open(map, item.marker);
}

// ── Filtros ────────────────────────────────────────────────────
var debTimer;
document.getElementById('mapaBusca').addEventListener('input', function() {
    clearTimeout(debTimer);
    var val = this.value;
    debTimer = setTimeout(function() { filtros.busca = val; salvarFiltros(); renderizar(); }, 150);
});
['mapaFiltroSit','mapaFiltroCidade','mapaFiltroTipo','mapaFiltroCliente'].forEach(function(id) {
    document.getElementById(id).addEventListener('change', function() {
        var key = { mapaFiltroSit:'situacao', mapaFiltroCidade:'cidade', mapaFiltroTipo:'tipo', mapaFiltroCliente:'cliente' }[id];
        filtros[key] = this.value;
        this.className = 'mapa-select' + (this.value ? ' ativo' : '');
        salvarFiltros();
        renderizar();
    });
});

function limparFiltros() {
    filtros = { busca:'', situacao:'', cidade:'', tipo:'', cliente:'' };
    document.getElementById('mapaBusca').value = '';
    ['mapaFiltroSit','mapaFiltroCidade','mapaFiltroTipo','mapaFiltroCliente'].forEach(function(id) {
        document.getElementById(id).value = '';
        document.getElementById(id).className = 'mapa-select';
    });
    salvarFiltros();
    renderizar();
}

// ── Altura do layout ───────────────────────────────────────────
function ajustarAltura() {
    var header = document.querySelector('.header');
    var h = window.innerHeight - (header ? header.offsetHeight : 60);
    document.getElementById('mapaLayout').style.height = h + 'px';
}
window.addEventListener('resize', ajustarAltura);
</script>
<script
    src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars(getenv('GOOGLE_MAPS_KEY')) ?>&callback=initMap&language=pt-BR&region=BR"
    async defer>
</script>

</body>
</html>
