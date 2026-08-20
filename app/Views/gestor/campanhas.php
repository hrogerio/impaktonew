<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}
$paginaAtual = 'campanhas';

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/campanhas/_helpers.php';
$pdo = getDatabase();

// A lista de campanhas NÃO é carregada aqui — só via AJAX (gestor/campanhas/buscar)
// quando o usuário aplica um filtro. Aqui só buscamos o necessário pra montar
// os KPIs e o dropdown de clientes, sem renderizar nenhum card.
$hoje = date('Y-m-d');
$gruposAtivos     = campanhasBuscarGrupos($pdo, '');
$gruposEncerrados = campanhasBuscarGrupos($pdo, 'Encerradas');

$totalAtivas = count($gruposAtivos);
$totalEncerradas = count($gruposEncerrados);
$totalVencidos = 0;
foreach ($gruposAtivos as $g) {
    if ($g['fim'] && substr($g['fim'], 0, 10) < $hoje) $totalVencidos++;
}

$listaCampanhas = [];
foreach ([...$gruposAtivos, ...$gruposEncerrados] as $g) {
    if ($g['titulo'] !== 'Sem nome') $listaCampanhas[$g['titulo']] = true;
}
ksort($listaCampanhas);
$listaCampanhas = array_keys($listaCampanhas);

// Cadastro de clientes (para autocomplete no modal de edição)
$listaClientesCadastro = $pdo->query("SELECT razao_social FROM clientes ORDER BY razao_social ASC")
                              ->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campanhas · Impakto</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
    <style>
        .cp-page { max-width:1100px; margin:0 auto; padding:1.5rem 1.5rem 4rem; }

        /* ── KPIs ── */
        .cp-kpis { display:grid; grid-template-columns:repeat(3,1fr); gap:0.75rem; margin-bottom:1.5rem; }
        .cp-kpi {
            background:white; border:1px solid var(--color-border); border-radius:10px;
            padding:0.9rem 1rem; display:flex; flex-direction:column; gap:0.2rem;
        }
        .cp-kpi-label { font-size:0.65rem; font-weight:800; text-transform:uppercase; letter-spacing:0.4px; color:var(--color-text-muted); }
        .cp-kpi-val   { font-size:1.7rem; font-weight:800; color:var(--color-text-dark); line-height:1; }
        .cp-kpi-val.verde   { color:#1a9059; }
        .cp-kpi-val.laranja { color:#fd7e14; }
        .cp-kpi-val.vermelho { color:#dc2626; }
        .cp-kpi-val.azul    { color:#0284c7; }

        /* ── Filtros ── */
        .cp-filtros {
            display:flex; gap:0.6rem; flex-wrap:wrap;
            background:white; border:1px solid var(--color-border);
            border-radius:10px; padding:0.75rem 1rem; margin-bottom:1.5rem;
            align-items:center;
        }
        .cp-busca-wrap { position:relative; flex:1; min-width:180px; }
        .cp-busca-icon { position:absolute; left:0.65rem; top:50%; transform:translateY(-50%); color:#aaa; font-size:0.85rem; }
        .cp-busca {
            width:100%; padding:0.45rem 0.75rem 0.45rem 2rem;
            border:1px solid var(--color-border); border-radius:7px;
            font-family:'Montserrat',sans-serif; font-size:0.82rem; box-sizing:border-box;
        }
        .cp-busca:focus { outline:none; border-color:var(--color-accent-primary); }
        .cp-sel {
            padding:0.45rem 0.65rem; border:1px solid var(--color-border);
            border-radius:7px; font-family:'Montserrat',sans-serif;
            font-size:0.82rem; background:white; color:var(--color-text-dark); cursor:pointer;
        }
        .cp-sel:focus { outline:none; border-color:var(--color-accent-primary); }
        .cp-sel.ativo { border-color:var(--color-accent-primary); background:#fff5f5; font-weight:700; }
        .cp-radio-group { display:flex; gap:0.4rem; }
        .cp-radio-opt {
            display:flex; align-items:center; gap:0.35rem; padding:0.45rem 0.75rem;
            border:1px solid var(--color-border); border-radius:7px; background:white;
            font-size:0.82rem; font-weight:600; color:var(--color-text-dark); cursor:pointer;
            white-space:nowrap; user-select:none;
        }
        .cp-radio-opt input { margin:0; accent-color:var(--color-accent-primary); cursor:pointer; }
        .cp-radio-opt.marcado { border-color:var(--color-accent-primary); background:#fff5f5; font-weight:700; }
        .cp-limpar {
            padding:0.45rem 0.9rem; background:#f3f4f6; border:1px solid var(--color-border);
            border-radius:7px; font-size:0.78rem; font-weight:700; color:#555;
            cursor:pointer; display:none; font-family:'Montserrat',sans-serif;
        }
        .cp-limpar.vis { display:block; }
        .cp-contador { font-size:0.78rem; font-weight:700; color:var(--color-text-muted); white-space:nowrap; }

        /* ── Grid de cards ── */
        .cp-grid {
            display:grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap:1rem;
        }

        /* ── Card ── */
        .cp-card {
            background:white;
            border:1px solid var(--color-border);
            border-radius:12px;
            overflow:hidden;
            display:flex;
            flex-direction:column;
            transition: box-shadow 0.15s;
        }
        .cp-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
        .cp-card.encerrada { background:#fef2f2; border-color:#fecaca; }
        .cp-card.encerrada .cp-card-footer { background:#fee2e2; }
        .cp-card.encerrada .cp-acordeon-toggle:hover { background:#fee2e2; }
        .cp-card.vencida   { background:#fff7ed; border-color:#fed7aa; box-shadow: 0 0 0 2px #ffedd5; }
        .cp-card.vencida .cp-card-footer { background:#ffedd5; }
        .cp-card.vencida .cp-acordeon-toggle:hover { background:#ffedd5; }

        /* Faixa colorida topo */
        .cp-card-faixa {
            height: 4px;
        }

        /* Cabeçalho do card */
        .cp-card-head {
            padding: 0.85rem 1rem 0.6rem;
            border-bottom: 1px solid #f0f2f5;
        }
        .cp-card-top {
            display:flex; align-items:center; gap:0.5rem; margin-bottom:0.35rem; flex-wrap:wrap;
        }
        .sit-dot {
            display:inline-block; width:10px; height:10px; border-radius:50%;
            flex-shrink:0; cursor:help;
        }
        .cp-card-nome {
            font-size:1rem; font-weight:800; color:var(--color-text-dark);
            min-width:0;
        }
        .cp-card-motivo {
            font-size:0.78rem; font-weight:500; color:var(--color-text-muted);
            min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
        }
        .cp-card-cliente {
            font-size:0.82rem; font-weight:600; color:var(--color-text-dark);
            margin-top:1px;
        }
        .cp-card-cliente-link {
            color:inherit; text-decoration:none;
        }
        .cp-card-cliente-link:hover {
            color:var(--color-accent-primary); text-decoration:underline;
        }
        .cp-card-agencia {
            font-size:0.72rem; color:var(--color-text-muted); font-weight:600;
        }
        .cp-card-meta {
            display:flex; align-items:center; gap:0.5rem; margin-top:0.4rem; flex-wrap:wrap;
        }
        .cp-card-periodo {
            font-size:0.75rem; color:var(--color-text-muted); font-weight:600;
        }
        .prazo-urg { background:#fee2e2; color:#991b1b; font-size:0.62rem; font-weight:800; padding:1px 7px; border-radius:8px; }
        .prazo-ale { background:#ffedd5; color:#9a3412; font-size:0.62rem; font-weight:800; padding:1px 7px; border-radius:8px; }
        .status-ativa    { background:#dcfce7; color:#166534; font-size:0.62rem; font-weight:800; padding:1px 7px; border-radius:8px; }
        .status-vencida  { background:#ffedd5; color:#9a3412; font-size:0.62rem; font-weight:800; padding:1px 7px; border-radius:8px; }
        .status-encerrada{ background:#fee2e2; color:#991b1b; font-size:0.62rem; font-weight:800; padding:1px 7px; border-radius:8px; }

        /* Lista de painéis (acordeão) */
        .cp-card-paineis { flex:1; }
        .cp-acordeon-toggle {
            width:100%; display:flex; align-items:center; justify-content:space-between;
            gap:0.5rem; padding:0.55rem 1rem; background:none; border:none;
            border-bottom:1px solid #f5f5f7; cursor:pointer; text-align:left;
            font-family:'Montserrat',sans-serif;
        }
        .cp-acordeon-toggle:hover { background:#fafbfc; }
        .cp-acordeon-toggle-label { font-size:0.78rem; font-weight:700; color:var(--color-text-dark); }
        .cp-acordeon-seta { font-size:0.7rem; color:var(--color-text-muted); transition:transform 0.15s; flex-shrink:0; }
        .cp-acordeon-seta.aberta { transform:rotate(180deg); }
        .cp-card-paineis.fechado { display:none; }
        .cp-painel-row {
            display:flex; align-items:center; gap:0.6rem;
            padding:0.5rem 1rem; border-bottom:1px solid #f5f5f7;
        }
        .cp-painel-row:last-child { border-bottom:none; }
        .cp-painel-num {
            font-weight:800; color:var(--color-accent-primary);
            font-size:0.78rem; min-width:32px; flex-shrink:0;
        }
        .cp-painel-end {
            flex:1; min-width:0;
        }
        .cp-painel-log {
            font-size:0.78rem; font-weight:600; color:var(--color-text-dark);
            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
        }
        .cp-painel-cid {
            font-size:0.68rem; color:var(--color-text-muted); margin-top:1px;
        }
        .cp-painel-link {
            font-size:0.72rem; font-weight:700; color:var(--color-accent-primary);
            text-decoration:none; flex-shrink:0;
        }
        .cp-painel-link:hover { text-decoration:underline; }

        /* Rodapé do card: ações (em acordeão fechado por padrão) */
        .cp-card-footer {
            padding:0.6rem 0.75rem;
            background:#fafbfc;
            border-top:1px solid #f0f2f5;
        }
        .cp-acoes { display:flex; flex-wrap:wrap; gap:0.35rem; }
        .cp-btn {
            padding:3px 9px; border-radius:5px; font-size:0.7rem; font-weight:700;
            cursor:pointer; border:none; font-family:'Montserrat',sans-serif;
            transition:all 0.15s; white-space:nowrap; text-decoration:none;
            display:inline-flex; align-items:center; gap:0.25rem;
        }
        .cp-btn-editar   { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
        .cp-btn-editar:hover   { background:#dbeafe; }
        .cp-btn-renovar  { background:#f0fdf4; color:#166534; border:1px solid #86efac; }
        .cp-btn-renovar:hover  { background:#dcfce7; }
        .cp-btn-encerrar { background:#fff1f0; color:#c0392b; border:1px solid #fca5a5; }
        .cp-btn-encerrar:hover { background:#fee2e2; }
        .cp-btn-checking { background:#fdf4ff; color:#7e22ce; border:1px solid #d8b4fe; text-decoration:none; }
        .cp-btn-checking:hover { background:#f3e8ff; }
        .cp-btn-espelho { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; text-decoration:none; }
        .cp-btn-espelho:hover { background:#ffedd5; }
        .cp-btn-docs { background:#eefdf6; color:#0f766e; border:1px solid #99f6e4; }
        .cp-btn-docs:hover { background:#ccfbf1; }

        /* ── Modal de edição ── */
        .cp-modal-overlay {
            display:none; position:fixed; inset:0;
            background:rgba(0,0,0,0.45); z-index:1000;
            align-items:center; justify-content:center;
        }
        .cp-modal-overlay.aberto { display:flex; }
        .cp-modal {
            background:white; border-radius:14px; padding:1.5rem;
            width:480px; max-width:95vw; max-height:90vh; overflow-y:auto;
            box-shadow:0 20px 60px rgba(0,0,0,0.3);
        }
        .cp-modal-title { font-size:1rem; font-weight:800; color:var(--color-text-dark); margin-bottom:0.25rem; }
        .cp-modal-sub   { font-size:0.78rem; color:var(--color-text-muted); margin-bottom:1.25rem; }
        .cp-modal-field { margin-bottom:0.9rem; }
        .cp-modal-label {
            display:block; font-size:0.65rem; font-weight:800;
            color:var(--color-text-muted); text-transform:uppercase;
            letter-spacing:0.4px; margin-bottom:0.3rem;
        }
        .cp-modal-input {
            width:100%; padding:0.45rem 0.65rem;
            border:1px solid var(--color-border); border-radius:7px;
            font-family:'Montserrat',sans-serif; font-size:0.85rem;
            color:var(--color-text-dark); box-sizing:border-box;
        }
        .cp-modal-input:focus { outline:none; border-color:var(--color-accent-primary); }
        .cp-modal-row { display:flex; gap:0.75rem; }
        .cp-modal-row .cp-modal-field { flex:1; }
        .cp-modal-actions { display:flex; gap:0.75rem; justify-content:flex-end; margin-top:1.25rem; }
        .cp-btn-salvar {
            padding:0.55rem 1.4rem; background:var(--color-accent-primary); color:white;
            border:none; border-radius:8px; font-family:'Montserrat',sans-serif;
            font-size:0.85rem; font-weight:700; cursor:pointer; transition:opacity 0.15s;
        }
        .cp-btn-salvar:hover { opacity:0.9; }
        .cp-btn-salvar:disabled { opacity:0.45; cursor:not-allowed; }
        .cp-btn-cancelar {
            padding:0.55rem 1rem; background:none; color:#666;
            border:1px solid var(--color-border); border-radius:8px;
            font-family:'Montserrat',sans-serif; font-size:0.85rem; cursor:pointer;
        }
        .cp-modal-divider { height:1px; background:var(--color-border); margin:1rem 0; }

        /* ── Modal de documentos (P.I. / P.P.) ── */
        .cp-docs-tipo-titulo { font-size:0.8rem; font-weight:800; color:var(--color-text-dark); margin-bottom:0.5rem; }
        .cp-docs-lista { display:flex; flex-direction:column; gap:0.4rem; margin-bottom:0.6rem; }
        .cp-docs-vazio { font-size:0.78rem; color:var(--color-text-muted); font-style:italic; }
        .cp-docs-item {
            display:flex; align-items:center; justify-content:space-between; gap:0.5rem;
            background:#f8fafc; border:1px solid var(--color-border); border-radius:6px;
            padding:0.4rem 0.6rem; font-size:0.78rem;
        }
        .cp-docs-item a { color:#0f766e; font-weight:700; text-decoration:none; }
        .cp-docs-item a:hover { text-decoration:underline; }
        .cp-docs-item-data { color:var(--color-text-muted); font-size:0.72rem; }
        .cp-docs-item-excluir {
            background:none; border:none; color:#c0392b; cursor:pointer;
            font-size:0.85rem; padding:0 0.25rem; line-height:1;
        }
        .cp-docs-upload {
            display:inline-flex; align-items:center; gap:0.4rem; cursor:pointer;
            font-size:0.78rem; font-weight:700; color:#0f766e;
            background:#eefdf6; border:1px solid #99f6e4; border-radius:6px;
            padding:0.45rem 0.75rem;
        }
        .cp-docs-upload:hover { background:#ccfbf1; }
        .cp-docs-upload input[type="file"] { display:none; }

        .cp-empty { padding:3rem; text-align:center; color:var(--color-text-muted); font-size:0.85rem; }
        .cp-prompt { padding:3rem; text-align:center; color:var(--color-text-muted); font-size:0.88rem; font-weight:600; }

        @media(max-width:700px) {
            .cp-grid  { grid-template-columns:1fr; }
        }
        @media(max-width:480px) {
            .cp-kpis { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="cp-page">

    <!-- ── Título ── -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.75rem">
        <h1 style="font-size:1.3rem;font-weight:800;color:var(--color-text-dark);margin:0">📢 Campanhas</h1>
    </div>

    <!-- ── Alerta de vencidos ── -->
    <?php if ($totalVencidos > 0): ?>
    <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:10px;padding:0.75rem 1rem;margin-bottom:1.25rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
        <span style="font-size:0.85rem;color:#991b1b;font-weight:700">
            🔴 <strong><?= $totalVencidos ?> campanha<?= $totalVencidos > 1 ? 's' : '' ?></strong>
            com contrato vencido ainda marcada<?= $totalVencidos > 1 ? 's' : '' ?> como ativa<?= $totalVencidos > 1 ? 's' : '' ?>.
            Renove ou libere os pontos.
        </span>
        <button onclick="liberarTodosVencidos(this)"
                style="background:#dc3545;color:white;border:none;border-radius:7px;padding:0.4rem 1rem;font-size:0.78rem;font-weight:700;cursor:pointer;font-family:'Montserrat',sans-serif;white-space:nowrap">
            ⚡ Liberar todos (<?= $totalVencidos ?>)
        </button>
    </div>
    <?php endif; ?>

    <!-- ── KPIs ── -->
    <div class="cp-kpis">
        <div class="cp-kpi">
            <div class="cp-kpi-label">Ativas</div>
            <div class="cp-kpi-val verde"><?= $totalAtivas ?></div>
        </div>
        <div class="cp-kpi">
            <div class="cp-kpi-label">Vencidas</div>
            <div class="cp-kpi-val <?= $totalVencidos > 0 ? 'laranja' : '' ?>"><?= $totalVencidos ?></div>
        </div>
        <div class="cp-kpi">
            <div class="cp-kpi-label">Encerradas</div>
            <div class="cp-kpi-val <?= $totalEncerradas > 0 ? 'vermelho' : '' ?>"><?= $totalEncerradas ?></div>
        </div>
    </div>

    <!-- ── Filtros ── -->
    <div class="cp-filtros">
        <div class="cp-busca-wrap">
            <span class="cp-busca-icon">🔍</span>
            <input type="text" id="cpBusca" class="cp-busca" placeholder="Buscar cliente, campanha, ponto..." autocomplete="off">
        </div>
        <select id="cpFiltroCampanha" class="cp-sel">
            <option value="">Todas as campanhas</option>
            <?php foreach ($listaCampanhas as $c): ?>
            <option value="<?= htmlspecialchars(strtolower($c)) ?>"><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="cp-radio-group" id="cpFiltroSitGroup">
            <label class="cp-radio-opt">
                <input type="radio" name="cpSit" value="Vencidas"> Vencidas
            </label>
            <label class="cp-radio-opt">
                <input type="radio" name="cpSit" value="Encerradas"> Encerradas
            </label>
        </div>
        <button class="cp-limpar" id="cpLimpar" onclick="limparFiltros()">✕ Limpar</button>
    </div>

    <!-- ── Grid de cards (preenchido via AJAX ao aplicar um filtro) ── -->
    <div class="cp-grid" id="cpGrid"></div>

    <div class="cp-empty" id="cpEmpty" style="display:none">
        Nenhuma campanha encontrada para os filtros aplicados.
    </div>

</div>

<!-- ── Modal de renovação de campanha ── -->
<div class="cp-modal-overlay" id="cpRenovarOverlay">
    <div class="cp-modal">
        <div class="cp-modal-title">🔄 Renovar Campanha</div>
        <div style="font-size:0.85rem;font-weight:700;color:var(--color-text-dark);margin-bottom:0.15rem" id="cpRenovarCliente"></div>
        <div class="cp-modal-sub" id="cpRenovarSub"></div>

        <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:0.6rem 0.85rem;margin-bottom:1rem;font-size:0.78rem;color:#166534;font-weight:600">
            ✅ Cliente, agência e nome da campanha serão mantidos. Informe apenas as novas datas.
        </div>

        <div class="cp-modal-row">
            <div class="cp-modal-field">
                <label class="cp-modal-label">Novo início</label>
                <input type="date" id="cpRenovarInicio" class="cp-modal-input">
            </div>
            <div class="cp-modal-field">
                <label class="cp-modal-label">Novo fim do contrato *</label>
                <input type="date" id="cpRenovarFim" class="cp-modal-input">
            </div>
        </div>

        <div class="cp-modal-actions">
            <button class="cp-btn-cancelar" onclick="document.getElementById('cpRenovarOverlay').classList.remove('aberto')">Cancelar</button>
            <button class="cp-btn-salvar" id="cpBtnRenovar" onclick="salvarRenovacao()" style="background:#1a9059">🔄 Renovar contrato</button>
        </div>
    </div>
</div>

<!-- ── Modal de edição de campanha ── -->
<div class="cp-modal-overlay" id="cpModalOverlay">
    <div class="cp-modal">
        <div class="cp-modal-title" id="cpModalTitulo">Editar Campanha</div>
        <div class="cp-modal-sub" id="cpModalSub"></div>

        <div class="cp-modal-row">
            <div class="cp-modal-field">
                <label class="cp-modal-label">Nome</label>
                <input type="text" id="cpModalNomeProjeto" class="cp-modal-input" placeholder="Ex: Alto da Passira">
            </div>
            <div class="cp-modal-field">
                <label class="cp-modal-label">Motivo</label>
                <input type="text" id="cpModalNome" class="cp-modal-input" placeholder="Ex: Obras Avançadas">
            </div>
        </div>

        <div class="cp-modal-row">
            <div class="cp-modal-field">
                <label class="cp-modal-label">Cliente</label>
                <input type="text" id="cpModalCliente" class="cp-modal-input" placeholder="Nome do cliente" list="cpClientesCadastro" autocomplete="off">
                <datalist id="cpClientesCadastro">
                    <?php foreach ($listaClientesCadastro as $rs): ?>
                        <option value="<?= htmlspecialchars($rs) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="cp-modal-field">
                <label class="cp-modal-label">Agência</label>
                <input type="text" id="cpModalAgencia" class="cp-modal-input" placeholder="Agência (opcional)">
            </div>
        </div>

        <div class="cp-modal-divider"></div>

        <div class="cp-modal-row">
            <div class="cp-modal-field">
                <label class="cp-modal-label">Início</label>
                <input type="date" id="cpModalInicio" class="cp-modal-input">
            </div>
            <div class="cp-modal-field">
                <label class="cp-modal-label">Fim do contrato</label>
                <input type="date" id="cpModalFim" class="cp-modal-input">
            </div>
        </div>

        <div class="cp-modal-actions">
            <button class="cp-btn-cancelar" onclick="fecharModal()">Cancelar</button>
            <button class="cp-btn-salvar" id="cpBtnSalvar" onclick="salvarEdicao()">💾 Salvar alterações</button>
        </div>
    </div>
</div>

<!-- ── Modal de documentos (P.I. / P.P.) ── -->
<div class="cp-modal-overlay" id="cpDocsOverlay">
    <div class="cp-modal">
        <div class="cp-modal-title">📎 Documentos Financeiros</div>
        <div class="cp-modal-sub" id="cpDocsSub"></div>

        <div class="cp-docs-secao">
            <div class="cp-docs-tipo-titulo">Contrato</div>
            <div class="cp-docs-lista" id="cpDocsListaCONTRATO"></div>
            <label class="cp-docs-upload">
                📤 Enviar novo contrato
                <input type="file" accept="application/pdf" id="cpDocsInputCONTRATO" onchange="enviarDocumento('CONTRATO', this)">
            </label>
        </div>

        <div class="cp-modal-divider"></div>

        <div class="cp-docs-secao">
            <div class="cp-docs-tipo-titulo">Pedido de Inserção (P.I.)</div>
            <div class="cp-docs-lista" id="cpDocsListaPI"></div>
            <label class="cp-docs-upload">
                📤 Enviar novo P.I.
                <input type="file" accept="application/pdf" id="cpDocsInputPI" onchange="enviarDocumento('PI', this)">
            </label>
        </div>

        <div class="cp-modal-divider"></div>

        <div class="cp-docs-secao">
            <div class="cp-docs-tipo-titulo">Pedido de Produção (P.P.)</div>
            <div class="cp-docs-lista" id="cpDocsListaPP"></div>
            <label class="cp-docs-upload">
                📤 Enviar novo P.P.
                <input type="file" accept="application/pdf" id="cpDocsInputPP" onchange="enviarDocumento('PP', this)">
            </label>
        </div>

        <div class="cp-modal-actions">
            <button class="cp-btn-cancelar" onclick="document.getElementById('cpDocsOverlay').classList.remove('aberto')">Fechar</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="cpToast" style="
    position:fixed;top:50%;left:50%;z-index:9999;
    background:#1a9059;color:white;padding:0.9rem 1.5rem;
    border-radius:10px;font-size:0.95rem;font-weight:700;
    box-shadow:0 8px 32px rgba(0,0,0,0.3);
    transform:translate(-50%,-50%) scale(0.9);opacity:0;transition:all 0.3s ease;
    pointer-events:none;max-width:90vw;text-align:center;
"></div>

<script>
// ── Filtros / busca via AJAX ────────────────────────────────────
// A lista só é consultada quando o usuário aplica um filtro (busca,
// cliente ou situação) — a página não carrega nada de cara.
// situacao === ''          -> ativas (padrão)
// situacao === 'Encerradas' -> encerradas
// situacao === 'Vencidas'   -> ativas com contrato vencido
var FILTROS_KEY = 'impakto_campanhas_filtros_v1';
var filtros = { busca:'', cliente:'', campanha:'', situacao:'' };
var _scrollAoAbrir = null; // key do card pra rolar até depois de renderizar
function salvarFiltros() {
    try { sessionStorage.setItem(FILTROS_KEY, JSON.stringify(filtros)); } catch(e) {}
}

function buscarCampanhas() {
    var temFiltro = filtros.busca || filtros.cliente || filtros.campanha || filtros.situacao;
    document.getElementById('cpLimpar').className = 'cp-limpar' + (temFiltro ? ' vis' : '');
    document.getElementById('cpEmpty').style.display = 'none';

    var grid = document.getElementById('cpGrid');
    grid.innerHTML = '<div class="cp-prompt">⏳ Buscando campanhas...</div>';

    var qs = new URLSearchParams({
        busca: filtros.busca || '',
        cliente: filtros.cliente || '',
        campanha: filtros.campanha || '',
        situacao: filtros.situacao || '',
    });

    return fetch('/gestor/campanhas/buscar?' + qs.toString())
        .then(function(r) {
            // Sessão expirada: o backend redireciona pro login (HTML, não JSON).
            if (r.redirected || r.url.indexOf('/campanhas/buscar') === -1) {
                location.href = r.url || '/';
                return Promise.reject(new Error('sessao_expirada'));
            }
            return r.json();
        })
        .then(function(resp) {
            if (!resp.ok) {
                grid.innerHTML = '';
                mostrarToast('❌ Erro ao buscar campanhas', 'err');
                return;
            }
            grid.innerHTML = resp.html;
            document.getElementById('cpEmpty').style.display = resp.total === 0 ? 'block' : 'none';
            restaurarAcordeoes();
            if (_scrollAoAbrir) {
                var card = document.querySelector('#cpGrid .cp-card[data-key="' + _scrollAoAbrir + '"]');
                _scrollAoAbrir = null;
                if (card) card.scrollIntoView({ block: 'center' });
            }
        })
        .catch(function(e) {
            if (e && e.message === 'sessao_expirada') return;
            grid.innerHTML = '';
            mostrarToast('❌ Erro de comunicação ao buscar campanhas', 'err');
        });
}

var debTimer;
(function() {
    var qs = new URLSearchParams(location.search);
    var buscaUrl = qs.get('busca');
    if (buscaUrl) {
        document.getElementById('cpBusca').value = buscaUrl;
        filtros.busca = buscaUrl.toLowerCase().trim();
        salvarFiltros();
        buscarCampanhas();
        return;
    }
    // Sem parâmetro na URL: restaura o último filtro usado (ex: ao voltar da tela anterior),
    // ou carrega as ativas/vencidas por padrão.
    var salvo;
    try { salvo = JSON.parse(sessionStorage.getItem(FILTROS_KEY) || 'null'); } catch(e) { salvo = null; }
    filtros = Object.assign({ busca:'', cliente:'', campanha:'', situacao:'' }, salvo || {});
    document.getElementById('cpBusca').value = filtros.busca;
    document.getElementById('cpFiltroCampanha').value = filtros.campanha;
    document.getElementById('cpFiltroCampanha').className = 'cp-sel' + (filtros.campanha ? ' ativo' : '');
    marcarRadioSit(filtros.situacao);
    buscarCampanhas();
})();
document.getElementById('cpBusca').addEventListener('input', function() {
    clearTimeout(debTimer);
    var val = this.value.toLowerCase().trim();
    debTimer = setTimeout(function() { filtros.busca = val; salvarFiltros(); buscarCampanhas(); }, 300);
});
document.getElementById('cpFiltroCampanha').addEventListener('change', function() {
    filtros.campanha = this.value;
    this.className = 'cp-sel' + (this.value ? ' ativo' : '');
    salvarFiltros();
    buscarCampanhas();
});
document.querySelectorAll('input[name="cpSit"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        if (!this.checked) return;
        filtros.situacao = this.value;
        marcarRadioSit(this.value);
        salvarFiltros();
        buscarCampanhas();
    });
});
function marcarRadioSit(valor) {
    document.querySelectorAll('#cpFiltroSitGroup input[name="cpSit"]').forEach(function(radio) {
        radio.checked = (radio.value === (valor || ''));
        radio.closest('.cp-radio-opt').classList.toggle('marcado', radio.checked);
    });
}
function limparFiltros() {
    filtros = { busca:'', cliente:'', campanha:'', situacao:'' };
    document.getElementById('cpBusca').value = '';
    document.getElementById('cpFiltroCampanha').value = '';
    document.getElementById('cpFiltroCampanha').className = 'cp-sel';
    marcarRadioSit('');
    salvarFiltros();
    buscarCampanhas();
}

// ── Acordeão de pontos ─────────────────────────────────────────
var ACORDEOES_KEY = 'impakto_campanhas_acordeoes_v1';
function salvarAcordeoes() {
    var abertos = [];
    document.querySelectorAll('#cpGrid .cp-card').forEach(function(card) {
        var painel = card.querySelector('.cp-card-paineis');
        if (painel && !painel.classList.contains('fechado')) abertos.push(card.dataset.key);
    });
    try { sessionStorage.setItem(ACORDEOES_KEY, JSON.stringify(abertos)); } catch(e) {}
}
function toggleAcordeon(btn) {
    var painel = btn.nextElementSibling;
    var seta   = btn.querySelector('.cp-acordeon-seta');
    var aberto = !painel.classList.contains('fechado');
    painel.classList.toggle('fechado', aberto);
    seta.classList.toggle('aberta', !aberto);
    salvarAcordeoes();
}
// ── Restaurar acordeões abertos após (re)renderizar os cards ──
function restaurarAcordeoes() {
    var abertos;
    try { abertos = JSON.parse(sessionStorage.getItem(ACORDEOES_KEY) || '[]'); } catch(e) { abertos = []; }
    if (!abertos.length) return;
    abertos.forEach(function(key) {
        var card = document.querySelector('#cpGrid .cp-card[data-key="' + key + '"]');
        if (!card) return;
        var painel = card.querySelector('.cp-card-paineis');
        var seta   = card.querySelector('.cp-acordeon-seta');
        if (painel) painel.classList.remove('fechado');
        if (seta) seta.classList.add('aberta');
    });
}

// ── Modal de edição ───────────────────────────────────────────
var _modalCard = null; // card DOM atualmente no modal

function abrirEdicao(card) {
    var raw = card.dataset.campanha || '';
    var dados;
    try {
        dados = JSON.parse(raw);
    } catch(e) {
        console.error('abrirEdicao: falha ao parsear data-campanha', e, raw);
        alert('Erro ao abrir o editor. Recarregue a página e tente novamente.');
        return;
    }
    if (!dados || !Array.isArray(dados.campIds) || !Array.isArray(dados.pontoIds)) {
        console.error('abrirEdicao: dados incompletos', dados);
        alert('Dados da campanha inválidos. Recarregue a página.');
        return;
    }
    _modalCard = card;

    document.getElementById('cpModalTitulo').textContent = 'Editar Campanha';
    document.getElementById('cpModalSub').textContent    =
        dados.campIds.length + ' ponto' + (dados.campIds.length > 1 ? 's' : '') + ' nesta campanha';
    document.getElementById('cpModalCliente').value      = dados.cliente || '';
    document.getElementById('cpModalAgencia').value      = dados.agencia || '';
    document.getElementById('cpModalNomeProjeto').value  = dados.nome_projeto || '';
    document.getElementById('cpModalNome').value         = (dados.nome && dados.nome !== '—') ? dados.nome : '';
    document.getElementById('cpModalInicio').value   = dados.inicio  || '';
    document.getElementById('cpModalFim').value      = dados.fim     || '';
    document.getElementById('cpBtnSalvar').disabled  = false;
    document.getElementById('cpBtnSalvar').textContent = '💾 Salvar alterações';
    document.getElementById('cpModalOverlay').classList.add('aberto');
}

function fecharModal() {
    document.getElementById('cpModalOverlay').classList.remove('aberto');
    _modalCard = null;
}

// ── Documentos financeiros (P.I. / P.P.) ──────────────────────
var _docsGrupo = null; // {cliente, agencia, campanha, inicio, fim}

function abrirDocumentos(card) {
    var dados;
    try {
        dados = JSON.parse(card.dataset.campanha || '{}');
    } catch(e) {
        alert('Erro ao abrir documentos. Recarregue a página e tente novamente.');
        return;
    }
    _docsGrupo = {
        cliente:  dados.cliente  || '',
        agencia:  dados.agencia  || '',
        campanha: dados.nome     || '',
        inicio:   dados.inicio   || '',
        fim:      dados.fim      || '',
    };
    document.getElementById('cpDocsSub').textContent = dados.cliente + (dados.nome ? ' — ' + dados.nome : '');
    renderizarDocs('CONTRATO', dados.documentos || []);
    renderizarDocs('PI', dados.documentos || []);
    renderizarDocs('PP', dados.documentos || []);
    document.getElementById('cpDocsOverlay').classList.add('aberto');
}

function renderizarDocs(tipo, documentos) {
    var lista = documentos.filter(function(d) { return d.tipo === tipo; });
    var el = document.getElementById('cpDocsLista' + tipo);
    if (lista.length === 0) {
        el.innerHTML = '<div class="cp-docs-vazio">Nenhum arquivo enviado ainda</div>';
        return;
    }
    el.innerHTML = lista.map(function(d) {
        var data = new Date(d.criado_em.replace(' ', 'T')).toLocaleDateString('pt-BR');
        return '<div class="cp-docs-item">' +
            '<a href="/' + d.caminho + '" target="_blank">📄 ' + (d.nome_original || 'arquivo.pdf') + '</a>' +
            '<span class="cp-docs-item-data">' + data + '</span>' +
            '<button class="cp-docs-item-excluir" onclick="excluirDocumento(' + d.id + ')" title="Excluir">✕</button>' +
        '</div>';
    }).join('');
}

function enviarDocumento(tipo, inputEl) {
    if (!inputEl.files || !inputEl.files[0]) return;
    var fd = new FormData();
    fd.append('cliente',  _docsGrupo.cliente);
    fd.append('agencia',  _docsGrupo.agencia);
    fd.append('campanha', _docsGrupo.campanha);
    fd.append('inicio',   _docsGrupo.inicio);
    fd.append('fim',      _docsGrupo.fim);
    fd.append('tipo',     tipo);
    fd.append('arquivo',  inputEl.files[0]);

    fetch('/gestor/campanhas/documentos/upload', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            inputEl.value = '';
            if (!resp.ok) {
                mostrarToast('Erro ao enviar arquivo (' + (resp.erro || 'desconhecido') + ')', 'err');
                return;
            }
            mostrarToast('Documento enviado com sucesso!');
            location.reload();
        })
        .catch(function() {
            mostrarToast('Erro de conexão ao enviar arquivo', 'err');
        });
}

function excluirDocumento(docId) {
    if (!confirm('Excluir este documento?')) return;
    var fd = new FormData();
    fd.append('action', 'excluir');
    fd.append('doc_id', docId);

    fetch('/gestor/campanhas/documentos/upload', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            if (!resp.ok) {
                mostrarToast('Erro ao excluir (' + (resp.erro || 'desconhecido') + ')', 'err');
                return;
            }
            mostrarToast('Documento excluído.');
            location.reload();
        })
        .catch(function() {
            mostrarToast('Erro de conexão ao excluir', 'err');
        });
}

function salvarEdicao() {
    if (!_modalCard) return;
    var dados       = JSON.parse(_modalCard.dataset.campanha || '{}');
    var cliente     = document.getElementById('cpModalCliente').value.trim();
    var agencia     = document.getElementById('cpModalAgencia').value.trim();
    var nomeProjeto = document.getElementById('cpModalNomeProjeto').value.trim();
    var nome        = document.getElementById('cpModalNome').value.trim();
    var inicio      = document.getElementById('cpModalInicio').value;
    var fim         = document.getElementById('cpModalFim').value;

    if (!cliente) { alert('Informe o nome do cliente.'); return; }

    var btn = document.getElementById('cpBtnSalvar');
    btn.disabled = true;
    btn.textContent = '⏳ Salvando...';

    // Envia um request por ponto do grupo
    var promises;
    try {
        promises = dados.pontoIds.map(function(pontoId, i) {
            return fetch('/gestor/campanhas/salvar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    ponto_id:    pontoId,
                    campanha_id: dados.campIds[i] || 0,
                    cliente:     cliente,
                    agencia:     agencia,
                    campanha:    nome,
                    nome:        nomeProjeto,
                    situacao:    dados.situacao,
                    inicio:      inicio || null,
                    fim:         fim    || null,
                })
            }).then(function(r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            });
        });
    } catch(e) {
        console.error('salvarEdicao: erro ao montar requests', e);
        btn.disabled = false;
        btn.textContent = '💾 Salvar alterações';
        mostrarToast('❌ Erro interno: ' + e.message, 'err');
        return;
    }

    Promise.all(promises).then(function(results) {
        var erros = results.filter(function(r) { return r.erro; });
        if (erros.length > 0) {
            btn.disabled = false;
            btn.textContent = '💾 Salvar alterações';
            mostrarToast('❌ Erro ao salvar: ' + (erros[0].msg || erros[0].erro || 'desconhecido'), 'err');
            return;
        }
        fecharModal();
        mostrarToast('✅ Campanha atualizada com sucesso!', 'ok');
        setTimeout(function() { location.reload(); }, 1200);
    }).catch(function(e) {
        console.error('salvarEdicao: erro na requisição', e);
        btn.disabled = false;
        btn.textContent = '💾 Salvar alterações';
        mostrarToast('❌ Erro de comunicação: ' + (e.message || 'verifique o console'), 'err');
    });
}

// ── Encerrar grupo de campanhas ───────────────────────────────
function encerrarGrupo(card, btn) {
    var dados = JSON.parse(card.dataset.campanha || '{}');
    var nomes = dados.pontoIds.length;
    var cli   = dados.cliente;
    if (!confirm('Encerrar a campanha de ' + cli + ' (' + nomes + ' ponto' + (nomes > 1 ? 's' : '') + ')?\n\nOs pontos voltarão a ficar Disponíveis.')) return;

    btn.disabled = true;
    btn.textContent = '⏳';

    var promises = dados.pontoIds.map(function(pontoId) {
        return fetch('/gestor/campanhas/encerrar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ponto_id: pontoId })
        }).then(function(r) { return r.json(); });
    });

    Promise.all(promises).then(function(results) {
        var erros = results.filter(function(r) { return r.erro; });
        if (erros.length > 0) {
            btn.disabled = false;
            btn.textContent = '🔒 Encerrar';
            mostrarToast('❌ Erro ao encerrar: ' + (erros[0].erro || 'desconhecido'), 'err');
            return;
        }
        // Anima o card para "encerrada"
        card.classList.add('encerrada');
        card.querySelector('.cp-acoes').innerHTML = '<span style="font-size:0.68rem;color:#6c757d;font-weight:700">Encerrada</span>';
        mostrarToast('✅ Campanha de ' + cli + ' encerrada. ' + nomes + ' ponto' + (nomes > 1 ? 's' : '') + ' liberado' + (nomes > 1 ? 's' : '') + '!', 'ok');
    }).catch(function() {
        btn.disabled = false;
        btn.textContent = '🔒 Encerrar';
        mostrarToast('❌ Erro de comunicação', 'err');
    });
}

// ── Toast ─────────────────────────────────────────────────────
function mostrarToast(msg, tipo) {
    var t = document.getElementById('cpToast');
    t.textContent = msg;
    t.style.background = tipo === 'err' ? '#dc3545' : '#1a9059';
    t.style.transform  = 'translate(-50%,-50%) scale(1)';
    t.style.opacity    = '1';
    clearTimeout(t._tmr);
    t._tmr = setTimeout(function() {
        t.style.transform = 'translate(-50%,-50%) scale(0.9)';
        t.style.opacity   = '0';
    }, 3500);
}

// ── Fechar modal com ESC ou clique fora ───────────────────────
document.getElementById('cpModalOverlay').addEventListener('click', function(e) {
    if (e.target === this) fecharModal();
});
document.getElementById('cpRenovarOverlay').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('aberto');
});
document.getElementById('cpDocsOverlay').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('aberto');
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharModal();
        document.getElementById('cpRenovarOverlay').classList.remove('aberto');
        document.getElementById('cpDocsOverlay').classList.remove('aberto');
    }
});

// ── Renovar campanha ──────────────────────────────────────────
var _renovarCard = null;

function abrirRenovacao(card) {
    var dados;
    try { dados = JSON.parse(card.dataset.campanha || '{}'); } catch(e) {
        alert('Erro ao abrir renovação. Recarregue a página.'); return;
    }
    if (!dados || !Array.isArray(dados.pontoIds)) { alert('Dados inválidos.'); return; }
    _renovarCard = card;

    document.getElementById('cpRenovarCliente').textContent =
        (dados.nome_projeto ? dados.nome_projeto + ' — ' : '') + (dados.cliente || '—');
    document.getElementById('cpRenovarSub').textContent =
        dados.campIds.length + ' ponto' + (dados.campIds.length > 1 ? 's' : '') +
        (dados.nome && dados.nome !== '—' ? ' · ' + dados.nome : '');
    document.getElementById('cpRenovarInicio').value = '';
    document.getElementById('cpRenovarFim').value    = '';
    document.getElementById('cpBtnRenovar').disabled = false;
    document.getElementById('cpBtnRenovar').textContent = '🔄 Renovar contrato';
    document.getElementById('cpRenovarOverlay').classList.add('aberto');
}

function salvarRenovacao() {
    if (!_renovarCard) return;
    var dados;
    try { dados = JSON.parse(_renovarCard.dataset.campanha || '{}'); } catch(e) {
        mostrarToast('❌ Erro interno.', 'err'); return;
    }
    var inicio = document.getElementById('cpRenovarInicio').value;
    var fim    = document.getElementById('cpRenovarFim').value;
    if (!fim) { alert('Informe a data de fim do novo contrato.'); return; }

    var btn = document.getElementById('cpBtnRenovar');
    btn.disabled = true;
    btn.textContent = '⏳ Renovando...';

    var promises;
    try {
        promises = dados.pontoIds.map(function(pontoId, i) {
            return fetch('/gestor/campanhas/renovar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    campanha_id: dados.campIds ? (dados.campIds[i] || 0) : 0,
                    ponto_id:    pontoId,
                    inicio:      inicio || null,
                    fim:         fim,
                })
            }).then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); });
        });
    } catch(e) {
        btn.disabled = false;
        btn.textContent = '🔄 Renovar contrato';
        mostrarToast('❌ Erro: ' + e.message, 'err');
        return;
    }

    Promise.all(promises).then(function(results) {
        var erros = results.filter(function(r) { return r.erro; });
        if (erros.length > 0) {
            btn.disabled = false;
            btn.textContent = '🔄 Renovar contrato';
            mostrarToast('❌ Erro: ' + (erros[0].msg || erros[0].erro), 'err');
            return;
        }
        document.getElementById('cpRenovarOverlay').classList.remove('aberto');
        mostrarToast('✅ Contrato renovado com sucesso!', 'ok');
        setTimeout(function() { location.reload(); }, 1200);
    }).catch(function(e) {
        btn.disabled = false;
        btn.textContent = '🔄 Renovar contrato';
        mostrarToast('❌ Erro de comunicação: ' + (e.message || ''), 'err');
    });
}

// ── Liberar todos os vencidos ─────────────────────────────────
function liberarTodosVencidos(btn) {
    if (!confirm('Liberar todos os contratos vencidos?\nOs pontos voltarão a ficar Disponíveis.')) return;
    btn.disabled = true;
    btn.textContent = '⏳ Processando...';
    fetch('/gestor/campanhas/processar-vencidos', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({})
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) {
            mostrarToast('✅ ' + data.processados + ' contrato' + (data.processados !== 1 ? 's' : '') + ' liberado' + (data.processados !== 1 ? 's' : '') + '!', 'ok');
            setTimeout(function() { location.reload(); }, 1200);
        } else {
            btn.disabled = false;
            btn.textContent = '⚡ Liberar todos';
            mostrarToast('❌ Erro: ' + (data.msg || data.erro || 'desconhecido'), 'err');
        }
    })
    .catch(function() {
        btn.disabled = false;
        btn.textContent = '⚡ Liberar todos';
        mostrarToast('❌ Erro de comunicação', 'err');
    });
}

// ── Auto-abrir Editar/Renovar ao chegar via link do relatório ──
// A tela não carrega a lista de cara, então aqui disparamos uma busca
// (por cliente) e, quando os cards chegarem, localizamos o alvo exato.
(function() {
    var qs = new URLSearchParams(location.search);
    var acao = qs.get('acao');
    if (acao !== 'editar' && acao !== 'renovar') return;

    var norm = function(s) { return (s || '').toString().trim().toLowerCase(); };
    var alvo = {
        cliente:  norm(qs.get('cliente')),
        agencia:  norm(qs.get('agencia')),
        nome:     norm(qs.get('campanha')),
        situacao: norm(qs.get('situacao')),
        inicio:   qs.get('inicio') || '',
        fim:      qs.get('fim') || '',
    };

    function acharEAbrir() {
        var cards = document.querySelectorAll('#cpGrid .cp-card');
        for (var i = 0; i < cards.length; i++) {
            var d;
            try { d = JSON.parse(cards[i].dataset.campanha || '{}'); } catch(e) { continue; }
            if (norm(d.cliente) === alvo.cliente && norm(d.agencia) === alvo.agencia &&
                norm(d.nome) === alvo.nome && norm(d.situacao) === alvo.situacao &&
                (d.inicio || '') === alvo.inicio && (d.fim || '') === alvo.fim) {
                cards[i].scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (acao === 'editar') abrirEdicao(cards[i]);
                else abrirRenovacao(cards[i]);
                return true;
            }
        }
        return false;
    }

    // Filtra por cliente só internamente (pra estreitar a busca) — o select
    // visível agora filtra por campanha, não faz sentido refletir aqui.
    filtros.cliente = alvo.cliente;
    filtros.situacao = '';
    buscarCampanhas().then(function() {
        if (acharEAbrir()) return;
        // Não achou entre as ativas (pode estar encerrada): tenta de novo
        filtros.situacao = 'Encerradas';
        marcarRadioSit('Encerradas');
        buscarCampanhas().then(acharEAbrir);
    });
})();
</script>

</body>
</html>
