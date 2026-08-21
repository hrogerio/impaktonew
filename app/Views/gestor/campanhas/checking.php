<?php
// ==VERSAO-2025-05-27==
ini_set('display_errors', 0);
ini_set('log_errors', 1);
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

require_once __DIR__ . '/../../../../config/database.php';
$pdo = getDatabase();

$cliente     = trim($_GET['cliente']  ?? '');
$agencia     = trim($_GET['agencia']  ?? '');
$campanha    = trim($_GET['campanha'] ?? '');
$nomeProjeto = trim($_GET['nome_projeto'] ?? '');
$situacao    = trim($_GET['situacao'] ?? 'Ocupado');
$inicio   = trim($_GET['inicio']   ?? '') ?: null;
$fim      = trim($_GET['fim']      ?? '') ?: null;
$pontoIds = array_values(array_filter(array_map('intval', (array)($_GET['pontoIds'] ?? [])), fn($id) => $id > 0));

if (empty($pontoIds)) {
    http_response_code(400);
    die('Nenhum painel selecionado.');
}

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

// Busca pontos ordenados por numero
$ph   = implode(',', array_fill(0, count($pontoIds), '?'));
$stmt = $pdo->prepare("SELECT id, numero, logradouro, bairro, cidade, regiao, latitude, longitude FROM pontos WHERE id IN ($ph) ORDER BY numero ASC");
$stmt->execute($pontoIds);
$pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fotos já enviadas
$stmt2 = $pdo->prepare("SELECT id, ponto_id, caminho, legenda, ordem FROM checking_fotos WHERE cliente=? AND campanha=? AND situacao=? AND inicio<=>? AND fim<=>? AND ponto_id IN ($ph) ORDER BY ponto_id ASC, ordem ASC, id ASC");
$stmt2->execute(array_merge([$cliente, $campanha, $situacao, $inicio, $fim], $pontoIds));
$existentes = [];
foreach ($stmt2->fetchAll() as $f) {
    $existentes[$f['ponto_id']][] = $f;
}

// Contagem total de fotos
$totalFotos = array_sum(array_map('count', $existentes));

function fmtD2($d) {
    if (!$d) return '—';
    try { return (new DateTime($d))->format('d/m/Y'); } catch (Exception $e) { return $d; }
}
$periodoFmt = ($inicio || $fim) ? (fmtD2($inicio) . ' → ' . fmtD2($fim)) : '—';

// Query string para repassar ao PDF
$pdfQ = http_build_query([
    'cliente'      => $cliente,
    'agencia'      => $agencia,
    'campanha'     => $campanha,
    'nome_projeto' => $nomeProjeto,
    'situacao'     => $situacao,
    'inicio'       => $inicio ?? '',
    'fim'          => $fim    ?? '',
]);
foreach ($pontoIds as $pid) { $pdfQ .= '&pontoIds[]=' . $pid; }

$paginaAtual = 'campanhas';
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
<link rel="stylesheet" href="/public/assets/css/gestor.css?v=2">
<title>Checking — <?= htmlspecialchars($campanha ?: $cliente) ?></title>
<style>
:root {
    --ck-purple: #7e22ce;
    --ck-purple-light: #f3e8ff;
    --ck-purple-border: #d8b4fe;
}

/* ── Layout ─────────────────────────────────── */
.ck-page { max-width: 980px; margin: 0 auto; padding: 1.5rem 1rem 6rem; }

/* ── Header da campanha ──────────────────────── */
.ck-header {
    background: #fff;
    border: 1.5px solid var(--color-border);
    border-radius: 14px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}
.ck-header-left { display: flex; flex-direction: column; gap: 0.3rem; }
.ck-breadcrumb { font-size: 0.72rem; color: var(--color-text-muted); font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; }
.ck-breadcrumb a { color: var(--color-text-muted); text-decoration: none; }
.ck-breadcrumb a:hover { color: var(--color-accent-primary); }
.ck-titulo { font-size: 1.3rem; font-weight: 800; color: var(--color-text-dark); }
.ck-sub { font-size: 0.82rem; color: var(--color-text-muted); display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 0.2rem; }
.ck-sub b { color: var(--color-text-dark); }
.ck-sit-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: #fde8e8;
    color: #c0392b;
}
.ck-header-right { display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0; }
.ck-btn-pdf {
    display: inline-flex; align-items: center; gap: 0.4rem;
    background: var(--ck-purple); color: #fff;
    border: none; border-radius: 8px;
    padding: 0.55rem 1.1rem;
    font-size: 0.82rem; font-weight: 700;
    cursor: pointer; text-decoration: none;
    transition: background 0.15s, opacity 0.15s;
}
.ck-btn-pdf:hover { background: #6b21a8; }
.ck-btn-pdf.disabled { opacity: 0.45; pointer-events: none; }
.ck-btn-voltar {
    display: inline-flex; align-items: center; gap: 0.35rem;
    background: #f3f4f6; color: var(--color-text-dark);
    border: 1.5px solid var(--color-border); border-radius: 8px;
    padding: 0.55rem 1rem;
    font-size: 0.82rem; font-weight: 600;
    text-decoration: none;
    transition: background 0.15s;
}
.ck-btn-voltar:hover { background: #e5e7eb; }

/* ── Cards dos pontos ────────────────────────── */
.ck-ponto-card {
    background: #fff;
    border: 1.5px solid var(--color-border);
    border-radius: 14px;
    margin-bottom: 1.25rem;
    overflow: hidden;
    transition: box-shadow 0.2s;
}
.ck-ponto-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,0.07); }
.ck-ponto-head {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.9rem 1.25rem;
    background: #fafafa;
    border-bottom: 1px solid var(--color-border);
}
.ck-ponto-num {
    background: var(--color-accent-primary);
    color: #fff;
    font-weight: 800; font-size: 0.85rem;
    padding: 3px 10px; border-radius: 8px;
    flex-shrink: 0;
}
.ck-ponto-info { flex: 1; }
.ck-ponto-log { font-weight: 700; font-size: 0.88rem; color: var(--color-text-dark); }
.ck-ponto-cid { font-size: 0.75rem; color: var(--color-text-muted); margin-top: 1px; }
.ck-ponto-maps {
    font-size: 0.72rem; color: #1d4ed8; text-decoration: none;
    display: flex; align-items: center; gap: 0.25rem;
    padding: 4px 8px; border-radius: 6px; background: #eff6ff;
    border: 1px solid #bfdbfe;
    flex-shrink: 0;
}
.ck-ponto-maps:hover { background: #dbeafe; }
.ck-ponto-body { padding: 1.1rem 1.25rem; }

/* ── Upload zone ─────────────────────────────── */
.ck-upload-zone {
    border: 2px dashed var(--ck-purple-border);
    border-radius: 10px;
    padding: 1.5rem 1rem;
    text-align: center;
    background: var(--ck-purple-light);
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
    margin-bottom: 1rem;
    position: relative;
}
.ck-upload-zone:hover, .ck-upload-zone.drag-over {
    background: #ede9fe;
    border-color: var(--ck-purple);
}
.ck-upload-zone input[type=file] {
    position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
}
.ck-upload-icon { font-size: 1.8rem; margin-bottom: 0.4rem; }
.ck-upload-txt { font-size: 0.82rem; font-weight: 600; color: var(--ck-purple); }
.ck-upload-sub { font-size: 0.72rem; color: var(--color-text-muted); margin-top: 0.2rem; }

/* ── Grid de fotos ───────────────────────────── */
.ck-fotos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 0.75rem;
}
.ck-foto-item {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    background: #f3f4f6;
    border: 1.5px solid var(--color-border);
    display: flex;
    flex-direction: column;
}
.ck-foto-img-wrap {
    position: relative;
    aspect-ratio: 4/3;
    overflow: hidden;
}
.ck-foto-img-wrap img {
    width: 100%; height: 100%; object-fit: cover;
    display: block; transition: transform 0.2s;
}
.ck-foto-item:hover .ck-foto-img-wrap img { transform: scale(1.04); }
.ck-foto-nota {
    width: 100%;
    border: none;
    border-top: 1px solid var(--color-border);
    background: #fffbea;
    font-size: 0.68rem;
    font-family: inherit;
    line-height: 1.3;
    padding: 4px 6px;
    resize: none;
    height: 38px;
    color: var(--color-text-dark);
    outline: none;
}
.ck-foto-nota:focus { background: #fff8d6; }
.ck-foto-nota::placeholder { color: var(--color-text-muted); }
.ck-foto-del {
    position: absolute; top: 5px; right: 5px;
    background: rgba(0,0,0,0.65); color: #fff;
    border: none; border-radius: 50%;
    width: 26px; height: 26px;
    font-size: 0.75rem; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.15s;
}
.ck-foto-del:hover { background: rgba(192,57,43,0.9); }
.ck-foto-uploading {
    display: flex; align-items: center; justify-content: center;
    aspect-ratio: 4/3; background: #f3f4f6;
    border-radius: 8px; border: 1.5px dashed var(--color-border);
    font-size: 0.75rem; color: var(--color-text-muted);
    animation: pulse 1s infinite;
}
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.5} }

/* ── Sem fotos placeholder ───────────────────── */
.ck-sem-foto {
    font-size: 0.78rem; color: var(--color-text-muted);
    text-align: center; padding: 0.5rem 0;
    font-style: italic;
}

/* ── Toast ───────────────────────────────────── */
.ck-toast {
    position: fixed; bottom: 1.5rem; left: 50%; transform: translateX(-50%) translateY(60px);
    background: #1a1a2e; color: #fff;
    padding: 0.7rem 1.4rem; border-radius: 30px;
    font-size: 0.82rem; font-weight: 600;
    box-shadow: 0 4px 18px rgba(0,0,0,0.2);
    transition: transform 0.3s, opacity 0.3s; opacity: 0; z-index: 999;
}
.ck-toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
.ck-toast.ok   { background: #166534; }
.ck-toast.err  { background: #c0392b; }

/* ── Contador geral ──────────────────────────── */
.ck-counter-bar {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.78rem; color: var(--color-text-muted);
    margin-bottom: 1.25rem;
}
.ck-counter-num {
    background: var(--ck-purple); color: #fff;
    padding: 2px 9px; border-radius: 20px;
    font-size: 0.72rem; font-weight: 700;
}
</style>
</head>
<body>

<?php require __DIR__ . '/../../layouts/_nav.php'; ?>

<div class="ck-page">

    <!-- Header da campanha -->
    <div class="ck-header">
        <div class="ck-header-left">
            <div class="ck-breadcrumb">
                <a href="/gestor/campanhas">Campanhas</a> › Checking Fotográfico
            </div>
            <div class="ck-titulo"><?= htmlspecialchars($campanha ?: '—') ?></div>
            <div class="ck-sub">
                <span><?= htmlspecialchars($cliente) ?></span>
                <?php if ($agencia): ?><span>· <?= htmlspecialchars($agencia) ?></span><?php endif; ?>
                <span>· <b><?= $periodoFmt ?></b></span>
                <span>· <?= count($pontos) ?> ponto<?= count($pontos) > 1 ? 's' : '' ?></span>
                <span class="ck-sit-badge"><?= htmlspecialchars($situacao) ?></span>
            </div>
        </div>
        <div class="ck-header-right">
            <a href="/gestor/campanhas" class="ck-btn-voltar">← Voltar</a>
            <a href="/gestor/campanhas/checking/pdf?<?= htmlspecialchars($pdfQ) ?>"
               target="_blank"
               class="ck-btn-pdf <?= $totalFotos === 0 ? 'disabled' : '' ?>"
               id="btnGerarPdf"
               title="<?= $totalFotos === 0 ? 'Adicione pelo menos uma foto para gerar o PDF' : 'Baixar PDF do checking' ?>">
                📄 Gerar PDF
            </a>
        </div>
    </div>

    <!-- Contador -->
    <div class="ck-counter-bar">
        <span class="ck-counter-num" id="totalFotos"><?= $totalFotos ?></span>
        <span>foto<?= $totalFotos !== 1 ? 's' : '' ?> enviada<?= $totalFotos !== 1 ? 's' : '' ?> neste checking</span>
    </div>

    <!-- Cards por ponto -->
    <?php foreach ($pontos as $p):
        $fotos = $existentes[$p['id']] ?? [];
    ?>
    <div class="ck-ponto-card" id="card-ponto-<?= $p['id'] ?>">
        <div class="ck-ponto-head">
            <span class="ck-ponto-num"><?= str_pad($p['numero'], 3, '0', STR_PAD_LEFT) ?></span>
            <div class="ck-ponto-info">
                <div class="ck-ponto-log"><?= htmlspecialchars($p['logradouro']) ?></div>
                <div class="ck-ponto-cid"><?= htmlspecialchars(implode(' · ', array_filter([$p['cidade'] ?? '', $p['regiao'] ?? '']))) ?></div>
            </div>
            <?php if (!empty($p['latitude']) && !empty($p['longitude'])): ?>
            <a href="https://maps.google.com/?q=<?= $p['latitude'] ?>,<?= $p['longitude'] ?>"
               target="_blank" class="ck-ponto-maps">📍 Maps</a>
            <?php endif; ?>
        </div>
        <div class="ck-ponto-body">
            <!-- Upload zone -->
            <div class="ck-upload-zone" id="zone-<?= $p['id'] ?>"
                 ondragover="dragOver(event, <?= $p['id'] ?>)"
                 ondragleave="dragLeave(event, <?= $p['id'] ?>)"
                 ondrop="drop(event, <?= $p['id'] ?>)">
                <input type="file" accept="image/jpeg,image/png,image/webp"
                       multiple
                       onchange="uploadFotos(this.files, <?= $p['id'] ?>)">
                <div class="ck-upload-icon">📷</div>
                <div class="ck-upload-txt">Clique ou arraste as fotos aqui</div>
                <div class="ck-upload-sub">JPEG, PNG ou WebP · máx. 8MB por foto</div>
            </div>

            <!-- Grid de fotos -->
            <div class="ck-fotos-grid" id="grid-<?= $p['id'] ?>">
                <?php if (empty($fotos)): ?>
                <div class="ck-sem-foto" id="semfoto-<?= $p['id'] ?>">Nenhuma foto enviada ainda.</div>
                <?php else: ?>
                <?php foreach ($fotos as $f): ?>
                <div class="ck-foto-item" id="foto-<?= $f['id'] ?>">
                    <div class="ck-foto-img-wrap">
                        <img src="/<?= htmlspecialchars($f['caminho']) ?>" alt="Checking ponto <?= $p['numero'] ?>" loading="lazy">
                        <button class="ck-foto-del" onclick="excluirFoto(<?= $f['id'] ?>, <?= $p['id'] ?>)" title="Remover foto">✕</button>
                    </div>
                    <textarea class="ck-foto-nota" placeholder="Observação (opcional)…"
                              onchange="salvarNota(<?= $f['id'] ?>, this.value)"><?= htmlspecialchars($f['legenda'] ?? '') ?></textarea>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

</div>

<!-- Toast -->
<div class="ck-toast" id="ckToast"></div>

<script>
var CSRF      = <?= json_encode($csrf) ?>;
var CLIENTE   = <?= json_encode($cliente) ?>;
var AGENCIA   = <?= json_encode($agencia) ?>;
var CAMPANHA  = <?= json_encode($campanha) ?>;
var SITUACAO  = <?= json_encode($situacao) ?>;
var INICIO    = <?= json_encode($inicio) ?>;
var FIM       = <?= json_encode($fim) ?>;

// ── Toast ─────────────────────────────────────
function toast(msg, tipo) {
    var t = document.getElementById('ckToast');
    t.textContent = msg;
    t.className = 'ck-toast show ' + (tipo || '');
    setTimeout(function() { t.className = 'ck-toast'; }, 3000);
}

// ── Contador total de fotos ───────────────────
function atualizarContador(delta) {
    var el = document.getElementById('totalFotos');
    var n  = parseInt(el.textContent) + delta;
    el.textContent = n;
    // Habilita/desabilita botão PDF
    var btn = document.getElementById('btnGerarPdf');
    if (n > 0) {
        btn.classList.remove('disabled');
        btn.title = 'Baixar PDF do checking';
    } else {
        btn.classList.add('disabled');
        btn.title = 'Adicione pelo menos uma foto para gerar o PDF';
    }
}

// ── Drag & drop ───────────────────────────────
function dragOver(e, pontoId) {
    e.preventDefault();
    document.getElementById('zone-' + pontoId).classList.add('drag-over');
}
function dragLeave(e, pontoId) {
    document.getElementById('zone-' + pontoId).classList.remove('drag-over');
}
function drop(e, pontoId) {
    e.preventDefault();
    document.getElementById('zone-' + pontoId).classList.remove('drag-over');
    uploadFotos(e.dataTransfer.files, pontoId);
}

// ── Upload ────────────────────────────────────
function uploadFotos(files, pontoId) {
    if (!files || files.length === 0) return;
    Array.from(files).forEach(function(file) {
        uploadUmaFoto(file, pontoId);
    });
}

function uploadUmaFoto(file, pontoId) {
    var maxSize = 8 * 1024 * 1024;
    if (file.size > maxSize) {
        toast('❌ Arquivo muito grande: ' + file.name, 'err');
        return;
    }
    var allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (allowed.indexOf(file.type) === -1) {
        toast('❌ Formato inválido: ' + file.name, 'err');
        return;
    }

    var grid = document.getElementById('grid-' + pontoId);

    // Remove placeholder "sem foto"
    var sem = document.getElementById('semfoto-' + pontoId);
    if (sem) sem.remove();

    // Placeholder animado
    var placeholder = document.createElement('div');
    placeholder.className = 'ck-foto-uploading';
    placeholder.textContent = '⏳ Enviando…';
    grid.appendChild(placeholder);

    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('ponto_id',   pontoId);
    fd.append('cliente',    CLIENTE);
    fd.append('agencia',    AGENCIA);
    fd.append('campanha',   CAMPANHA);
    fd.append('situacao',   SITUACAO);
    fd.append('inicio',     INICIO || '');
    fd.append('fim',        FIM    || '');
    fd.append('foto',       file);

    fetch('/gestor/campanhas/checking/upload', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        placeholder.remove();
        if (!data.ok) {
            toast('❌ ' + (data.erro || 'Erro ao enviar'), 'err');
            return;
        }
        var div = document.createElement('div');
        div.className = 'ck-foto-item';
        div.id = 'foto-' + data.foto.id;
        div.innerHTML =
            '<div class="ck-foto-img-wrap">' +
            '<img src="/' + data.foto.caminho + '" alt="Foto checking" loading="lazy">' +
            '<button class="ck-foto-del" onclick="excluirFoto(' + data.foto.id + ', ' + pontoId + ')" title="Remover foto">✕</button>' +
            '</div>' +
            '<textarea class="ck-foto-nota" placeholder="Observação (opcional)…" onchange="salvarNota(' + data.foto.id + ', this.value)"></textarea>';
        grid.appendChild(div);
        atualizarContador(1);
        toast('✅ Foto enviada!', 'ok');
    })
    .catch(function() {
        placeholder.remove();
        toast('❌ Erro de comunicação.', 'err');
    });
}

// ── Observação da foto ────────────────────────
function salvarNota(fotoId, valor) {
    var fd = new FormData();
    fd.append('csrf_token',  CSRF);
    fd.append('action',      'salvar_nota');
    fd.append('foto_id',     fotoId);
    fd.append('observacao',  valor);

    fetch('/gestor/campanhas/checking/upload', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.ok) { toast('❌ Erro ao salvar observação', 'err'); return; }
        toast('📝 Observação salva', 'ok');
    })
    .catch(function() { toast('❌ Erro de comunicação.', 'err'); });
}

// ── Excluir foto ─────────────────────────────
function excluirFoto(fotoId, pontoId) {
    if (!confirm('Remover esta foto do checking?')) return;

    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('action',     'excluir');
    fd.append('foto_id',    fotoId);

    fetch('/gestor/campanhas/checking/upload', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.ok) { toast('❌ ' + (data.erro || 'Erro ao excluir'), 'err'); return; }
        var el = document.getElementById('foto-' + fotoId);
        if (el) el.remove();
        atualizarContador(-1);

        // Se grid ficou vazio, adiciona placeholder
        var grid = document.getElementById('grid-' + pontoId);
        if (!grid.querySelector('.ck-foto-item')) {
            var sem = document.createElement('div');
            sem.className = 'ck-sem-foto';
            sem.id = 'semfoto-' + pontoId;
            sem.textContent = 'Nenhuma foto enviada ainda.';
            grid.appendChild(sem);
        }
        toast('🗑️ Foto removida.', 'ok');
    })
    .catch(function() { toast('❌ Erro de comunicação.', 'err'); });
}
</script>

</body>
</html>
