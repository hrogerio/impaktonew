<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

require_once __DIR__ . '/../../../config/database.php';
$pdo = getDatabase();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

$itens = $pdo->query("SELECT * FROM midia_kit_paginas ORDER BY ordem ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

$pontos = $pdo->query("
    SELECT id, numero, logradouro, cidade, formato
    FROM pontos
    WHERE (ativo = 1 OR ativo IS NULL)
    ORDER BY numero ASC
")->fetchAll(PDO::FETCH_ASSOC);

$paginaAtual = 'midia-kit';
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
<link rel="stylesheet" href="/public/assets/css/gestor.css">
<title>Mídia Kit — Impakto</title>
<style>
:root {
    --mk-accent: var(--color-accent-primary);
}

.mk-page { max-width: 980px; margin: 0 auto; padding: 1.5rem 1rem 6rem; }

.mk-header {
    background: #fff;
    border: 1.5px solid var(--color-border);
    border-radius: 14px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.mk-titulo { font-size: 1.3rem; font-weight: 800; color: var(--color-text-dark); }
.mk-sub { font-size: 0.82rem; color: var(--color-text-muted); margin-top: 0.2rem; }
.mk-actions { display: flex; gap: 0.6rem; flex-wrap: wrap; }

.mk-btn {
    display: inline-flex; align-items: center; gap: 0.4rem;
    border: none; border-radius: 8px;
    padding: 0.55rem 1.1rem;
    font-size: 0.82rem; font-weight: 700;
    cursor: pointer; text-decoration: none;
    transition: background 0.15s, opacity 0.15s;
}
.mk-btn-primary { background: var(--mk-accent); color: #fff; }
.mk-btn-primary:hover { opacity: 0.9; }
.mk-btn-secondary { background: #f3f4f6; color: var(--color-text-dark); border: 1.5px solid var(--color-border); }
.mk-btn-secondary:hover { background: #e5e7eb; }
.mk-btn-pdf { background: #1a1a2e; color: #fff; }
.mk-btn-pdf:hover { opacity: 0.9; }
.mk-btn-pdf.disabled { opacity: 0.45; pointer-events: none; }

.mk-lista { display: flex; flex-direction: column; gap: 0.75rem; }

.mk-item {
    background: #fff;
    border: 1.5px solid var(--color-border);
    border-radius: 12px;
    padding: 0.85rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.9rem;
}
.mk-item.inativo { opacity: 0.5; }
.mk-item-divisor {
    background: #fafafa;
    border-style: dashed;
}

.mk-thumb {
    width: 64px; height: 48px; border-radius: 8px; flex-shrink: 0;
    object-fit: cover; background: #f3f4f6; border: 1px solid var(--color-border);
}
.mk-logo-thumb {
    width: 64px; height: 48px; border-radius: 8px; flex-shrink: 0;
    object-fit: contain; background: #fff; border: 1px solid var(--color-border);
    padding: 4px;
}
.mk-thumbs { display: flex; gap: 0.4rem; flex-shrink: 0; }
.mk-divisor-icon {
    width: 64px; height: 48px; border-radius: 8px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: #eee; font-size: 1.3rem; color: var(--color-text-muted);
}

.mk-info { flex: 1; min-width: 0; }
.mk-info-sub { font-size: 0.72rem; color: var(--color-text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; }
.mk-info-divisor-titulo { font-size: 0.95rem; font-weight: 800; color: var(--color-text-dark); }
.mk-info-titulo { font-size: 0.95rem; font-weight: 800; color: var(--mk-accent); }
.mk-info-loc { font-size: 0.78rem; color: var(--color-text-muted); }

.mk-item-actions { display: flex; align-items: center; gap: 0.3rem; flex-shrink: 0; }
.mk-icon-btn {
    background: #f3f4f6; border: 1px solid var(--color-border); border-radius: 7px;
    width: 30px; height: 30px; cursor: pointer; font-size: 0.85rem;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.15s;
}
.mk-icon-btn:hover { background: #e5e7eb; }
.mk-icon-btn.danger:hover { background: #fde8e8; color: #c0392b; }

.mk-switch { position: relative; width: 38px; height: 22px; flex-shrink: 0; }
.mk-switch input { opacity: 0; width: 0; height: 0; }
.mk-switch-track {
    position: absolute; cursor: pointer; inset: 0;
    background: #d1d5db; border-radius: 22px; transition: 0.15s;
}
.mk-switch-track::before {
    content: ""; position: absolute; height: 16px; width: 16px;
    left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: 0.15s;
}
.mk-switch input:checked + .mk-switch-track { background: #16a34a; }
.mk-switch input:checked + .mk-switch-track::before { transform: translateX(16px); }

.mk-vazio {
    text-align: center; padding: 3rem 1rem; color: var(--color-text-muted);
    background: #fff; border: 1.5px dashed var(--color-border); border-radius: 14px;
}

/* ── Modal ────────────────────────────────────── */
.mk-modal-bg {
    display: none; position: fixed; inset: 0; background: rgba(20,20,30,0.5);
    z-index: 1000; align-items: center; justify-content: center; padding: 1rem;
}
.mk-modal-bg.aberto { display: flex; }
.mk-modal {
    background: #fff; border-radius: 14px; padding: 1.5rem;
    width: 100%; max-width: 480px; max-height: 90vh; overflow-y: auto;
}
.mk-modal h3 { font-size: 1.05rem; font-weight: 800; margin-bottom: 1rem; color: var(--color-text-dark); }
.mk-field { margin-bottom: 0.9rem; }
.mk-field label { display: block; font-size: 0.78rem; font-weight: 700; color: var(--color-text-muted); margin-bottom: 0.3rem; }
.mk-field input[type=text], .mk-field select {
    width: 100%; padding: 0.55rem 0.7rem; border: 1.5px solid var(--color-border);
    border-radius: 8px; font-size: 0.85rem; font-family: inherit;
}
.mk-upload-zone {
    border: 2px dashed var(--color-border); border-radius: 10px;
    padding: 1rem; text-align: center; cursor: pointer; position: relative;
    background: #fafafa;
}
.mk-upload-zone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.mk-upload-preview { max-width: 100%; max-height: 140px; border-radius: 8px; margin-top: 0.6rem; display: none; }
.mk-modal-footer { display: flex; justify-content: flex-end; gap: 0.6rem; margin-top: 1.2rem; }

.mk-toast {
    position: fixed; bottom: 1.5rem; left: 50%; transform: translateX(-50%) translateY(60px);
    background: #1a1a2e; color: #fff;
    padding: 0.7rem 1.4rem; border-radius: 30px;
    font-size: 0.82rem; font-weight: 600;
    box-shadow: 0 4px 18px rgba(0,0,0,0.2);
    transition: transform 0.3s, opacity 0.3s; opacity: 0; z-index: 1100;
}
.mk-toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
.mk-toast.ok   { background: #166534; }
.mk-toast.err  { background: #c0392b; }
</style>
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="mk-page">

    <div class="mk-header">
        <div>
            <div class="mk-titulo">Mídia Kit</div>
            <div class="mk-sub">Cases e divisores usados para montar o PDF do mídia kit institucional.</div>
        </div>
        <div class="mk-actions">
            <button class="mk-btn mk-btn-secondary" onclick="abrirModalDivisor()">+ Divisor</button>
            <button class="mk-btn mk-btn-primary" onclick="abrirModalCase()">+ Case</button>
            <a href="/gestor/midia-kit/pdf" target="_blank"
               class="mk-btn mk-btn-pdf <?= empty($itens) ? 'disabled' : '' ?>"
               id="btnGerarPdf">📄 Gerar PDF</a>
        </div>
    </div>

    <div class="mk-lista" id="lista">
        <?php if (empty($itens)): ?>
        <div class="mk-vazio" id="vazio">Nenhum item cadastrado ainda. Adicione um case ou divisor para montar o mídia kit.</div>
        <?php else: foreach ($itens as $it): ?>
        <?php if ($it['tipo'] === 'divisor'): ?>
        <div class="mk-item mk-item-divisor<?= $it['ativo'] ? '' : ' inativo' ?>" id="item-<?= $it['id'] ?>">
            <div class="mk-divisor-icon">▤</div>
            <div class="mk-info">
                <div class="mk-info-sub">Divisor de seção</div>
                <div class="mk-info-divisor-titulo"><?= htmlspecialchars($it['titulo']) ?></div>
            </div>
            <div class="mk-item-actions">
                <label class="mk-switch">
                    <input type="checkbox" <?= $it['ativo'] ? 'checked' : '' ?> onchange="toggleAtivo(<?= $it['id'] ?>, this.checked)">
                    <span class="mk-switch-track"></span>
                </label>
                <button class="mk-icon-btn" title="Mover para cima" onclick="mover(<?= $it['id'] ?>, 'cima')">↑</button>
                <button class="mk-icon-btn" title="Mover para baixo" onclick="mover(<?= $it['id'] ?>, 'baixo')">↓</button>
                <button class="mk-icon-btn" title="Editar" onclick="editarItem(<?= $it['id'] ?>)">✎</button>
                <button class="mk-icon-btn danger" title="Excluir" onclick="excluirItem(<?= $it['id'] ?>)">✕</button>
            </div>
        </div>
        <?php else: ?>
        <div class="mk-item<?= $it['ativo'] ? '' : ' inativo' ?>" id="item-<?= $it['id'] ?>">
            <div class="mk-thumbs">
                <img class="mk-logo-thumb" src="/<?= htmlspecialchars($it['logo'] ?? '') ?>" alt="Logo">
                <img class="mk-thumb" src="/<?= htmlspecialchars($it['foto']) ?>" alt="Foto">
            </div>
            <div class="mk-info">
                <div class="mk-info-sub"><?= htmlspecialchars($it['subtitulo'] ?? '') ?></div>
                <div class="mk-info-titulo"><?= htmlspecialchars($it['titulo']) ?></div>
                <div class="mk-info-loc"><?= htmlspecialchars($it['localizacao'] ?? '') ?></div>
            </div>
            <div class="mk-item-actions">
                <label class="mk-switch">
                    <input type="checkbox" <?= $it['ativo'] ? 'checked' : '' ?> onchange="toggleAtivo(<?= $it['id'] ?>, this.checked)">
                    <span class="mk-switch-track"></span>
                </label>
                <button class="mk-icon-btn" title="Mover para cima" onclick="mover(<?= $it['id'] ?>, 'cima')">↑</button>
                <button class="mk-icon-btn" title="Mover para baixo" onclick="mover(<?= $it['id'] ?>, 'baixo')">↓</button>
                <button class="mk-icon-btn" title="Editar" onclick="editarItem(<?= $it['id'] ?>)">✎</button>
                <button class="mk-icon-btn danger" title="Excluir" onclick="excluirItem(<?= $it['id'] ?>)">✕</button>
            </div>
        </div>
        <?php endif; endforeach; endif; ?>
    </div>

</div>

<!-- Modal Case -->
<div class="mk-modal-bg" id="modalCase">
    <div class="mk-modal">
        <h3 id="modalCaseTitulo">Novo case</h3>
        <input type="hidden" id="caseId" value="0">
        <input type="hidden" id="caseFoto" value="">
        <input type="hidden" id="caseLogo" value="">

        <div class="mk-field">
            <label>Ponto (preenche formato e localização automaticamente)</label>
            <select id="casePonto" onchange="autofillPonto()">
                <option value="">— nenhum —</option>
                <?php foreach ($pontos as $p): ?>
                <option value="<?= $p['id'] ?>"
                        data-formato="<?= htmlspecialchars($p['formato'] ?? '') ?>"
                        data-cidade="<?= htmlspecialchars($p['cidade'] ?? '') ?>">
                    #<?= str_pad($p['numero'], 3, '0', STR_PAD_LEFT) ?> — <?= htmlspecialchars($p['logradouro'] ?? '') ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mk-field">
            <label>Logomarca do cliente</label>
            <div class="mk-upload-zone" id="caseLogoZone">
                <input type="file" accept="image/jpeg,image/png,image/webp" onchange="uploadLogoCase(this.files[0])">
                <span id="caseLogoTxt">Clique ou arraste a logo aqui</span>
            </div>
            <img id="caseLogoPreview" class="mk-upload-preview">
        </div>

        <div class="mk-field">
            <label>Cliente</label>
            <input type="text" id="caseTitulo" placeholder="Ex: Moura Dubeux">
        </div>

        <div class="mk-field">
            <label>Foto do painel instalado</label>
            <div class="mk-upload-zone" id="caseUploadZone">
                <input type="file" accept="image/jpeg,image/png,image/webp" onchange="uploadFotoCase(this.files[0])">
                <span id="caseUploadTxt">Clique ou arraste a foto aqui</span>
            </div>
            <img id="casePreview" class="mk-upload-preview">
        </div>

        <div class="mk-field">
            <label>Formato do painel</label>
            <input type="text" id="caseSubtitulo" placeholder="Ex: Painel 18x3 + Aplique">
        </div>

        <div class="mk-field">
            <label>Localização</label>
            <input type="text" id="caseLocalizacao" placeholder="Ex: Praia dos Carneiros">
        </div>

        <div class="mk-modal-footer">
            <button class="mk-btn mk-btn-secondary" onclick="fecharModais()">Cancelar</button>
            <button class="mk-btn mk-btn-primary" onclick="salvarCase()">Salvar</button>
        </div>
    </div>
</div>

<!-- Modal Divisor -->
<div class="mk-modal-bg" id="modalDivisor">
    <div class="mk-modal">
        <h3 id="modalDivisorTitulo">Novo divisor</h3>
        <input type="hidden" id="divisorId" value="0">
        <div class="mk-field">
            <label>Título da seção</label>
            <input type="text" id="divisorTitulo" placeholder="Ex: Painéis 9x6">
        </div>
        <div class="mk-modal-footer">
            <button class="mk-btn mk-btn-secondary" onclick="fecharModais()">Cancelar</button>
            <button class="mk-btn mk-btn-primary" onclick="salvarDivisor()">Salvar</button>
        </div>
    </div>
</div>

<div class="mk-toast" id="mkToast"></div>

<script>
var CSRF = <?= json_encode($csrf) ?>;
var ITENS = <?= json_encode(array_column($itens, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;

function toast(msg, tipo) {
    var t = document.getElementById('mkToast');
    t.textContent = msg;
    t.className = 'mk-toast show ' + (tipo || '');
    setTimeout(function() { t.className = 'mk-toast'; }, 3000);
}

function fecharModais() {
    document.getElementById('modalCase').classList.remove('aberto');
    document.getElementById('modalDivisor').classList.remove('aberto');
}

// ── Case ──────────────────────────────────────
function abrirModalCase() {
    document.getElementById('modalCaseTitulo').textContent = 'Novo case';
    document.getElementById('caseId').value = '0';
    document.getElementById('caseFoto').value = '';
    document.getElementById('caseLogo').value = '';
    document.getElementById('casePonto').value = '';
    document.getElementById('caseTitulo').value = '';
    document.getElementById('caseSubtitulo').value = '';
    document.getElementById('caseLocalizacao').value = '';
    document.getElementById('casePreview').style.display = 'none';
    document.getElementById('caseUploadTxt').textContent = 'Clique ou arraste a foto aqui';
    document.getElementById('caseLogoPreview').style.display = 'none';
    document.getElementById('caseLogoTxt').textContent = 'Clique ou arraste a logo aqui';
    document.getElementById('modalCase').classList.add('aberto');
    document.getElementById('casePonto').focus();
}

function autofillPonto() {
    var sel = document.getElementById('casePonto');
    var opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    var subtitulo = document.getElementById('caseSubtitulo');
    var loc = document.getElementById('caseLocalizacao');
    if (!subtitulo.value) subtitulo.value = opt.getAttribute('data-formato') || '';
    if (!loc.value) loc.value = opt.getAttribute('data-cidade') || '';
}

function uploadFotoCase(file) {
    if (!file) return;
    if (file.size > 8 * 1024 * 1024) { toast('❌ Arquivo muito grande', 'err'); return; }

    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('foto', file);

    document.getElementById('caseUploadTxt').textContent = 'Enviando…';

    fetch('/gestor/midia-kit/upload', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.ok) { toast('❌ ' + (data.erro || 'Erro ao enviar'), 'err'); document.getElementById('caseUploadTxt').textContent = 'Clique ou arraste a foto aqui'; return; }
        document.getElementById('caseFoto').value = data.caminho;
        var prev = document.getElementById('casePreview');
        prev.src = '/' + data.caminho;
        prev.style.display = 'block';
        document.getElementById('caseUploadTxt').textContent = 'Foto enviada — clique para trocar';
    })
    .catch(function() { toast('❌ Erro de comunicação.', 'err'); document.getElementById('caseUploadTxt').textContent = 'Clique ou arraste a foto aqui'; });
}

function uploadLogoCase(file) {
    if (!file) return;
    if (file.size > 8 * 1024 * 1024) { toast('❌ Arquivo muito grande', 'err'); return; }

    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('foto', file);

    document.getElementById('caseLogoTxt').textContent = 'Enviando…';

    fetch('/gestor/midia-kit/upload', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.ok) { toast('❌ ' + (data.erro || 'Erro ao enviar'), 'err'); document.getElementById('caseLogoTxt').textContent = 'Clique ou arraste a logo aqui'; return; }
        document.getElementById('caseLogo').value = data.caminho;
        var prev = document.getElementById('caseLogoPreview');
        prev.src = '/' + data.caminho;
        prev.style.display = 'block';
        document.getElementById('caseLogoTxt').textContent = 'Logo enviada — clique para trocar';
    })
    .catch(function() { toast('❌ Erro de comunicação.', 'err'); document.getElementById('caseLogoTxt').textContent = 'Clique ou arraste a logo aqui'; });
}

function salvarCase() {
    var id        = document.getElementById('caseId').value;
    var titulo    = document.getElementById('caseTitulo').value.trim();
    var subtitulo = document.getElementById('caseSubtitulo').value.trim();
    var loc       = document.getElementById('caseLocalizacao').value.trim();
    var foto      = document.getElementById('caseFoto').value;
    var logo      = document.getElementById('caseLogo').value;
    var pontoId   = document.getElementById('casePonto').value;

    if (!titulo) { toast('❌ Informe o cliente', 'err'); return; }
    if (!logo && id === '0') { toast('❌ Envie a logomarca do cliente', 'err'); return; }
    if (!foto && id === '0') { toast('❌ Envie a foto do painel', 'err'); return; }

    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('id', id);
    fd.append('tipo', 'case');
    fd.append('ponto_id', pontoId);
    fd.append('titulo', titulo);
    fd.append('subtitulo', subtitulo);
    fd.append('localizacao', loc);
    fd.append('foto', foto);
    fd.append('logo', logo);
    fd.append('ativo', id === '0' ? '1' : (ITENS[id] ? ITENS[id].ativo : '1'));

    fetch('/gestor/midia-kit/salvar', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.ok) { toast('❌ ' + (data.erro || 'Erro ao salvar'), 'err'); return; }
        toast('✅ Salvo!', 'ok');
        setTimeout(function() { location.reload(); }, 500);
    })
    .catch(function() { toast('❌ Erro de comunicação.', 'err'); });
}

function editarItem(id) {
    var it = ITENS[id];
    if (!it) return;
    if (it.tipo === 'divisor') {
        document.getElementById('modalDivisorTitulo').textContent = 'Editar divisor';
        document.getElementById('divisorId').value = id;
        document.getElementById('divisorTitulo').value = it.titulo;
        document.getElementById('modalDivisor').classList.add('aberto');
        return;
    }
    document.getElementById('modalCaseTitulo').textContent = 'Editar case';
    document.getElementById('caseId').value = id;
    document.getElementById('caseFoto').value = '';
    document.getElementById('caseLogo').value = '';
    document.getElementById('casePonto').value = it.ponto_id || '';
    document.getElementById('caseTitulo').value = it.titulo || '';
    document.getElementById('caseSubtitulo').value = it.subtitulo || '';
    document.getElementById('caseLocalizacao').value = it.localizacao || '';
    var prev = document.getElementById('casePreview');
    prev.src = '/' + it.foto;
    prev.style.display = 'block';
    document.getElementById('caseUploadTxt').textContent = 'Clique ou arraste para trocar a foto';
    var logoPrev = document.getElementById('caseLogoPreview');
    logoPrev.src = '/' + it.logo;
    logoPrev.style.display = 'block';
    document.getElementById('caseLogoTxt').textContent = 'Clique ou arraste para trocar a logo';
    document.getElementById('modalCase').classList.add('aberto');
}

// ── Divisor ───────────────────────────────────
function abrirModalDivisor() {
    document.getElementById('modalDivisorTitulo').textContent = 'Novo divisor';
    document.getElementById('divisorId').value = '0';
    document.getElementById('divisorTitulo').value = '';
    document.getElementById('modalDivisor').classList.add('aberto');
}

function salvarDivisor() {
    var id     = document.getElementById('divisorId').value;
    var titulo = document.getElementById('divisorTitulo').value.trim();
    if (!titulo) { toast('❌ Informe o título da seção', 'err'); return; }

    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('id', id);
    fd.append('tipo', 'divisor');
    fd.append('titulo', titulo);
    fd.append('ativo', id === '0' ? '1' : (ITENS[id] ? ITENS[id].ativo : '1'));

    fetch('/gestor/midia-kit/salvar', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.ok) { toast('❌ ' + (data.erro || 'Erro ao salvar'), 'err'); return; }
        toast('✅ Salvo!', 'ok');
        setTimeout(function() { location.reload(); }, 500);
    })
    .catch(function() { toast('❌ Erro de comunicação.', 'err'); });
}

// ── Ações comuns ────────────────────────────────
function toggleAtivo(id, ativo) {
    var it = ITENS[id];
    if (!it) return;

    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('id', id);
    fd.append('tipo', it.tipo);
    fd.append('ponto_id', it.ponto_id || '');
    fd.append('titulo', it.titulo || '');
    fd.append('subtitulo', it.subtitulo || '');
    fd.append('localizacao', it.localizacao || '');
    fd.append('ativo', ativo ? '1' : '0');

    fetch('/gestor/midia-kit/salvar', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.ok) { toast('❌ ' + (data.erro || 'Erro ao atualizar'), 'err'); return; }
        it.ativo = ativo ? 1 : 0;
        document.getElementById('item-' + id).classList.toggle('inativo', !ativo);
    })
    .catch(function() { toast('❌ Erro de comunicação.', 'err'); });
}

function mover(id, direcao) {
    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('id', id);
    fd.append('direcao', direcao);

    fetch('/gestor/midia-kit/mover', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.ok) { toast('❌ ' + (data.erro || 'Erro ao mover'), 'err'); return; }
        location.reload();
    })
    .catch(function() { toast('❌ Erro de comunicação.', 'err'); });
}

function excluirItem(id) {
    if (!confirm('Excluir este item do mídia kit?')) return;

    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('id', id);

    fetch('/gestor/midia-kit/excluir', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.ok) { toast('❌ ' + (data.erro || 'Erro ao excluir'), 'err'); return; }
        toast('🗑️ Removido.', 'ok');
        setTimeout(function() { location.reload(); }, 400);
    })
    .catch(function() { toast('❌ Erro de comunicação.', 'err'); });
}
</script>

</body>
</html>
