<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

$paginaAtual = 'pontos';

try {
    require_once __DIR__ . '/../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("Erro na conexão com o banco de dados.");
}

$id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$modo  = $id > 0 ? 'editar' : 'novo';
$ponto = [];
$fotos = [];

if ($modo === 'editar') {
    $stmt = $pdo->prepare("SELECT * FROM pontos WHERE id = ?");
    $stmt->execute([$id]);
    $ponto = $stmt->fetch();
    if (!$ponto) {
        header("Location: /gestor/pontos");
        exit;
    }
    $pontoAtivo = (int)($ponto['ativo'] ?? 1) !== 0;
    $stmtF = $pdo->prepare("SELECT * FROM ponto_fotos WHERE ponto_id = ? ORDER BY ordem ASC, id ASC");
    $stmtF->execute([$id]);
    $fotos = $stmtF->fetchAll();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

$msg     = isset($_GET['msg']) ? $_GET['msg'] : '';
$abaGet  = ($modo === 'editar' && isset($_GET['aba'])) ? $_GET['aba'] : 'dados';
$mapsKey = getenv('GOOGLE_MAPS_KEY') ?: '';

function v($arr, $campo, $default = '') {
    return isset($arr[$campo]) ? htmlspecialchars($arr[$campo], ENT_QUOTES) : $default;
}

$situacoes = ['Disponivel','Ocupado','Reservado','Vencido','Permuta','Bisemana'];
$tipos     = ['Painel','Outdoor','Frontlight','Painel LED'];

// Autocomplete: clientes e agências de campanhas + cliente_exclusivo dos pontos
$listaClientes = array_unique(array_merge(
    $pdo->query("SELECT DISTINCT cliente FROM campanhas WHERE cliente IS NOT NULL AND cliente != '' AND ativo=1 ORDER BY cliente")->fetchAll(PDO::FETCH_COLUMN),
    $pdo->query("SELECT DISTINCT cliente_exclusivo FROM pontos WHERE cliente_exclusivo IS NOT NULL AND cliente_exclusivo != '' AND (ativo=1 OR ativo IS NULL) ORDER BY cliente_exclusivo")->fetchAll(PDO::FETCH_COLUMN)
));
sort($listaClientes);

$listaAgencias = $pdo->query("SELECT DISTINCT agencia FROM campanhas WHERE agencia IS NOT NULL AND agencia != '' AND ativo=1 ORDER BY agencia")->fetchAll(PDO::FETCH_COLUMN);
sort($listaAgencias);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $modo === 'editar' ? 'Editar Ponto #' . htmlspecialchars($ponto['numero']) : 'Novo Ponto' ?> · Impakto</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css?v=2">
    <link rel="stylesheet" href="/public/assets/css/form_ponto.css">
    <style>
        .coord-pair { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
        .coord-whatsapp { display:flex; gap:0.5rem; margin-top:0.75rem; }
        .coord-whatsapp .form-input { flex:1; font-size:0.82rem; }
        .coord-search { display:flex; gap:0.5rem; }
        .coord-search .form-input { flex:1; }
        .btn-coord-apply {
            padding:0 1rem; background:var(--color-accent-primary); color:#fff;
            border:none; border-radius:6px; cursor:pointer; font-size:0.82rem;
            font-weight:600; white-space:nowrap;
        }
        .btn-coord-apply:hover { filter:brightness(1.1); }
        .btn-map-toggle {
            background:none; border:1px solid var(--color-accent-primary);
            color:var(--color-accent-primary); padding:0.3rem 0.9rem;
            border-radius:6px; cursor:pointer; font-size:0.8rem; font-weight:600;
        }
        .btn-map-toggle:hover { background:var(--color-accent-primary); color:#fff; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="container">
<div class="form-page">

    <!-- ── Cabeçalho ───────────────────────────────────────── -->
    <div class="form-header">
        <a href="/gestor/pontos" class="btn-voltar">← Voltar</a>
        <h2 class="form-title">
            <?php if ($modo === 'editar'): ?>
                Editar Ponto <span style="color:var(--color-accent-primary)">#<?= htmlspecialchars($ponto['numero']) ?></span>
            <?php else: ?>
                Novo Ponto
            <?php endif; ?>
        </h2>
        <?php if ($modo === 'editar'): ?>
        <?php if ($pontoAtivo): ?>
        <form method="post" action="/gestor/pontos/desativar"
              onsubmit="return confirm('Desativar o ponto #<?= htmlspecialchars($ponto['numero'], ENT_QUOTES) ?>? O ponto ficará inativo mas poderá ser reativado depois.')">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="id" value="<?= (int)$ponto['id'] ?>">
            <button type="submit" class="btn-excluir-ponto" style="background:#f59e0b;border-color:#d97706">⏸ Desativar ponto</button>
        </form>
        <?php else: ?>
        <form method="post" action="/gestor/pontos/reativar"
              onsubmit="return confirm('Reativar o ponto #<?= htmlspecialchars($ponto['numero'], ENT_QUOTES) ?>?')">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="id" value="<?= (int)$ponto['id'] ?>">
            <button type="submit" class="btn-excluir-ponto" style="background:#16a34a;border-color:#15803d">✅ Reativar ponto</button>
        </form>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- ── Aviso ponto inativo ──────────────────────────────── -->
    <?php if ($modo === 'editar' && !$pontoAtivo): ?>
        <div class="form-msg" style="background:#fef3c7;border:1px solid #f59e0b;color:#92400e;border-radius:8px;padding:0.75rem 1rem;margin-bottom:1rem">
            ⚠️ Este ponto está <strong>inativo</strong>. Use o botão "Reativar ponto" para ativá-lo novamente.
        </div>
    <?php endif; ?>

    <!-- ── Mensagens ────────────────────────────────────────── -->
    <?php if ($msg === 'salvo'): ?>
        <div class="form-msg ok">✅ Dados salvos com sucesso.</div>
    <?php elseif ($msg === 'criado'): ?>
        <div class="form-msg ok">✅ Ponto criado! Agora adicione as fotos abaixo.</div>
    <?php elseif ($msg === 'reativado'): ?>
        <div class="form-msg ok">✅ Ponto reativado com sucesso!</div>
    <?php elseif ($msg === 'erro'): ?>
        <div class="form-msg erro">⚠️ Verifique os campos obrigatórios (Número, Logradouro, Cidade).</div>
    <?php elseif ($msg === 'erro_situacao_sem_campanha'): ?>
        <div class="form-msg erro">⚠️ Não é possível marcar "Ocupado" sem uma campanha ativa. Use "Registrar campanha" para ocupar este ponto.</div>
    <?php endif; ?>

    <!-- ── Abas (só em modo editar) ─────────────────────────── -->
    <?php if ($modo === 'editar'): ?>
    <div class="form-tabs">
        <button class="form-tab" data-tab="dados" onclick="trocarAba('dados')">📋 Dados</button>
        <button class="form-tab" data-tab="fotos" onclick="trocarAba('fotos')">📷 Fotos (<?= count($fotos) ?>)</button>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════
         ABA DADOS
    ══════════════════════════════════════════════════════════ -->
    <div class="form-tab-content" id="tab-dados">
        <form method="post" action="/gestor/pontos/salvar">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <?php if ($modo === 'editar'): ?>
            <input type="hidden" name="id" value="<?= (int)$ponto['id'] ?>">
            <?php endif; ?>

            <!-- ── Identificação ── -->
            <p class="form-section-title">Identificação</p>
            <div class="form-grid">

                <div class="form-group">
                    <label class="form-label">Número <span class="req">*</span></label>
                    <input type="number" name="numero" class="form-input"
                           value="<?= v($ponto,'numero') ?>" required min="1" placeholder="001">
                </div>

                <div class="form-group">
                    <label class="form-label">Situação</label>
                    <select name="situacao" class="form-select">
                        <option value="">— Selecione —</option>
                        <?php foreach ($situacoes as $sit): ?>
                        <option value="<?= $sit ?>" <?= v($ponto,'situacao') === $sit ? 'selected' : '' ?>><?= $sit ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <!-- ── Localização ── -->
            <p class="form-section-title">Localização</p>
            <div class="form-grid">

                <div class="form-group span2">
                    <label class="form-label">Logradouro <span class="req">*</span></label>
                    <input type="text" name="logradouro" class="form-input"
                           value="<?= v($ponto,'logradouro') ?>" required maxlength="50"
                           placeholder="Av. Paulista, 1000">
                </div>

                <div class="form-group span2">
                    <label class="form-label">Descrição / Referência</label>
                    <textarea name="descricao" class="form-textarea"
                              placeholder="Próximo ao metrô, esquina com Rua X..."><?= v($ponto,'descricao') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Bairro</label>
                    <input type="text" name="bairro" class="form-input"
                           value="<?= v($ponto,'bairro') ?>" maxlength="20">
                </div>

                <div class="form-group">
                    <label class="form-label">Cidade <span class="req">*</span></label>
                    <input type="text" name="cidade" class="form-input"
                           value="<?= v($ponto,'cidade') ?>" required maxlength="100">
                </div>

                <div class="form-group">
                    <label class="form-label">Região</label>
                    <input type="text" name="regiao" class="form-input"
                           value="<?= v($ponto,'regiao') ?>" maxlength="100" placeholder="Zona Norte, Centro...">
                </div>

                <div class="form-group">
                    <label class="form-label">Sentido</label>
                    <input type="text" name="sentido" class="form-input"
                           value="<?= v($ponto,'sentido') ?>" maxlength="100" placeholder="Sentido bairro / centro">
                </div>

                <div class="form-group">
                    <label class="form-label">Corredor</label>
                    <input type="text" name="corredor" class="form-input"
                           value="<?= v($ponto,'corredor') ?>" maxlength="100">
                </div>

                <div class="form-group span2">
                    <div class="coord-pair">
                        <div class="form-group">
                            <label class="form-label">Latitude</label>
                            <input type="text" name="latitude" id="inputLat" class="form-input"
                                   value="<?= v($ponto,'latitude') ?>" placeholder="-23.55052"
                                   inputmode="decimal" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Longitude</label>
                            <input type="text" name="longitude" id="inputLng" class="form-input"
                                   value="<?= v($ponto,'longitude') ?>" placeholder="-46.63331"
                                   inputmode="decimal" autocomplete="off">
                        </div>
                    </div>

                    <!-- Colar do WhatsApp -->
                    <div class="coord-whatsapp">
                        <input type="text" id="coordPaste" class="form-input"
                               placeholder="📋 Colar do WhatsApp: -23.550520, -46.633310"
                               autocomplete="off">
                        <button type="button" class="btn-coord-apply" onclick="aplicarCoordColadas()">Aplicar</button>
                    </div>

                    <?php if ($mapsKey): ?>
                    <div style="margin-top:0.6rem;">
                        <button type="button" class="btn-map-toggle" id="btnMapToggle" onclick="toggleMapa()">
                            📍 Ajustar no mapa
                        </button>
                    </div>
                    <div id="mapPickerWrap" style="display:none;margin-top:0.75rem;">
                        <div class="coord-search" style="margin-bottom:0.5rem;">
                            <input type="text" id="mapSearch" class="form-input"
                                   placeholder="Buscar endereço no mapa..."
                                   autocomplete="off"
                                   onkeydown="if(event.key==='Enter'){event.preventDefault();buscarNoMapa();}">
                            <button type="button" class="btn-coord-apply" onclick="buscarNoMapa()">Buscar</button>
                        </div>
                        <div id="mapPicker" style="height:340px;border-radius:8px;border:1px solid var(--color-border);"></div>
                        <p style="font-size:0.73rem;color:var(--color-text-muted);margin-top:0.3rem;">
                            Clique no mapa ou arraste o marcador para definir a posição exata.
                        </p>
                    </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- ── Características ── -->
            <p class="form-section-title">Características</p>
            <div class="form-grid">

                <div class="form-group">
                    <label class="form-label">Tipo</label>
                    <select name="tipo" class="form-select">
                        <option value="">— Selecione —</option>
                        <?php foreach ($tipos as $t): ?>
                        <option value="<?= $t ?>" <?= v($ponto,'tipo') === $t ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Formato</label>
                    <input type="text" name="formato" class="form-input"
                           value="<?= v($ponto,'formato') ?>" maxlength="50" placeholder="9×3m, 6×3m...">
                </div>

            </div>

            <!-- ── Exclusividade ── -->
            <p class="form-section-title">Exclusividade</p>
            <div class="form-grid">

                <div class="form-group">
                    <label class="form-label" style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                        <input type="checkbox" id="chkExclusivo" name="exclusivo" value="1"
                               <?= v($ponto,'exclusivo') === '1' ? 'checked' : '' ?>
                               onchange="document.getElementById('clienteExclusivoWrap').style.display = this.checked ? '' : 'none';">
                        Painel exclusivo (não comercializável para outros clientes)
                    </label>
                </div>

                <div class="form-group" id="clienteExclusivoWrap" style="<?= v($ponto,'exclusivo') === '1' ? '' : 'display:none;' ?>">
                    <label class="form-label">Cliente exclusivo</label>
                    <input type="text" name="cliente_exclusivo" class="form-input" list="listaClientesExclusivo"
                           value="<?= v($ponto,'cliente_exclusivo') ?>" placeholder="Nome do cliente dono da exclusividade">
                    <datalist id="listaClientesExclusivo">
                        <?php foreach ($listaClientes as $c): ?>
                        <option value="<?= htmlspecialchars($c, ENT_QUOTES) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="form-group">
                    <label class="form-label" style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                        <input type="checkbox" name="liberado_comercializacao" value="1"
                               <?= v($ponto,'liberado_comercializacao') === '1' ? 'checked' : '' ?>>
                        Liberado para comercialização (mesmo sendo exclusivo)
                    </label>
                </div>

            </div>

            <!-- Nota: cliente, agência e contrato são gerenciados via Campanhas -->
            <?php if ($modo === 'editar' && !empty($ponto['id'])): ?>
            <div class="form-note-camp">
                📢 Cliente e contrato são gerenciados na aba
                <a href="/gestor/pontos/detalhes?id=<?= (int)$ponto['id'] ?>" style="color:var(--color-accent-primary);font-weight:700">Detalhes do Ponto → Campanha</a>
            </div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn-salvar">💾 Salvar dados</button>
                <a href="/gestor/pontos" class="btn-cancelar">Cancelar</a>
            </div>
        </form>
    </div>

    <!-- ══════════════════════════════════════════════════════
         ABA FOTOS (só em modo editar)
    ══════════════════════════════════════════════════════════ -->
    <?php if ($modo === 'editar'): ?>
    <div class="form-tab-content" id="tab-fotos">

        <?php if (empty($fotos)): ?>
        <p class="foto-aviso" id="fotoAviso">Nenhuma foto cadastrada. Faça upload abaixo.</p>
        <?php endif; ?>

        <div class="foto-grid" id="fotoGrid">
            <?php foreach ($fotos as $f): ?>
            <div class="foto-card <?= $f['principal'] ? 'principal-card' : '' ?>"
                 id="fc-<?= (int)$f['id'] ?>" data-id="<?= (int)$f['id'] ?>">
                <?php if ($f['principal']): ?>
                <span class="foto-capa-badge">★ Capa</span>
                <?php endif; ?>
                <img src="/<?= htmlspecialchars($f['caminho']) ?>" alt="Foto do ponto"
                     data-src="/<?= htmlspecialchars($f['caminho']) ?>"
                     onclick="abrirLb(this.dataset.src)"
                     onerror="this.style.display='none'">
                <div class="foto-card-actions">
                    <button class="btn-capa <?= $f['principal'] ? 'ativo' : '' ?>"
                            onclick="definirCapa(<?= (int)$f['id'] ?>)">
                        <?= $f['principal'] ? '★ Capa' : '☆ Capa' ?>
                    </button>
                    <button class="btn-del-foto" onclick="excluirFoto(<?= (int)$f['id'] ?>)">🗑</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Área de upload -->
        <div class="upload-area" id="uploadArea">
            <span class="upload-icon">📁</span>
            <p><strong>Clique ou arraste fotos aqui</strong></p>
            <p style="font-size:0.75rem;">JPG, PNG, WEBP — máx. 8 MB por arquivo</p>
            <button class="btn-upload" type="button"
                    onclick="document.getElementById('uploadInput').click()">Selecionar fotos</button>
            <input type="file" id="uploadInput" multiple
                   accept="image/jpeg,image/png,image/webp"
                   onchange="subirFotos(this.files)">
        </div>

        <div class="upload-progress" id="uploadProgress" style="display:none">
            <div class="progress-bar"><div class="progress-fill" id="progressFill"></div></div>
            <p class="progress-msg" id="progressMsg"></p>
        </div>

    </div>
    <?php endif; ?>

</div><!-- /form-page -->
</div><!-- /container -->

<!-- Lightbox -->
<div class="lb-overlay" id="lbOverlay" onclick="fecharLb()">
    <img class="lb-img" id="lbImg" src="" alt="">
</div>

<script>
var PONTO_ID   = <?= (int)$id ?>;
var CSRF_TOKEN = '<?= $csrfToken ?>';

// ── Abas ─────────────────────────────────────────────────────
function trocarAba(aba) {
    document.querySelectorAll('.form-tab').forEach(function(t) {
        t.classList.toggle('ativo', t.dataset.tab === aba);
    });
    document.querySelectorAll('.form-tab-content').forEach(function(c) {
        c.classList.toggle('ativo', c.id === 'tab-' + aba);
    });
}
<?php if ($modo === 'editar'): ?>
trocarAba('<?= htmlspecialchars($abaGet, ENT_QUOTES) ?>');
<?php else: ?>
document.getElementById('tab-dados').classList.add('ativo');
<?php endif; ?>

// ── Coordenadas: colar do WhatsApp ────────────────────────────
function aplicarCoordColadas() {
    var raw   = document.getElementById('coordPaste').value.trim();
    var match = raw.match(/^(-?\d+[.,]\d+)[,\s]+(-?\d+[.,]\d+)$/);
    if (!match) {
        alert('Formato inválido.\nUse: -23.550520, -46.633310');
        return;
    }
    var lat = parseFloat(match[1].replace(',', '.'));
    var lng = parseFloat(match[2].replace(',', '.'));
    document.getElementById('inputLat').value = lat.toFixed(8);
    document.getElementById('inputLng').value = lng.toFixed(8);
    document.getElementById('coordPaste').value = '';
    if (mapInitialized) {
        atualizarMarcador(lat, lng);
        mapInstance.setZoom(17);
    }
}

<?php if ($mapsKey): ?>
// ── Mapa ───────────────────────────────────────────────────────
var mapInstance    = null;
var mapMarker      = null;
var mapInitialized = false;

function toggleMapa() {
    var wrap = document.getElementById('mapPickerWrap');
    var btn  = document.getElementById('btnMapToggle');
    if (wrap.style.display === 'none') {
        wrap.style.display = 'block';
        btn.textContent = '🗺 Fechar mapa';
        if (!mapInitialized) inicializarMapa();
    } else {
        wrap.style.display = 'none';
        btn.textContent = '📍 Ajustar no mapa';
    }
}

function inicializarMapa() {
    mapInitialized = true;
    var latVal = document.getElementById('inputLat').value.trim();
    var lngVal = document.getElementById('inputLng').value.trim();
    var lat = latVal ? parseFloat(latVal.replace(',', '.')) : -15.793889;
    var lng = lngVal ? parseFloat(lngVal.replace(',', '.')) : -47.882778;
    var pos  = { lat: lat, lng: lng };
    var zoom = latVal ? 17 : 5;

    mapInstance = new google.maps.Map(document.getElementById('mapPicker'), {
        center: pos, zoom: zoom,
        mapTypeControl: false, streetViewControl: false, fullscreenControl: false,
    });
    mapMarker = new google.maps.Marker({
        position: pos, map: mapInstance, draggable: true,
        animation: google.maps.Animation.DROP,
    });
    mapInstance.addListener('click', function(e) {
        atualizarMarcador(e.latLng.lat(), e.latLng.lng());
    });
    mapMarker.addListener('dragend', function(e) {
        atualizarMarcador(e.latLng.lat(), e.latLng.lng());
    });
}

function atualizarMarcador(lat, lng) {
    var pos = { lat: lat, lng: lng };
    mapMarker.setPosition(pos);
    mapInstance.panTo(pos);
    document.getElementById('inputLat').value = lat.toFixed(8);
    document.getElementById('inputLng').value = lng.toFixed(8);
}

function buscarNoMapa() {
    var query = document.getElementById('mapSearch').value.trim();
    if (!query) return;
    var geocoder = new google.maps.Geocoder();
    geocoder.geocode({ address: query, region: 'BR' }, function(results, status) {
        if (status === 'OK' && results[0]) {
            var loc = results[0].geometry.location;
            if (!mapInitialized) inicializarMapa();
            mapInstance.setCenter(loc);
            mapInstance.setZoom(17);
            atualizarMarcador(loc.lat(), loc.lng());
        } else {
            alert('Endereço não encontrado. Tente ser mais específico.');
        }
    });
}
<?php endif; ?>

// ── Lightbox ──────────────────────────────────────────────────
function abrirLb(src) {
    document.getElementById('lbImg').src = src;
    document.getElementById('lbOverlay').classList.add('aberto');
}
function fecharLb() {
    document.getElementById('lbOverlay').classList.remove('aberto');
    document.getElementById('lbImg').src = '';
}
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') fecharLb(); });

<?php if ($modo === 'editar'): ?>
// ── Drag & drop ────────────────────────────────────────────────
var uploadArea = document.getElementById('uploadArea');
uploadArea.addEventListener('click', function(e) {
    if (e.target === uploadArea || e.target.tagName === 'P' || e.target.tagName === 'SPAN') {
        document.getElementById('uploadInput').click();
    }
});
uploadArea.addEventListener('dragover',  function(e) { e.preventDefault(); this.classList.add('drag-over'); });
uploadArea.addEventListener('dragleave', function()  { this.classList.remove('drag-over'); });
uploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    this.classList.remove('drag-over');
    subirFotos(e.dataTransfer.files);
});

// ── Upload ─────────────────────────────────────────────────────
function subirFotos(files) {
    if (!files || !files.length) return;
    var progress = document.getElementById('uploadProgress');
    var fill     = document.getElementById('progressFill');
    var msg      = document.getElementById('progressMsg');
    progress.style.display = 'block';
    fill.style.width = '0%';

    var total = files.length, done = 0, ok = 0;

    Array.from(files).forEach(function(file) {
        if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
            done++;
            fill.style.width = Math.round(done / total * 100) + '%';
            msg.textContent  = 'Ignorado: ' + file.name + ' (formato inválido)';
            return;
        }
        var fd = new FormData();
        fd.append('action',     'upload');
        fd.append('ponto_id',   PONTO_ID);
        fd.append('csrf_token', CSRF_TOKEN);
        fd.append('foto',       file);

        fetch('/gestor/pontos/fotos', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                done++;
                fill.style.width = Math.round(done / total * 100) + '%';
                if (d.ok) {
                    ok++;
                    adicionarFotoCard(d.foto);
                    msg.textContent = ok + ' foto(s) enviada(s)...';
                } else {
                    msg.textContent = 'Erro: ' + (d.erro || 'Falha no upload');
                }
                if (done === total) {
                    msg.textContent = ok + ' foto(s) adicionada(s) com sucesso!';
                    atualizarContadorAba();
                    document.getElementById('uploadInput').value = '';
                    setTimeout(function() {
                        progress.style.display = 'none';
                        fill.style.width = '0%';
                    }, 3000);
                }
            })
            .catch(function() {
                done++;
                msg.textContent = 'Erro de conexão ao enviar ' + file.name;
            });
    });
}

function adicionarFotoCard(foto) {
    var aviso = document.getElementById('fotoAviso');
    if (aviso) aviso.remove();

    var grid = document.getElementById('fotoGrid');
    var div  = document.createElement('div');
    div.className = 'foto-card' + (foto.principal ? ' principal-card' : '');
    div.id        = 'fc-' + foto.id;
    div.dataset.id = foto.id;

    var img = document.createElement('img');
    img.src          = '/' + foto.caminho;
    img.alt          = 'Foto do ponto';
    img.dataset.src  = '/' + foto.caminho;
    img.style.cssText = 'width:100%;height:120px;object-fit:cover;display:block;cursor:zoom-in';
    img.onclick       = function() { abrirLb(this.dataset.src); };

    var actions = document.createElement('div');
    actions.className = 'foto-card-actions';

    var btnCapa = document.createElement('button');
    btnCapa.className = 'btn-capa' + (foto.principal ? ' ativo' : '');
    btnCapa.textContent = foto.principal ? '★ Capa' : '☆ Capa';
    btnCapa.onclick = (function(id) { return function() { definirCapa(id); }; })(foto.id);

    var btnDel = document.createElement('button');
    btnDel.className = 'btn-del-foto';
    btnDel.textContent = '🗑';
    btnDel.onclick = (function(id) { return function() { excluirFoto(id); }; })(foto.id);

    actions.appendChild(btnCapa);
    actions.appendChild(btnDel);

    if (foto.principal) {
        var badge = document.createElement('span');
        badge.className   = 'foto-capa-badge';
        badge.textContent = '★ Capa';
        div.appendChild(badge);
    }
    div.appendChild(img);
    div.appendChild(actions);
    grid.appendChild(div);
}

// ── Definir foto principal ─────────────────────────────────────
function definirCapa(fotoId) {
    fetch('/gestor/pontos/fotos', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    'action=principal&id=' + fotoId + '&ponto_id=' + PONTO_ID + '&csrf_token=' + CSRF_TOKEN
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (!d.ok) { alert(d.erro || 'Erro ao definir capa'); return; }
        document.querySelectorAll('.foto-card').forEach(function(c) {
            c.classList.remove('principal-card');
            var badge = c.querySelector('.foto-capa-badge');
            if (badge) badge.remove();
            var btn = c.querySelector('.btn-capa');
            if (btn) { btn.classList.remove('ativo'); btn.textContent = '☆ Capa'; }
        });
        var card = document.getElementById('fc-' + fotoId);
        if (!card) return;
        card.classList.add('principal-card');
        var btn = card.querySelector('.btn-capa');
        if (btn) { btn.classList.add('ativo'); btn.textContent = '★ Capa'; }
        var img = card.querySelector('img');
        if (img) {
            var badge = document.createElement('span');
            badge.className   = 'foto-capa-badge';
            badge.textContent = '★ Capa';
            card.insertBefore(badge, img);
        }
    });
}

// ── Excluir foto ───────────────────────────────────────────────
function excluirFoto(fotoId) {
    if (!confirm('Excluir esta foto permanentemente?')) return;
    fetch('/gestor/pontos/fotos', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    'action=excluir&id=' + fotoId + '&csrf_token=' + CSRF_TOKEN
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (!d.ok) { alert(d.erro || 'Erro ao excluir'); return; }
        var card = document.getElementById('fc-' + fotoId);
        if (card) card.remove();
        atualizarContadorAba();
        if (document.querySelectorAll('.foto-card').length === 0) {
            var aviso = document.createElement('p');
            aviso.id        = 'fotoAviso';
            aviso.className = 'foto-aviso';
            aviso.textContent = 'Nenhuma foto cadastrada. Faça upload abaixo.';
            document.getElementById('fotoGrid').before(aviso);
        }
    });
}

function atualizarContadorAba() {
    var n   = document.querySelectorAll('.foto-card').length;
    var btn = document.querySelector('.form-tab[data-tab="fotos"]');
    if (btn) btn.textContent = '📷 Fotos (' + n + ')';
}
<?php endif; ?>
</script>

<?php if ($mapsKey): ?>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($mapsKey) ?>&language=pt-BR"></script>
<?php endif; ?>

</body>
</html>
