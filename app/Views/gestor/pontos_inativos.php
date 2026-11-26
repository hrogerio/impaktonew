<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}
$paginaAtual = 'pontos-inativos';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

try {
    require_once __DIR__ . '/../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("Erro na conexão com o banco de dados.");
}

// Dados de inativação vêm do pontos_log (campo 'ativo'). Pontos desativados
// antes desse registro existir aparecem sem data — por isso o try/catch e o "—".
$pontos = $pdo->query("
    SELECT p.id, p.numero, p.logradouro, p.cidade, p.regiao, p.situacao, p.tipo, p.formato,
           COALESCE(
               (SELECT pf.caminho FROM ponto_fotos pf WHERE pf.ponto_id = p.id AND pf.principal = 1 LIMIT 1),
               p.foto
           ) AS foto,
           (SELECT c.cliente FROM campanhas c
             WHERE c.ponto_id = p.id AND c.cliente IS NOT NULL AND c.cliente <> ''
             ORDER BY COALESCE(c.fim, c.criado_em) DESC, c.id DESC LIMIT 1) AS ultimo_cliente,
           (SELECT MAX(c.fim) FROM campanhas c WHERE c.ponto_id = p.id) AS ultimo_fim
    FROM pontos p
    WHERE p.ativo = 0
    ORDER BY p.numero
")->fetchAll(PDO::FETCH_ASSOC);

$inativacoes = [];
try {
    $rows = $pdo->query("
        SELECT pl.ponto_id, pl.alterado_em, pl.alterado_por
        FROM pontos_log pl
        INNER JOIN (
            SELECT ponto_id, MAX(id) AS id FROM pontos_log
            WHERE campo = 'ativo' AND valor_depois = '0'
            GROUP BY ponto_id
        ) u ON u.id = pl.id
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) $inativacoes[(int)$r['ponto_id']] = $r;
} catch (Exception $e) { /* sem histórico — segue sem as datas */ }

$porRegiao = [];
$cidades   = [];
foreach ($pontos as $p) {
    $reg = mb_convert_case(trim($p['regiao'] ?? ''), MB_CASE_TITLE, 'UTF-8') ?: 'Sem região';
    $porRegiao[$reg] = ($porRegiao[$reg] ?? 0) + 1;
    if (!empty($p['cidade'])) $cidades[$p['cidade']] = true;
}
ksort($porRegiao, SORT_LOCALE_STRING);
ksort($cidades, SORT_LOCALE_STRING);

$msg = $_GET['msg'] ?? '';
$qtdReativados = (int)($_GET['qtd'] ?? 0);

function fmtData(?string $d): string {
    if (!$d || substr($d, 0, 4) === '0000') return '';
    $t = strtotime($d);
    return $t ? date('d/m/Y', $t) : '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pontos Inativos · Impakto</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css?v=2">
    <link rel="stylesheet" href="/public/assets/css/backup.css">
    <style>
        .pi-head { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; flex-wrap:wrap; }
        .pi-total { font-size:2rem; font-weight:800; color:var(--color-accent-primary); line-height:1; text-align:right; }
        .pi-total small { display:block; font-size:0.7rem; font-weight:600; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:0.5px; margin-top:0.25rem; }

        .pi-regioes { display:flex; gap:0.5rem; flex-wrap:wrap; margin-top:1rem; }
        .pi-chip { display:inline-flex; align-items:center; gap:0.4rem; padding:0.35rem 0.75rem; border:1px solid var(--color-border); border-radius:999px; background:var(--color-bg-white); font-family:'Montserrat',sans-serif; font-size:0.75rem; font-weight:600; color:var(--color-text-dark); cursor:pointer; transition:all var(--duration-fast) var(--ease); }
        .pi-chip b { background:var(--color-bg-primary); border-radius:999px; padding:0 0.45rem; font-size:0.7rem; }
        .pi-chip:hover { border-color:var(--color-accent-primary); }
        .pi-chip.ativo { background:var(--color-accent-primary); border-color:var(--color-accent-primary); color:#fff; }
        .pi-chip.ativo b { background:rgba(255,255,255,0.25); }

        .pi-toolbar { display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap; margin-bottom:1rem; }
        .pi-toolbar input[type=search], .pi-toolbar select { padding:0.55rem 0.75rem; border:1px solid var(--color-border); border-radius:8px; font-family:'Montserrat',sans-serif; font-size:0.82rem; background:var(--color-bg-white); color:var(--color-text-dark); }
        .pi-toolbar input[type=search] { flex:1 1 260px; min-width:0; }
        .pi-toolbar input:focus, .pi-toolbar select:focus { outline:none; border-color:var(--color-accent-primary); }
        .pi-contador { font-size:0.78rem; color:var(--color-text-muted); white-space:nowrap; }
        .btn-reativar-lote { display:inline-flex; align-items:center; gap:0.4rem; padding:0.55rem 1rem; border:none; border-radius:8px; background:#166534; color:#fff; font-family:'Montserrat',sans-serif; font-size:0.8rem; font-weight:700; cursor:pointer; }
        .btn-reativar-lote:disabled { background:#cbd5e1; cursor:not-allowed; }

        .pi-table td { vertical-align:middle; }
        .pi-table th.chk, .pi-table td.chk { width:34px; text-align:center; }
        .pi-table input[type=checkbox] { width:16px; height:16px; accent-color:var(--color-accent-primary); cursor:pointer; }
        .pi-thumb { width:64px; height:42px; border-radius:6px; overflow:hidden; background:var(--color-bg-primary); display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:1rem; }
        .pi-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
        .pi-num { font-weight:800; font-size:0.9rem; color:var(--color-text-dark); }
        .pi-log { font-weight:600; color:var(--color-text-dark); }
        .pi-sub { display:block; color:var(--color-text-muted); font-size:0.72rem; margin-top:0.1rem; }
        .pi-tag { display:inline-block; padding:2px 8px; border-radius:999px; background:var(--color-bg-primary); color:var(--color-text-muted); font-size:0.7rem; font-weight:600; }
        .pi-acoes { white-space:nowrap; text-align:right; }
        .pi-acoes form { display:inline; }
        .btn-reativar { display:inline-flex; align-items:center; gap:0.3rem; padding:3px 10px; border:1.5px solid #166534; border-radius:6px; background:#166534; color:#fff; font-size:0.75rem; font-weight:700; font-family:'Montserrat',sans-serif; cursor:pointer; margin-left:0.35rem; }
        .btn-reativar:hover { background:#14532d; border-color:#14532d; }
        .btn-ver { border-color:var(--color-text-dark); color:var(--color-text-dark); }
        .btn-ver:hover { background:var(--color-text-dark); color:#fff; }
        .pi-table tr.oculto { display:none; }
        .pi-table tr.sel { background:#fff7ed; }

        @media (max-width: 760px) {
            .pi-table thead { display:none; }
            .pi-table, .pi-table tbody, .pi-table tr, .pi-table td { display:block; width:100%; }
            .pi-table tr { position:relative; padding:0.75rem 0.75rem 0.75rem 5.5rem; border-bottom:1px solid var(--color-border); min-height:64px; }
            .pi-table td { border:none; padding:0.1rem 0; }
            .pi-table td.chk { position:absolute; left:0.5rem; top:0.85rem; width:auto; }
            .pi-table td.foto { position:absolute; left:2rem; top:0.75rem; width:auto; }
            .pi-thumb { width:48px; height:36px; }
            .pi-table td.col-cli, .pi-table td.col-inat { font-size:0.75rem; color:var(--color-text-muted); }
            .pi-acoes { text-align:left; margin-top:0.4rem; }
            .pi-total { text-align:left; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../partials/env_banner.php'; ?>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="container" style="padding-bottom:2rem;">

    <div class="welcome" style="margin-bottom:1.25rem;">
        <div class="pi-head">
            <div>
                <h2>⏸ Pontos Inativos</h2>
                <p>Pontos desativados não aparecem na listagem principal, no mapa nem podem ser incluídos em pré-seleções. Reative um ponto para que ele volte a ser comercializado.</p>
            </div>
            <div class="pi-total"><?= count($pontos) ?><small>inativo(s)</small></div>
        </div>
        <?php if ($porRegiao): ?>
        <div class="pi-regioes" id="chipsRegiao">
            <?php foreach ($porRegiao as $reg => $qtd): ?>
                <button type="button" class="pi-chip" data-regiao="<?= htmlspecialchars($reg) ?>"><?= htmlspecialchars($reg) ?> <b><?= $qtd ?></b></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($msg === 'reativados'): ?>
        <div class="alerta alerta-ok">✅ <?= $qtdReativados ?> ponto(s) reativado(s) com sucesso.</div>
    <?php endif; ?>

    <?php if (empty($pontos)): ?>
        <div class="table-container"><div class="backup-empty">🎉 Nenhum ponto inativo no momento.</div></div>
    <?php else: ?>

    <form method="post" action="/gestor/pontos/reativar" id="formLote">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="voltar" value="inativos">

        <div class="pi-toolbar">
            <input type="search" id="busca" placeholder="Buscar por nº, logradouro, cidade ou cliente…" autocomplete="off">
            <select id="filtroCidade">
                <option value="">Todas as cidades</option>
                <?php foreach (array_keys($cidades) as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="pi-contador" id="contador"></span>
            <button type="submit" class="btn-reativar-lote" id="btnLote" disabled>↻ Reativar selecionados</button>
        </div>

        <div class="table-container" style="overflow-x:auto;">
            <table class="backup-table pi-table">
                <thead>
                    <tr>
                        <th class="chk"><input type="checkbox" id="chkTodos" title="Selecionar visíveis"></th>
                        <th></th>
                        <th>Nº</th>
                        <th>Logradouro</th>
                        <th>Último cliente</th>
                        <th>Inativado em</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pontos as $p):
                    $id   = (int)$p['id'];
                    $reg  = mb_convert_case(trim($p['regiao'] ?? ''), MB_CASE_TITLE, 'UTF-8') ?: 'Sem região';
                    $inat = $inativacoes[$id] ?? null;
                    $busca = mb_strtolower(implode(' ', [$p['numero'], $p['logradouro'], $p['cidade'], $reg, $p['ultimo_cliente'] ?? '']));
                    $fim  = fmtData($p['ultimo_fim'] ?? null);
                ?>
                    <tr data-regiao="<?= htmlspecialchars($reg) ?>" data-cidade="<?= htmlspecialchars($p['cidade'] ?? '') ?>" data-busca="<?= htmlspecialchars($busca) ?>">
                        <td class="chk"><input type="checkbox" name="ids[]" value="<?= $id ?>" class="chk-ponto"></td>
                        <td class="foto">
                            <div class="pi-thumb">
                                <?php if (!empty($p['foto'])): ?>
                                    <img src="/<?= htmlspecialchars(ltrim($p['foto'], '/')) ?>" alt="" loading="lazy" onerror="this.parentElement.textContent='📷'">
                                <?php else: ?>📷<?php endif; ?>
                            </div>
                        </td>
                        <td class="pi-num"><?= htmlspecialchars($p['numero']) ?></td>
                        <td>
                            <span class="pi-log"><?= htmlspecialchars($p['logradouro']) ?></span>
                            <span class="pi-sub"><?= htmlspecialchars($p['cidade'] ?? '—') ?> · <?= htmlspecialchars($reg) ?><?php if (!empty($p['tipo'])): ?> · <span class="pi-tag"><?= htmlspecialchars($p['tipo']) ?></span><?php endif; ?></span>
                        </td>
                        <td class="col-cli">
                            <?php if (!empty($p['ultimo_cliente'])): ?>
                                <?= htmlspecialchars($p['ultimo_cliente']) ?>
                                <?php if ($fim): ?><span class="pi-sub">até <?= $fim ?></span><?php endif; ?>
                            <?php else: ?>
                                <span class="backup-size">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="col-inat">
                            <?php if ($inat): ?>
                                <?= fmtData($inat['alterado_em']) ?>
                                <?php if (!empty($inat['alterado_por']) && $inat['alterado_por'] !== 'sistema'): ?><span class="pi-sub">por <?= htmlspecialchars($inat['alterado_por']) ?></span><?php endif; ?>
                            <?php else: ?>
                                <span class="backup-size" title="Desativado antes do registro de histórico">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="pi-acoes">
                            <a href="/gestor/pontos/editar?id=<?= $id ?>" class="btn-dl btn-ver">Ver / Editar</a>
                            <button type="submit" class="btn-reativar" name="ids[]" value="<?= $id ?>" formnovalidate
                                    data-numero="<?= htmlspecialchars($p['numero']) ?>">↻ Reativar</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                    <tr id="semResultado" class="oculto"><td colspan="7" class="backup-empty">Nenhum ponto encontrado com esses filtros.</td></tr>
                </tbody>
            </table>
        </div>
    </form>
    <?php endif; ?>

</div>

<script>
(function () {
    const form = document.getElementById('formLote');
    if (!form) return;

    const linhas   = [...form.querySelectorAll('tbody tr[data-busca]')];
    const busca    = document.getElementById('busca');
    const cidade   = document.getElementById('filtroCidade');
    const chips    = [...document.querySelectorAll('#chipsRegiao .pi-chip')];
    const chkTodos = document.getElementById('chkTodos');
    const btnLote  = document.getElementById('btnLote');
    const contador = document.getElementById('contador');
    const vazio    = document.getElementById('semResultado');
    const KEY      = 'pontosInativosFiltros';
    let regiao     = '';

    const norm = s => (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

    function aplicar() {
        const q = norm(busca.value.trim());
        let visiveis = 0;
        linhas.forEach(tr => {
            const ok = (!q || norm(tr.dataset.busca).includes(q))
                    && (!regiao || tr.dataset.regiao === regiao)
                    && (!cidade.value || tr.dataset.cidade === cidade.value);
            tr.classList.toggle('oculto', !ok);
            if (ok) visiveis++;
        });
        chips.forEach(c => c.classList.toggle('ativo', c.dataset.regiao === regiao));
        vazio.classList.toggle('oculto', visiveis > 0);
        contador.textContent = visiveis === linhas.length ? `${linhas.length} ponto(s)` : `${visiveis} de ${linhas.length}`;
        try { sessionStorage.setItem(KEY, JSON.stringify({ q: busca.value, regiao, cidade: cidade.value })); } catch (e) {}
        atualizarSel();
    }

    function atualizarSel() {
        const marcados = linhas.filter(tr => tr.querySelector('.chk-ponto').checked);
        linhas.forEach(tr => tr.classList.toggle('sel', tr.querySelector('.chk-ponto').checked));
        btnLote.disabled = marcados.length === 0;
        btnLote.textContent = marcados.length ? `↻ Reativar ${marcados.length} selecionado(s)` : '↻ Reativar selecionados';
        const vis = linhas.filter(tr => !tr.classList.contains('oculto'));
        chkTodos.checked = vis.length > 0 && vis.every(tr => tr.querySelector('.chk-ponto').checked);
    }

    try {
        const s = JSON.parse(sessionStorage.getItem(KEY) || 'null');
        if (s) { busca.value = s.q || ''; regiao = s.regiao || ''; cidade.value = s.cidade || ''; }
    } catch (e) {}

    busca.addEventListener('input', aplicar);
    cidade.addEventListener('change', aplicar);
    chips.forEach(c => c.addEventListener('click', () => { regiao = regiao === c.dataset.regiao ? '' : c.dataset.regiao; aplicar(); }));
    form.addEventListener('change', e => { if (e.target.classList.contains('chk-ponto')) atualizarSel(); });
    chkTodos.addEventListener('change', () => {
        linhas.forEach(tr => { if (!tr.classList.contains('oculto')) tr.querySelector('.chk-ponto').checked = chkTodos.checked; });
        atualizarSel();
    });

    // Botão individual: envia só aquele id (desmarca os checkboxes para não reativar em lote sem querer)
    form.addEventListener('submit', e => {
        const btn = e.submitter;
        if (btn && btn.classList.contains('btn-reativar')) {
            if (!confirm(`Reativar o ponto #${btn.dataset.numero}?`)) { e.preventDefault(); return; }
            form.querySelectorAll('.chk-ponto').forEach(c => c.disabled = true);
            return;
        }
        const n = form.querySelectorAll('.chk-ponto:checked').length;
        if (!n || !confirm(`Reativar ${n} ponto(s) selecionado(s)?`)) e.preventDefault();
    });

    // Ao voltar pelo histórico (bfcache), reabilita checkboxes desabilitados pelo submit individual
    window.addEventListener('pageshow', () => form.querySelectorAll('.chk-ponto').forEach(c => c.disabled = false));

    aplicar();
})();
</script>
</body>
</html>
