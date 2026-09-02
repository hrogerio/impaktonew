<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

$paginaAtual = 'relatorios';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../Controllers/RelatorioController.php';

$controller = new RelatorioController();

$ocupacao  = $controller->dadosOcupacao();
$contratos = $controller->dadosContratos();
$clientes  = $controller->dadosClientes();
$documentosPorGrupo = $controller->documentosPorGrupo();

// ============================================================
// Helpers de apresentação
// ============================================================
function pct($valor, $total) {
    return $total > 0 ? round(($valor / $total) * 100, 1) : 0;
}


function mesLabel($mesStr) {
    $meses = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
              '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];
    list($ano, $m) = explode('-', $mesStr);
    return (isset($meses[$m]) ? $meses[$m] : $m) . '/' . substr($ano, 2);
}

function fmtData($data) {
    if (!$data || $data === '0000-00-00') return '-';
    try { return (new DateTime($data))->format('d/m/Y'); }
    catch (Exception $e) { return '-'; }
}

/** Conta quantos contratos da lista não têm nenhum documento financeiro enviado */
function contarSemDocumentos(array $lista, array $documentosPorGrupo): int {
    return count(array_filter($lista, function($c) use ($documentosPorGrupo) {
        $chave = md5(trim($c['cliente_raw'] ?? ($c['cliente'] ?? '')) . '|' . trim($c['agencia'] ?? '') . '|' . trim($c['motivo'] ?? '') . '|' . ($c['inicio_doc'] ?? '') . '|' . ($c['fim_doc'] ?? ''));
        return empty($documentosPorGrupo[$chave] ?? []);
    }));
}

/** Conta contratos cadastrados no sistema nos últimos $dias dias — regra provisória pro financeiro identificar novidades */
function contarNovosContratos(array $lista, int $dias = 30): int {
    $limite = new DateTime("-{$dias} days");
    return count(array_filter($lista, function($c) use ($limite) {
        if (empty($c['criado_em'])) return false;
        try { return new DateTime($c['criado_em']) >= $limite; }
        catch (Exception $e) { return false; }
    }));
}

function fmtDuracao($dias) {
    if ($dias === null) return '-';
    $dias = (int)$dias;
    if ($dias < 30) {
        return $dias . ' dias';
    }
    $meses = round($dias / 30);
    return $meses . ' meses';
}

/** Tabela padrão de campanhas (Cliente/Campanha/Agência/Início/Fim/Duração/Pontos), reutilizada em Contratos Ativos e Vencendo por mês */
function tabelaCampanhas(array $lista) {
    global $documentosPorGrupo;
    if (empty($lista)) {
        echo '<div class="empty-state"><p>Nenhum contrato encontrado.</p></div>';
        return;
    }
    ?>
    <div class="table-container">
        <table class="rel-table">
            <thead>
                <tr><th>Cliente</th><th>Campanha</th><th>Agência</th><th>Início</th><th>Fim</th><th>Duração</th><th style="text-align:center">Pontos</th><th>Docs</th></tr>
            </thead>
            <tbody>
                <?php foreach ($lista as $c):
                    $docChave = md5(trim($c['cliente_raw'] ?? '') . '|' . trim($c['agencia'] ?? '') . '|' . trim($c['motivo'] ?? '') . '|' . ($c['inicio_doc'] ?? '') . '|' . ($c['fim_doc'] ?? ''));
                    $docsGrupo = $documentosPorGrupo[$docChave] ?? [];
                    $dadosContrato = json_encode([
                        'cliente'         => $c['cliente'] ?? '-',
                        'cliente_raw'     => $c['cliente_raw'] ?? ($c['cliente'] ?? ''),
                        'agencia'         => $c['agencia'] ?? '',
                        'campanha'        => $c['campanha'] ?? '-',
                        'motivo'          => $c['motivo'] ?? '-',
                        'situacao'        => $c['situacao'] ?? 'Ocupado',
                        'inicio_contrato' => $c['inicio_contrato'] ?? null,
                        'fim_contrato'    => $c['fim_contrato'] ?? null,
                        'inicio_fmt'      => fmtData($c['inicio_contrato'] ?? null),
                        'fim_fmt'         => fmtData($c['fim_contrato'] ?? null),
                        'pontos'          => $c['pontos'] ?? [],
                        'documentos'      => $docsGrupo,
                    ], JSON_HEX_APOS | JSON_HEX_QUOT);

                    // Nem toda campanha usa os 3 tipos (normalmente é só Contrato OU P.I., e P.P. é opcional) —
                    // então o status lista os documentos já enviados, em ordem de prioridade (CT > P.P. > P.I.),
                    // não uma checklist exigindo os 3.
                    $tiposPresentes = array_unique(array_column($docsGrupo, 'tipo'));
                    $labelsTipo = ['CONTRATO' => 'CT', 'PP' => 'P.P.', 'PI' => 'P.I.'];
                    $tiposEmOrdem = array_values(array_filter(['CONTRATO', 'PP', 'PI'], fn($t) => in_array($t, $tiposPresentes, true)));

                    // Contrato "novo": cadastrado no sistema nos últimos 30 dias (regra provisória, ajustável)
                    $ehNovo = false;
                    if (!empty($c['criado_em'])) {
                        try { $ehNovo = (new DateTime())->diff(new DateTime($c['criado_em']))->days <= 30; }
                        catch (Exception $e) { $ehNovo = false; }
                    }
                ?>
                <tr class="rel-row-clicavel" onclick='abrirDetalhesContrato(<?= $dadosContrato ?>)' title="Ver detalhes do contrato">
                    <td>
                        <strong><?= htmlspecialchars($c['cliente'] ?? '-') ?></strong>
                        <?php if ($ehNovo): ?>
                        <span class="tag-novo-contrato" title="Cadastrado nos últimos 30 dias">🆕 Novo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($c['campanha'] ?? '-') ?>
                        <?php if (!empty($c['motivo']) && $c['motivo'] !== '-' && $c['motivo'] !== ($c['campanha'] ?? '')): ?>
                        <div style="color:var(--color-text-muted);font-size:0.75rem;"><?= htmlspecialchars($c['motivo']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--color-text-muted)">
                        <?= htmlspecialchars($c['agencia'] ?? '-') ?>
                        <?php if (!empty($c['contato']) && $c['contato'] !== '-'): ?>
                        <div style="color:var(--color-text-muted);font-size:0.75rem;"><?= htmlspecialchars($c['contato']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= fmtData($c['inicio_contrato']) ?></td>
                    <td><?= fmtData($c['fim_contrato']) ?></td>
                    <td><?= fmtDuracao($c['duracao_dias']) ?></td>
                    <td style="text-align:center"><strong style="color:var(--color-accent-primary)"><?= $c['qtd_pontos'] ?></strong></td>
                    <td>
                        <?php if (!empty($tiposEmOrdem)): ?>
                        <span class="docs-status docs-ok" title="Documentos enviados: <?= htmlspecialchars(implode(', ', array_map(fn($t) => $labelsTipo[$t], $tiposEmOrdem))) ?>">✅ <?= htmlspecialchars(implode(', ', array_map(fn($t) => $labelsTipo[$t], $tiposEmOrdem))) ?></span>
                        <?php else: ?>
                        <span class="docs-status docs-falta" title="Nenhum documento enviado ainda">⚠️ Sem doc</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios · Impakto</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css?v=2">
    <link rel="stylesheet" href="/public/assets/css/relatorios.css">
    <style>
        .docs-status { display:inline-block; padding:2px 8px; border-radius:8px; font-size:0.68rem; font-weight:700; white-space:nowrap; }
        .tag-novo-contrato { display:inline-block; margin-left:0.4rem; padding:1px 7px; border-radius:8px; background:#dbeafe; color:#1d4ed8; font-size:0.65rem; font-weight:800; white-space:nowrap; vertical-align:middle; }
        .docs-status.docs-ok    { background:#dcfce7; color:#166534; }
        .docs-status.docs-falta { background:#fef3c7; color:#92400e; }

        .rel-row-clicavel { cursor:pointer; }
        .rel-row-clicavel:hover { background:#f9fafb; }
        .rel-row-clicavel:hover td { color:var(--color-accent-primary) !important; font-weight:700; }
        .rel-row-clicavel:hover td:first-child strong { text-decoration:underline; }

        /* ── Acordeão de Contratos Vencendo (preto e branco, pra diferenciar da tabela geral) ── */
        .mes-acordeao { border:1px solid #000; border-radius:10px; margin-bottom:0.75rem; overflow:hidden; }
        .mes-acordeao-header {
            display:flex; align-items:center; justify-content:space-between; gap:0.5rem;
            padding:0.7rem 1rem; cursor:pointer; user-select:none;
            background:#fff; color:#000;
            transition:background 0.15s, color 0.15s;
        }
        .mes-acordeao-header:hover { background:#f2f2f2; }
        .mes-acordeao.aberto .mes-acordeao-header { background:#000; color:#fff; }
        .mes-acordeao-titulo { display:flex; align-items:center; gap:0.6rem; font-weight:700; font-size:0.92rem; }
        .mes-acordeao-count {
            background:#000; color:#fff;
            border-radius:999px; padding:0.1rem 0.55rem; font-size:0.72rem; font-weight:700;
        }
        .mes-acordeao.aberto .mes-acordeao-count { background:#fff; color:#000; }
        .mes-acordeao-body { display:none; padding:0.75rem 1rem 1rem; }
        .mes-acordeao.aberto .mes-acordeao-body { display:block; }
        .mes-acordeao-body .rel-table th { background:#6b7280; color:#fff; }

        .cp-modal-overlay {
            display:flex; position:fixed; inset:0;
            background:rgba(0,0,0,0.45); z-index:1000;
            align-items:center; justify-content:center;
            opacity:0; visibility:hidden; pointer-events:none;
            transition:opacity var(--duration-base,0.2s) var(--ease,ease), visibility var(--duration-base,0.2s);
        }
        .cp-modal-overlay.aberto { opacity:1; visibility:visible; pointer-events:auto; }
        .cp-card {
            background:#fff; border:1px solid var(--color-border); border-radius:12px;
            overflow:hidden; display:flex; flex-direction:column;
        }
        .cp-modal {
            background:#fff; border-radius:14px; padding:1.5rem;
            width:480px; max-width:95vw; max-height:90vh; overflow-y:auto;
            box-shadow:0 20px 60px rgba(0,0,0,0.3);
            transform:scale(0.95) translateY(8px);
            transition:transform var(--duration-base,0.2s) var(--ease,ease);
        }
        .cp-modal-overlay.aberto .cp-modal { transform:scale(1) translateY(0); }
        .cp-card.cp-modal {
            padding:0; position:relative;
            width:580px;
        }
        .cp-card-faixa { height:5px; }
        .rel-modal-fechar {
            position:absolute; top:1rem; right:1rem; z-index:1;
            width:32px; height:32px; border-radius:50%;
            background:#fff; border:2px solid #dc3545; color:#dc3545;
            font-size:0.95rem; font-weight:800; line-height:1;
            cursor:pointer; display:flex; align-items:center; justify-content:center;
            transition:all 0.15s;
        }
        .rel-modal-fechar:hover { background:#dc3545; color:#fff; transform:rotate(90deg); }
        .cp-card-head { padding:1.5rem 3.25rem 1.15rem 1.75rem; border-bottom:1px solid #f0f2f5; }
        .cp-card-top { display:flex; align-items:center; gap:0.6rem; margin-bottom:0.6rem; flex-wrap:wrap; }
        .sit-badge {
            display:inline-block; padding:4px 12px; border-radius:12px;
            font-size:0.68rem; font-weight:800; text-transform:uppercase;
            letter-spacing:0.4px; color:white; white-space:nowrap; flex-shrink:0;
        }
        .cp-card-nome { font-size:0.9rem; font-weight:600; color:var(--color-text-muted); flex:1; min-width:0; }
        .cp-card-cliente { font-size:1.4rem; font-weight:800; color:var(--color-text-dark); line-height:1.25; }
        .cp-card-agencia { font-size:0.85rem; color:var(--color-text-muted); font-weight:600; margin-top:0.15rem; }
        .cp-card-meta { display:flex; align-items:center; gap:0.75rem; margin-top:0.75rem; flex-wrap:wrap; }
        .cp-card-periodo { font-size:0.85rem; color:var(--color-text-muted); font-weight:600; }

        .cp-card-paineis { max-height:340px; overflow-y:auto; }
        .cp-painel-row { display:flex; align-items:center; gap:0.9rem; padding:0.85rem 1.75rem; border-bottom:1px solid #f5f5f7; }
        .cp-painel-row:last-child { border-bottom:none; }
        .cp-painel-num { font-weight:800; color:var(--color-accent-primary); font-size:0.95rem; min-width:38px; flex-shrink:0; }
        .cp-painel-end { flex:1; min-width:0; }
        .cp-painel-log { font-size:0.9rem; font-weight:600; color:var(--color-text-dark); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .cp-painel-cid { font-size:0.78rem; color:var(--color-text-muted); margin-top:2px; }
        .cp-painel-link { font-size:0.9rem; font-weight:700; color:var(--color-accent-primary); text-decoration:none; flex-shrink:0; }
        .cp-painel-link:hover { text-decoration:underline; }

        .cp-card-footer {
            padding:0.9rem 1.75rem; background:#fafbfc;
            border-top:1px solid #f0f2f5; font-size:0.82rem; font-weight:700;
            color:var(--color-text-muted); display:flex; align-items:center;
            justify-content:space-between; gap:0.75rem; flex-wrap:wrap;
        }
        .cp-acoes { display:flex; gap:0.5rem; flex-wrap:wrap; }
        .cp-btn {
            padding:6px 14px; border-radius:7px; font-size:0.8rem; font-weight:700;
            cursor:pointer; border:none; font-family:'Montserrat',sans-serif;
            transition:all 0.15s; white-space:nowrap; text-decoration:none;
            display:inline-flex; align-items:center; gap:0.3rem;
        }
        .cp-btn-checking { background:#fdf4ff; color:#7e22ce; border:1px solid #d8b4fe; }
        .cp-btn-checking:hover { background:#f3e8ff; }
        .cp-btn-docs { background:#eefdf6; color:#0f766e; border:1px solid #99f6e4; }
        .cp-btn-docs:hover { background:#ccfbf1; }
        .cp-btn-docs.desabilitado { background:#f3f4f6; color:#6b7280; border:1px solid #e5e7eb; cursor:not-allowed; pointer-events:none; }
        .cp-btn-editar { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
        .cp-btn-editar:hover { background:#dbeafe; }
        .cp-btn-renovar { background:#f0fdf4; color:#166534; border:1px solid #86efac; }
        .cp-btn-renovar:hover { background:#dcfce7; }
        .cp-btn-cancelar {
            padding:0.55rem 1rem; background:none; color:#666;
            border:1px solid var(--color-border); border-radius:8px;
            font-family:'Montserrat',sans-serif; font-size:0.85rem; cursor:pointer;
        }

        .cp-modal-title { font-size:1rem; font-weight:800; color:var(--color-text-dark); margin-bottom:0.25rem; }
        .cp-modal-sub   { font-size:0.78rem; color:var(--color-text-muted); margin-bottom:1.25rem; }
        .cp-modal-divider { height:1px; background:var(--color-border); margin:1rem 0; }
        .cp-modal-actions { display:flex; gap:0.75rem; justify-content:flex-end; margin-top:1.25rem; }

        .cli-modal-field { margin-bottom:0.75rem; }
        .cli-modal-label { display:block; font-size:0.78rem; font-weight:700; color:var(--color-text-muted); margin-bottom:0.3rem; }
        .cli-modal-input { width:100%; padding:0.55rem 0.7rem; border:1.5px solid var(--color-border); border-radius:8px; font-family:'Montserrat',sans-serif; font-size:0.88rem; box-sizing:border-box; }
        .cli-modal-input:focus { outline:none; border-color:var(--color-accent-primary); }
        .cli-modal-logo-field { margin-bottom:1rem; padding-bottom:0.75rem; border-bottom:1px solid #f0f2f5; }
        .cli-modal-file { font-size:0.8rem; max-width:100%; }
        .cli-modal-erro { color:#dc3545; font-size:0.8rem; margin-top:0.75rem; display:none; }
        .cp-btn-salvar {
            padding:0.55rem 1.1rem; background:var(--color-accent-primary); color:#fff;
            border:none; border-radius:8px; font-family:'Montserrat',sans-serif;
            font-size:0.85rem; font-weight:700; cursor:pointer;
        }
        .cp-btn-salvar:disabled { opacity:0.6; cursor:not-allowed; }

        .cli-row-editado { background:#fff8e1; box-shadow:inset 3px 0 0 var(--color-accent-primary); transition:background 0.4s ease; }
        .cli-row-editado:hover { background:#fdefc8; }
        .cli-badge-editado {
            display:inline-block; margin-left:0.4rem;
            color:var(--color-text-muted); font-style:italic;
            font-size:0.68rem; font-weight:500;
            vertical-align:middle;
        }

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
    </style>
</head>
<body>
<?php include __DIR__ . '/../partials/env_banner.php'; ?>


<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="container" style="padding-top:0.75rem; padding-bottom:2rem;">

    <div class="welcome" style="margin-bottom:1rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.75rem;">
        <div>
            <h2>📊 Relatórios</h2>
            <p>Ocupação, contratos, clientes e histórico — visão comercial para apresentação à diretoria.</p>
        </div>
        <a class="btn-export btn-pdf-mensal" href="/gestor/relatorios/pdf" target="_blank">
            📄 Gerar Relatório Mensal (PDF)
        </a>
    </div>

    <div class="tabs-nav" id="tabsNav">
        <button class="tab-btn active" onclick="switchTab('contratos',this)">📅 Contratos</button>
        <button class="tab-btn" onclick="switchTab('clientes',this)">🏢 Clientes</button>
        <button class="tab-btn" onclick="switchTab('agencias',this)">🏛️ Agências</button>
        <button class="tab-btn" onclick="switchTab('ocupacao',this)">🗺️ Ocupação</button>
    </div>

    <!-- ============================================================ -->
    <!-- ABA 1: OCUPAÇÃO                                               -->
    <!-- ============================================================ -->
    <div class="tab-content" id="tab-ocupacao">

        <div class="export-bar">
            <button class="btn-export btn-csv" onclick="exportCSV('tbl-regiao-hidden','ocupacao-regiao')">⬇ CSV</button>
        </div>

        <div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);">
            <div class="kpi-card kpi-total">
                <div class="kpi-icon">📍</div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= number_format($ocupacao['totais']['geral']) ?></div>
                    <div class="kpi-label">Total de Pontos</div>
                </div>
            </div>
            <div class="kpi-card kpi-ocup">
                <div class="kpi-icon">🔴</div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= number_format($ocupacao['totais']['ocupados']) ?></div>
                    <div class="kpi-label">Ocupados</div>
                    <div class="kpi-sub"><?= pct($ocupacao['totais']['ocupados'], $ocupacao['totais']['geral']) ?>% do total</div>
                </div>
            </div>
            <div class="kpi-card kpi-disp">
                <div class="kpi-icon">🟢</div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= number_format($ocupacao['totais']['disponiveis']) ?></div>
                    <div class="kpi-label">Disponíveis</div>
                    <div class="kpi-sub"><?= pct($ocupacao['totais']['disponiveis'], $ocupacao['totais']['geral']) ?>% do total</div>
                </div>
            </div>
        </div>

        <div class="bloco-section">
            <div class="bloco-label">Por Região</div>

            <?php if (empty($ocupacao['ocupacao_regiao'])): ?>
                <p style="color:var(--color-text-muted);font-size:0.85rem;">Nenhum dado encontrado.</p>
            <?php else: ?>
                <div class="bars-clean">
                    <?php foreach ($ocupacao['ocupacao_regiao'] as $r): ?>
                    <div class="bar-clean-row" title="<?= htmlspecialchars($r['regiao']) ?>: <?= $r['ocupados'] ?> ocupados, <?= $r['disponiveis'] ?> disponíveis">
                        <div class="bar-clean-label"><?= htmlspecialchars($r['regiao']) ?></div>
                        <div class="bar-clean-track">
                            <div class="bc-ocup" style="width:<?= pct($r['ocupados'],$r['total']) ?>%"></div>
                            <div class="bc-disp" style="width:<?= pct($r['disponiveis'],$r['total']) ?>%"></div>
                            <div class="bc-res"  style="width:<?= pct($r['reservados'],$r['total']) ?>%"></div>
                            <div class="bc-venc" style="width:<?= pct($r['vencidos'],$r['total']) ?>%"></div>
                        </div>
                        <div class="bar-clean-meta">
                            <span class="bar-clean-total"><?= $r['total'] ?></span>
                            <span class="bar-clean-pct"><?= pct($r['ocupados'],$r['total']) ?>% ocup.</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="bloco-section">
            <div class="bloco-label">Por Cidades <span class="bloco-count"><?= count($ocupacao['ocupacao_cidade']) ?></span></div>

            <?php if (empty($ocupacao['ocupacao_cidade'])): ?>
                <p style="color:var(--color-text-muted);font-size:0.85rem;margin-top:0.5rem;">Nenhuma cidade encontrada.</p>
            <?php else: ?>
            <div class="table-container" style="margin-top:0.75rem;">
                <table class="rel-table">
                    <thead>
                        <tr>
                            <th>Cidade</th>
                            <th style="text-align:right">Total</th>
                            <th style="text-align:right">Ocup.</th>
                            <th style="text-align:right">Disp.</th>
                            <th style="text-align:right">Res.</th>
                            <th style="text-align:right">Venc.</th>
                            <th>% Ocup.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ocupacao['ocupacao_cidade'] as $c): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['cidade']) ?></strong></td>
                            <td style="text-align:right;font-weight:700"><?= $c['total'] ?></td>
                            <td style="text-align:right;color:#e34c3e;font-weight:600"><?= $c['ocupados'] ?></td>
                            <td style="text-align:right;color:#27ae60;font-weight:600"><?= $c['disponiveis'] ?></td>
                            <td style="text-align:right;color:#f39c12;font-weight:600"><?= $c['reservados'] ?></td>
                            <td style="text-align:right;color:#8e44ad;font-weight:600"><?= $c['vencidos'] ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:5px;">
                                    <div style="flex:1;height:6px;background:#f0f0f0;border-radius:3px;overflow:hidden;min-width:40px;">
                                        <div style="width:<?= pct($c['ocupados'],$c['total']) ?>%;height:100%;background:#e34c3e;border-radius:3px;"></div>
                                    </div>
                                    <span style="font-size:0.72rem;font-weight:700;width:32px;text-align:right;color:var(--color-text-muted);"><?= pct($c['ocupados'],$c['total']) ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <table id="tbl-regiao-hidden" style="display:none">
            <thead><tr><th>Região</th><th>Total</th><th>Ocupados</th><th>Disponíveis</th><th>Reservados</th><th>Ctr. Vencidos</th><th>% Ocupação</th></tr></thead>
            <tbody>
                <?php foreach ($ocupacao['ocupacao_regiao'] as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['regiao']) ?></td>
                    <td><?= $r['total'] ?></td>
                    <td><?= $r['ocupados'] ?></td>
                    <td><?= $r['disponiveis'] ?></td>
                    <td><?= $r['reservados'] ?></td>
                    <td><?= $r['vencidos'] ?></td>
                    <td><?= pct($r['ocupados'],$r['total']) ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div><!-- /tab-ocupacao -->


    <!-- ============================================================ -->
    <!-- ABA 2: CONTRATOS & TEMPO DE CONTRATO                          -->
    <!-- ============================================================ -->
    <div class="tab-content active" id="tab-contratos">

        <div class="export-bar">
            <a class="btn-export btn-pdf" href="/gestor/relatorios/contratos/pdf" target="_blank">📄 PDF de Contratos</a>
        </div>

        <div class="kpi-grid" style="grid-template-columns:repeat(4,minmax(180px,220px)); margin-bottom:1.25rem;">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#eef6ff;">📄</div>
                <div class="kpi-body">
                    <div class="kpi-value" style="color:#3498db"><?= count($contratos['campanhas_ativas']) ?></div>
                    <div class="kpi-label">Contratos Ativos</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#dbeafe;">🆕</div>
                <div class="kpi-body">
                    <div class="kpi-value" style="color:#1d4ed8"><?= contarNovosContratos($contratos['campanhas_ativas'], 30) ?></div>
                    <div class="kpi-label">Novos (30 dias)</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#f9f0ff;">🔴</div>
                <div class="kpi-body">
                    <div class="kpi-value" style="color:#8e44ad"><?= count($contratos['vencidos_agrupado']) ?></div>
                    <div class="kpi-label">Já Vencidos</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#fff4e5;">⚠️</div>
                <div class="kpi-body">
                    <div class="kpi-value" style="color:#e67e22"><?= contarSemDocumentos($contratos['campanhas_ativas'], $documentosPorGrupo) ?></div>
                    <div class="kpi-label">Sem Documentos</div>
                </div>
            </div>
        </div>

        <?php if (!empty($contratos['ativos_por_mes'])): ?>
        <div class="panel" style="margin-bottom:1.25rem;">
            <div class="panel-title">Histórico Anual — Contratos Ativos por Mês</div>
            <?php $maxAtivos = max(1, max($contratos['ativos_por_mes'])); ?>
            <div class="timeline-bars">
                <?php foreach ($contratos['ativos_por_mes'] as $mes => $qtd): ?>
                <div class="tl-col">
                    <div class="tl-bar-count"><?= $qtd ?></div>
                    <div class="tl-bar" style="height:<?= max(4,round(($qtd/$maxAtivos)*60)) ?>px;"></div>
                    <div class="tl-label"><?= mesLabel($mes) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="section-title" style="justify-content:space-between;flex-wrap:wrap;gap:0.6rem;">
            <span>📋 Contratos Ativos por Cliente</span>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
                <input type="text" id="buscaContratosAtivos" oninput="aplicarFiltrosContratosAtivos()"
                       placeholder="🔎 Buscar cliente, campanha, agência ou contato..."
                       style="font-size:0.78rem;color:var(--color-text-dark);border:1px solid var(--color-border);border-radius:6px;padding:0.35rem 0.6rem;font-family:'Montserrat',sans-serif;min-width:260px;">
                <select id="filtroDocs" onchange="aplicarFiltrosContratosAtivos()" style="font-size:0.78rem;font-weight:700;color:var(--color-text-dark);border:1px solid var(--color-border);border-radius:6px;padding:0.3rem 0.5rem;font-family:'Montserrat',sans-serif;">
                    <option value="todos">📎 Documentos: Todos</option>
                    <option value="com">✅ Com documentos</option>
                    <option value="sem">⚠️ Sem documentos</option>
                </select>
            </div>
        </div>
        <div id="tabelaContratosAtivosWrap">
            <?php tabelaCampanhas($contratos['campanhas_ativas']); ?>
        </div>

        <div class="section-title" style="margin-top:1.5rem">🔴 Contratos Vencidos (<?= count($contratos['vencidos_agrupado']) ?>)</div>
        <?php tabelaCampanhas($contratos['vencidos_agrupado']); ?>

        <!-- ===== Contratos Vencendo ===== -->
        <div class="section-title" style="margin-top:1.5rem">📅 Contratos Vencendo</div>
        <div class="panel-title" style="margin-bottom:0.6rem;">Distribuição por mês (Jul a Dez)</div>

        <?php if (empty($contratos['vencendo_agrupado'])): ?>
            <div class="empty-state"><div class="empty-state-icon">🎉</div><p>Nenhum contrato vencendo este ano.</p></div>
        <?php else: ?>
            <?php $mesIndex = 0; ?>
            <?php foreach ($contratos['vencendo_agrupado'] as $mes => $campanhas):
                $mesId = 'mesAcc' . $mesIndex;
                $mesIndex++;
            ?>
            <div class="mes-acordeao" id="<?= $mesId ?>">
                <div class="mes-acordeao-header" onclick="document.getElementById('<?= $mesId ?>').classList.toggle('aberto')">
                    <span class="mes-acordeao-titulo"><?= mesLabel($mes) ?> <span class="mes-acordeao-count"><?= count($campanhas) ?></span></span>
                </div>
                <div class="mes-acordeao-body">
                    <?php tabelaCampanhas($campanhas); ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div><!-- /tab-contratos -->


    <!-- ============================================================ -->
    <!-- ABA 3: CLIENTES                                              -->
    <!-- ============================================================ -->
    <div class="tab-content" id="tab-clientes">

        <div class="export-bar">
            <a class="btn-export btn-pdf" href="/gestor/clientes/novo">➕ Novo Cliente</a>
            <a class="btn-export btn-pdf" href="/gestor/relatorios/clientes/pdf" target="_blank">📄 PDF</a>
        </div>

        <div class="kpi-grid" style="grid-template-columns:minmax(180px,220px); margin-bottom:1.25rem;">
            <div class="kpi-card kpi-total">
                <div class="kpi-icon">🏢</div>
                <div class="kpi-body">
                    <div class="kpi-value" id="cliKpiTotal"><?= count($clientes['clientes']) ?></div>
                    <div class="kpi-label">Clientes</div>
                </div>
            </div>
        </div>

        <div class="section-title">📋 Todos os Clientes (<span id="cliContagemTitulo"><?= count($clientes['clientes']) ?></span>)</div>
        <?php if (empty($clientes['clientes'])): ?>
            <div class="empty-state"><div class="empty-state-icon">🏢</div><p>Nenhum cliente encontrado.</p></div>
        <?php else: ?>
        <div class="table-container">
            <table class="rel-table" id="tbl-clientes">
                <thead>
                    <tr><th style="width:50px;"></th><th>Razão Social</th><th>Nome Fantasia</th><th>CNPJ</th><th>E-mail</th><th>Ações</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($clientes['clientes'] as $cl): ?>
                    <tr id="cli-row-<?= (int)$cl['id'] ?>">
                        <td class="cli-cel-logo">
                            <?php if (!empty($cl['logo'])): ?>
                            <img src="/<?= htmlspecialchars($cl['logo']) ?>" alt="" style="width:36px;height:36px;border-radius:6px;object-fit:contain;background:#f6f7fb;border:1px solid var(--color-border);">
                            <?php else: ?>
                            <div style="width:36px;height:36px;border-radius:6px;background:#f6f7fb;border:1px solid var(--color-border);display:flex;align-items:center;justify-content:center;font-size:0.9rem;color:var(--color-text-muted);">🏢</div>
                            <?php endif; ?>
                        </td>
                        <td class="cli-cel-razao">
                            <strong>
                                <?php if (!empty($cl['id'])): ?>
                                <a href="/gestor/clientes/ficha?id=<?= (int)$cl['id'] ?>" style="color:inherit;text-decoration:none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'"><?= htmlspecialchars($cl['razao_social']) ?></a>
                                <?php else: ?>
                                <?= htmlspecialchars($cl['razao_social']) ?>
                                <?php endif; ?>
                            </strong>
                        </td>
                        <td class="cli-cel-fantasia"><?= htmlspecialchars($cl['nome_fantasia'] ?: '-') ?></td>
                        <td class="cli-cel-cnpj" style="color:var(--color-text-muted)"><?= htmlspecialchars($cl['cnpj'] ?: '-') ?></td>
                        <td class="cli-cel-email" style="color:var(--color-text-muted)"><?= htmlspecialchars($cl['email'] ?: '-') ?></td>
                        <td style="white-space:nowrap;">
                            <button type="button" title="Editar" onclick='abrirEdicaoCliente(<?= json_encode([
                                "id" => (int)$cl["id"],
                                "razao_social" => $cl["razao_social"],
                                "nome_fantasia" => $cl["nome_fantasia"],
                                "cnpj" => $cl["cnpj"],
                                "email" => $cl["email"],
                                "logo" => $cl["logo"],
                            ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' style="background:none;border:none;cursor:pointer;font-size:1rem;padding:0;margin-right:0.5rem;">✏️</button>
                            <button type="button" title="Excluir" onclick='abrirExclusaoCliente(<?= (int)$cl['id'] ?>, <?= json_encode($cl['razao_social'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' style="background:none;border:none;cursor:pointer;font-size:1rem;padding:0;">🗑️</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div><!-- /tab-clientes -->

    <!-- ============================================================ -->
    <!-- ABA 4: AGÊNCIAS                                              -->
    <!-- ============================================================ -->
    <div class="tab-content" id="tab-agencias">

        <div class="export-bar">
            <a class="btn-export btn-pdf" href="/gestor/agencias">🏛️ Gerenciar Agências</a>
            <button class="btn-export btn-csv" onclick="exportCSV('tbl-agencias','resumo-agencias')">⬇ CSV</button>
        </div>

        <div class="kpi-grid" style="grid-template-columns:minmax(180px,220px); margin-bottom:1.25rem;">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#eef6ff;">🏛️</div>
                <div class="kpi-body">
                    <div class="kpi-value" style="color:#3498db"><?= count($clientes['agencias_cadastro']) ?></div>
                    <div class="kpi-label">Agências</div>
                </div>
            </div>
        </div>

        <div class="section-title">🏛️ Todas as Agências (<?= count($clientes['agencias_cadastro']) ?>)</div>
        <?php if (empty($clientes['agencias_cadastro'])): ?>
            <div class="empty-state"><p>Nenhuma agência cadastrada. <a href="/gestor/agencias">Cadastre a primeira</a>.</p></div>
        <?php else: ?>
        <div class="table-container">
            <table class="rel-table" id="tbl-agencias">
                <thead><tr><th style="width:50px;"></th><th>Nome</th><th>Endereço</th><th>Telefone</th><th>Diretor</th><th>Mídia</th><th>Nº Clientes</th><th>Nº Campanhas</th></tr></thead>
                <tbody>
                    <?php foreach ($clientes['agencias_cadastro'] as $ag): ?>
                    <tr>
                        <td>
                            <?php if ($ag['logo']): ?>
                            <img src="/<?= htmlspecialchars($ag['logo']) ?>" alt="" style="width:36px;height:36px;border-radius:6px;object-fit:contain;background:#f6f7fb;border:1px solid var(--color-border);">
                            <?php else: ?>
                            <div style="width:36px;height:36px;border-radius:6px;background:#f6f7fb;border:1px solid var(--color-border);display:flex;align-items:center;justify-content:center;font-size:0.9rem;color:var(--color-text-muted);">🏛️</div>
                            <?php endif; ?>
                        </td>
                        <td><strong><a href="/gestor/agencias/ficha?id=<?= (int)$ag['id'] ?>" style="color:inherit;text-decoration:none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'"><?= htmlspecialchars($ag['nome']) ?></a></strong></td>
                        <td style="color:var(--color-text-muted)"><?= htmlspecialchars($ag['endereco'] ?: '-') ?></td>
                        <td style="color:var(--color-text-muted)"><?= htmlspecialchars($ag['telefone'] ?: '-') ?></td>
                        <td><?= $ag['qtd_diretoria'] ?></td>
                        <td><?= $ag['qtd_midia'] ?></td>
                        <td><?= $ag['qtd_clientes'] ?></td>
                        <td><strong style="color:var(--color-accent-primary)"><?= $ag['qtd_campanhas'] ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div><!-- /tab-agencias -->


</div><!-- /container -->

<!-- ── Modal de detalhes do contrato ── -->
<div class="cp-modal-overlay" id="relDetalheOverlay">
    <div class="cp-modal cp-card" style="padding:0;">
        <button type="button" class="rel-modal-fechar" onclick="fecharDetalhesContrato()" title="Fechar">✕</button>
        <div class="cp-card-faixa" id="relDetalheFaixa" style="background:#888"></div>
        <div class="cp-card-head">
            <div class="cp-card-top">
                <span class="sit-badge" id="relDetalheBadge" style="background:#888">Ocupado</span>
                <span class="cp-card-nome" id="relDetalheNome"></span>
            </div>
            <div class="cp-card-cliente" id="relDetalheCliente"></div>
            <div class="cp-card-agencia" id="relDetalheAgencia" style="display:none"></div>
            <div class="cp-card-meta">
                <span class="cp-card-periodo" id="relDetalhePeriodo"></span>
            </div>
        </div>
        <div class="cp-card-paineis" id="relDetalhePontos"></div>
        <div class="cp-card-footer">
            <span id="relDetalheQtd"></span>
            <div class="cp-acoes">
                <a href="#" target="_blank" id="relDetalheChecking" class="cp-btn cp-btn-checking" title="Checking fotográfico desta campanha">📸 Checking</a>
                <button type="button" id="relDetalheDocsBtn" class="cp-btn cp-btn-docs" onclick="abrirDocumentosRelatorio()" title="Documentos financeiros (Contrato / P.I. / P.P.)">📎 Docs (0)</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal de documentos financeiros (Contrato / P.I. / P.P.) ── -->
<div class="cp-modal-overlay" id="relDocsOverlay">
    <div class="cp-modal">
        <div class="cp-modal-title">📎 Documentos Financeiros</div>
        <div class="cp-modal-sub" id="relDocsSub"></div>

        <div class="cp-docs-secao">
            <div class="cp-docs-tipo-titulo">Contrato</div>
            <div class="cp-docs-lista" id="relDocsListaCONTRATO"></div>
            <label class="cp-docs-upload">
                📤 Enviar novo contrato
                <input type="file" accept="application/pdf" id="relDocsInputCONTRATO" onchange="enviarDocumentoRelatorio('CONTRATO', this)">
            </label>
        </div>

        <div class="cp-modal-divider"></div>

        <div class="cp-docs-secao">
            <div class="cp-docs-tipo-titulo">Pedido de Inserção (P.I.)</div>
            <div class="cp-docs-lista" id="relDocsListaPI"></div>
            <label class="cp-docs-upload">
                📤 Enviar novo P.I.
                <input type="file" accept="application/pdf" id="relDocsInputPI" onchange="enviarDocumentoRelatorio('PI', this)">
            </label>
        </div>

        <div class="cp-modal-divider"></div>

        <div class="cp-docs-secao">
            <div class="cp-docs-tipo-titulo">Pedido de Produção (P.P.)</div>
            <div class="cp-docs-lista" id="relDocsListaPP"></div>
            <label class="cp-docs-upload">
                📤 Enviar novo P.P.
                <input type="file" accept="application/pdf" id="relDocsInputPP" onchange="enviarDocumentoRelatorio('PP', this)">
            </label>
        </div>

        <div class="cp-modal-actions">
            <button class="cp-btn-cancelar" onclick="document.getElementById('relDocsOverlay').classList.remove('aberto')">Fechar</button>
        </div>
    </div>
</div>

<!-- ── Modal de edição rápida de cliente (sem sair da página) ── -->
<div class="cp-modal-overlay" id="cliEdicaoOverlay">
    <div class="cp-modal">
        <div class="cp-modal-title">✏️ Editar Cliente</div>
        <div class="cp-modal-sub">Razão Social, Nome Fantasia, CNPJ e E-mail</div>

        <input type="hidden" id="cliEdicaoId">

        <div class="cli-modal-field cli-modal-logo-field">
            <label class="cli-modal-label">Logomarca</label>
            <div style="display:flex; align-items:center; gap:0.7rem;">
                <img id="cliEdicaoLogoPreview" src="" alt="" style="width:44px;height:44px;border-radius:8px;object-fit:contain;background:#f6f7fb;border:1px solid var(--color-border);flex-shrink:0;display:none;">
                <div style="flex:1; min-width:0;">
                    <input type="file" id="cliEdicaoLogo" class="cli-modal-file" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                    <div style="font-size:0.68rem;color:var(--color-text-muted);margin-top:0.2rem;">PNG, JPG, WEBP ou SVG — até 2 MB.</div>
                </div>
            </div>
        </div>
        <div class="cli-modal-field">
            <label class="cli-modal-label">Razão Social *</label>
            <input type="text" id="cliEdicaoRazaoSocial" class="cli-modal-input" maxlength="200">
        </div>
        <div class="cli-modal-field">
            <label class="cli-modal-label">Nome Fantasia</label>
            <input type="text" id="cliEdicaoNomeFantasia" class="cli-modal-input" maxlength="200">
        </div>
        <div class="cli-modal-field" style="display:flex; gap:0.7rem;">
            <div style="flex:1; min-width:0;">
                <label class="cli-modal-label">CNPJ</label>
                <input type="text" id="cliEdicaoCnpj" class="cli-modal-input" maxlength="20" placeholder="00.000.000/0000-00">
            </div>
            <div style="flex:1; min-width:0;">
                <label class="cli-modal-label">E-mail</label>
                <input type="email" id="cliEdicaoEmail" class="cli-modal-input" maxlength="150">
            </div>
        </div>

        <div class="cli-modal-erro" id="cliEdicaoErro"></div>

        <div class="cp-modal-actions">
            <button class="cp-btn-cancelar" onclick="fecharEdicaoCliente()">Cancelar</button>
            <button class="cp-btn-salvar" id="cliEdicaoSalvarBtn" onclick="salvarEdicaoCliente()">💾 Salvar</button>
        </div>
    </div>
</div>

<!-- ── Modal de confirmação de exclusão de documento financeiro ── -->
<div class="cp-modal-overlay" id="docExclusaoOverlay">
    <div class="cp-modal" style="width:380px;">
        <div class="cp-modal-title">🗑️ Excluir Documento</div>
        <div class="cp-modal-sub" style="margin-bottom:0;">
            Tem certeza que deseja excluir este documento? Essa ação não pode ser desfeita.
        </div>

        <input type="hidden" id="docExclusaoId">

        <div class="cp-modal-actions">
            <button class="cp-btn-cancelar" onclick="fecharExclusaoDocumento()">Cancelar</button>
            <button class="cp-btn-salvar" id="docExclusaoConfirmarBtn" onclick="confirmarExclusaoDocumento()" style="background:#dc3545;">🗑️ Excluir</button>
        </div>
    </div>
</div>

<!-- ── Modal de confirmação de exclusão de cliente ── -->
<div class="cp-modal-overlay" id="cliExclusaoOverlay">
    <div class="cp-modal" style="width:400px;">
        <div class="cp-modal-title">🗑️ Excluir Cliente</div>
        <div class="cp-modal-sub" style="margin-bottom:0;">
            Tem certeza que deseja excluir <strong id="cliExclusaoNome"></strong>?
            Essa ação não pode ser desfeita.
        </div>

        <input type="hidden" id="cliExclusaoId">

        <div class="cp-modal-actions">
            <button class="cp-btn-cancelar" onclick="fecharExclusaoCliente()">Cancelar</button>
            <button class="cp-btn-salvar" id="cliExclusaoConfirmarBtn" onclick="confirmarExclusaoCliente()" style="background:#dc3545;">🗑️ Excluir</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="relToast" style="
    position:fixed;top:50%;left:50%;z-index:9999;
    background:#1a9059;color:white;padding:0.9rem 1.5rem;
    border-radius:10px;font-size:0.95rem;font-weight:700;
    box-shadow:0 8px 32px rgba(0,0,0,0.3);
    transform:translate(-50%,-50%) scale(0.9);opacity:0;transition:all 0.3s ease;
    pointer-events:none;max-width:90vw;text-align:center;
"></div>

<script>
var _ultimoContrato = null; // último contrato aberto no modal de detalhes

function switchTab(name, btn) {
    document.querySelectorAll('.tab-content').forEach(function(t){ t.classList.remove('active'); });
    document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); });
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
    history.replaceState(null, '', '#' + name);
}

(function(){
    var hash = location.hash.replace('#','');
    var tabs = ['contratos','clientes','agencias','ocupacao'];
    var idx = tabs.indexOf(hash);
    if (idx !== -1) {
        document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); });
        document.querySelectorAll('.tab-content').forEach(function(t){ t.classList.remove('active'); });
        document.getElementById('tab-' + hash).classList.add('active');
        document.querySelectorAll('.tab-btn')[idx].classList.add('active');
    }
})();

function aplicarFiltrosContratosAtivos() {
    var valorDocs = document.getElementById('filtroDocs').value;
    var termo = (document.getElementById('buscaContratosAtivos').value || '').toLowerCase().trim();
    var linhas = document.querySelectorAll('#tabelaContratosAtivosWrap .rel-row-clicavel');
    linhas.forEach(function(el) {
        var temDoc = !!el.querySelector('.docs-ok');
        var passaDocs = valorDocs === 'todos' || (valorDocs === 'com' && temDoc) || (valorDocs === 'sem' && !temDoc);
        var passaBusca = termo === '' || el.textContent.toLowerCase().indexOf(termo) !== -1;
        el.style.display = (passaDocs && passaBusca) ? '' : 'none';
    });
}

function exportCSV(tableId, filename) {
    var table = document.getElementById(tableId);
    if (!table) { alert('Tabela não encontrada.'); return; }
    var csv = '';
    var rows = table.querySelectorAll('tr');
    for (var i=0; i<rows.length; i++) {
        var cells = rows[i].querySelectorAll('th, td');
        var row = [];
        for (var j=0; j<cells.length; j++) {
            var txt = cells[j].innerText.trim().replace(/\n/g,' ');
            if (txt.indexOf(',') !== -1 || txt.indexOf('"') !== -1) txt = '"' + txt.replace(/"/g,'""') + '"';
            row.push(txt);
        }
        csv += row.join(',') + '\n';
    }
    var blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename + '_' + new Date().toISOString().slice(0,10) + '.csv';
    link.click();
}

// ── Modal de detalhes do contrato ─────────────────────────────
var CORES_SITUACAO = {
    'Ocupado': '#dc3545', 'Reservado': '#fd7e14',
    'Permuta': '#51086e', 'Bisemana': '#0284c7',
    'Vencido': '#6c757d'
};

function abrirDetalhesContrato(c) {
    var cor = CORES_SITUACAO[c.situacao] || '#888';

    document.getElementById('relDetalheFaixa').style.background = cor;
    document.getElementById('relDetalheBadge').style.background = cor;
    document.getElementById('relDetalheBadge').textContent = c.situacao || 'Ocupado';
    document.getElementById('relDetalheNome').textContent = c.campanha || 'Sem nome';
    document.getElementById('relDetalheCliente').textContent = c.cliente || '-';

    var agenciaEl = document.getElementById('relDetalheAgencia');
    if (c.agencia) { agenciaEl.textContent = c.agencia; agenciaEl.style.display = ''; }
    else { agenciaEl.style.display = 'none'; }

    document.getElementById('relDetalhePeriodo').textContent =
        (c.inicio_fmt || '?') + ' → ' + (c.fim_fmt || '?');

    var pontos = c.pontos || [];
    var htmlPontos = pontos.map(function(p) {
        var num = String(p.numero || '').padStart(3, '0');
        var cidReg = [p.cidade, p.regiao].filter(Boolean).join(' · ');
        var link = p.ponto_id
            ? '<a href="/gestor/pontos/detalhes?id=' + p.ponto_id + '" class="cp-painel-link" title="Ver ponto" target="_blank">→</a>'
            : '';
        return '<div class="cp-painel-row">' +
            '<span class="cp-painel-num">' + num + '</span>' +
            '<div class="cp-painel-end">' +
                '<div class="cp-painel-log">' + (p.logradouro || '') + '</div>' +
                '<div class="cp-painel-cid">' + cidReg + '</div>' +
            '</div>' + link +
        '</div>';
    }).join('');
    document.getElementById('relDetalhePontos').innerHTML = htmlPontos || '<div class="cp-empty">Nenhum ponto vinculado.</div>';
    document.getElementById('relDetalheQtd').textContent = pontos.length + ' ponto' + (pontos.length > 1 ? 's' : '');

    var pontoIds = pontos.map(function(p){ return p.ponto_id; }).filter(Boolean);
    var params = new URLSearchParams();
    params.set('cliente', c.cliente_raw || c.cliente || '');
    params.set('agencia', c.agencia || '');
    params.set('campanha', c.motivo || '');
    params.set('situacao', c.situacao || 'Ocupado');
    params.set('inicio', (c.inicio_contrato || '').substring(0, 10));
    params.set('fim', (c.fim_contrato || '').substring(0, 10));
    pontoIds.forEach(function(id){ params.append('pontoIds[]', id); });

    document.getElementById('relDetalheChecking').href = '/gestor/campanhas/checking?' + params.toString();

    _ultimoContrato = c;
    var documentos = c.documentos || [];
    document.getElementById('relDetalheDocsBtn').textContent = '📎 Docs (' + documentos.length + ')';

    document.getElementById('relDetalheOverlay').classList.add('aberto');
}

function fecharDetalhesContrato() {
    document.getElementById('relDetalheOverlay').classList.remove('aberto');
}

document.getElementById('relDetalheOverlay').addEventListener('click', function(e) {
    if (e.target === this) fecharDetalhesContrato();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharDetalhesContrato();
        document.getElementById('relDocsOverlay').classList.remove('aberto');
    }
});

// ── Modal de documentos financeiros (Contrato / P.I. / P.P.) ──
var _relDocsGrupo = null; // {cliente, agencia, campanha, inicio, fim}

function abrirDocumentosRelatorio() {
    if (!_ultimoContrato) return;
    var c = _ultimoContrato;
    _relDocsGrupo = {
        cliente:  c.cliente_raw || c.cliente || '',
        agencia:  c.agencia  || '',
        campanha: c.motivo || '',
        inicio:   (c.inicio_contrato || '').substring(0, 10),
        fim:      (c.fim_contrato    || '').substring(0, 10),
    };
    document.getElementById('relDocsSub').textContent = c.cliente + (c.campanha && c.campanha !== '-' ? ' — ' + c.campanha : '');
    var documentos = c.documentos || [];
    renderizarDocsRelatorio('CONTRATO', documentos);
    renderizarDocsRelatorio('PI', documentos);
    renderizarDocsRelatorio('PP', documentos);
    document.getElementById('relDocsOverlay').classList.add('aberto');
}

function renderizarDocsRelatorio(tipo, documentos) {
    var lista = documentos.filter(function(d) { return d.tipo === tipo; });
    var el = document.getElementById('relDocsLista' + tipo);
    if (lista.length === 0) {
        el.innerHTML = '<div class="cp-docs-vazio">Nenhum arquivo enviado ainda</div>';
        return;
    }
    el.innerHTML = lista.map(function(d) {
        var data = new Date(d.criado_em.replace(' ', 'T')).toLocaleDateString('pt-BR');
        return '<div class="cp-docs-item">' +
            '<a href="/' + d.caminho + '" target="_blank">📄 ' + (d.nome_original || 'arquivo.pdf') + '</a>' +
            '<span class="cp-docs-item-data">' + data + '</span>' +
            '<button class="cp-docs-item-excluir" onclick="excluirDocumentoRelatorio(' + d.id + ')" title="Excluir">✕</button>' +
        '</div>';
    }).join('');
}

function enviarDocumentoRelatorio(tipo, inputEl) {
    if (!inputEl.files || !inputEl.files[0]) return;
    var fd = new FormData();
    fd.append('cliente',  _relDocsGrupo.cliente);
    fd.append('agencia',  _relDocsGrupo.agencia);
    fd.append('campanha', _relDocsGrupo.campanha);
    fd.append('inicio',   _relDocsGrupo.inicio);
    fd.append('fim',      _relDocsGrupo.fim);
    fd.append('tipo',     tipo);
    fd.append('arquivo',  inputEl.files[0]);

    fetch('/gestor/campanhas/documentos/upload', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            inputEl.value = '';
            if (!resp.ok) {
                mostrarToastRelatorio('Erro ao enviar arquivo (' + (resp.erro || 'desconhecido') + ')', 'err');
                return;
            }
            mostrarToastRelatorio('Documento enviado com sucesso!');
            location.reload();
        })
        .catch(function() {
            mostrarToastRelatorio('Erro de conexão ao enviar arquivo', 'err');
        });
}

function excluirDocumentoRelatorio(docId) {
    document.getElementById('docExclusaoId').value = docId;
    document.getElementById('docExclusaoOverlay').classList.add('aberto');
}

function fecharExclusaoDocumento() {
    document.getElementById('docExclusaoOverlay').classList.remove('aberto');
}

document.getElementById('docExclusaoOverlay').addEventListener('click', function(e) {
    if (e.target === this) fecharExclusaoDocumento();
});

function confirmarExclusaoDocumento() {
    var docId = document.getElementById('docExclusaoId').value;
    var btn = document.getElementById('docExclusaoConfirmarBtn');
    var fd = new FormData();
    fd.append('action', 'excluir');
    fd.append('doc_id', docId);

    btn.disabled = true;
    fetch('/gestor/campanhas/documentos/upload', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            btn.disabled = false;
            fecharExclusaoDocumento();
            if (!resp.ok) {
                mostrarToastRelatorio('Erro ao excluir (' + (resp.erro || 'desconhecido') + ')', 'err');
                return;
            }
            mostrarToastRelatorio('Documento excluído.');
            location.reload();
        })
        .catch(function() {
            btn.disabled = false;
            fecharExclusaoDocumento();
            mostrarToastRelatorio('Erro de conexão ao excluir', 'err');
        });
}

// ── Edição rápida de cliente (Razão Social / Nome Fantasia / CNPJ / E-mail) ──
var CLI_CSRF_TOKEN = <?= json_encode($csrfToken) ?>;

function abrirEdicaoCliente(cliente) {
    document.getElementById('cliEdicaoId').value = cliente.id;
    document.getElementById('cliEdicaoRazaoSocial').value = cliente.razao_social || '';
    document.getElementById('cliEdicaoNomeFantasia').value = cliente.nome_fantasia || '';
    document.getElementById('cliEdicaoCnpj').value = cliente.cnpj || '';
    document.getElementById('cliEdicaoEmail').value = cliente.email || '';
    document.getElementById('cliEdicaoLogo').value = '';
    var preview = document.getElementById('cliEdicaoLogoPreview');
    if (cliente.logo) {
        preview.src = '/' + cliente.logo;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
    document.getElementById('cliEdicaoErro').style.display = 'none';
    document.getElementById('cliEdicaoOverlay').classList.add('aberto');
}

function fecharEdicaoCliente() {
    document.getElementById('cliEdicaoOverlay').classList.remove('aberto');
}

document.getElementById('cliEdicaoOverlay').addEventListener('click', function(e) {
    if (e.target === this) fecharEdicaoCliente();
});

// Destaca visualmente a linha do último cliente editado, pra achar fácil numa lista grande
function marcarClienteEditado(row) {
    var anterior = document.querySelector('.cli-row-editado');
    if (anterior) {
        anterior.classList.remove('cli-row-editado');
        var badgeAnterior = anterior.querySelector('.cli-badge-editado');
        if (badgeAnterior) badgeAnterior.remove();
    }
    row.classList.add('cli-row-editado');
    var nomeEl = row.querySelector('.cli-cel-razao a') || row.querySelector('.cli-cel-razao strong');
    if (nomeEl) {
        var badge = document.createElement('span');
        badge.className = 'cli-badge-editado';
        badge.textContent = 'Editado agora';
        nomeEl.after(badge);
    }
    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function salvarEdicaoCliente() {
    var id = document.getElementById('cliEdicaoId').value;
    var razaoSocial = document.getElementById('cliEdicaoRazaoSocial').value.trim();
    var nomeFantasia = document.getElementById('cliEdicaoNomeFantasia').value.trim();
    var cnpj = document.getElementById('cliEdicaoCnpj').value.trim();
    var email = document.getElementById('cliEdicaoEmail').value.trim();
    var erroEl = document.getElementById('cliEdicaoErro');
    var btn = document.getElementById('cliEdicaoSalvarBtn');

    erroEl.style.display = 'none';
    if (razaoSocial === '') {
        erroEl.textContent = 'Razão social é obrigatória.';
        erroEl.style.display = 'block';
        return;
    }

    var fd = new FormData();
    fd.append('csrf_token', CLI_CSRF_TOKEN);
    fd.append('id', id);
    fd.append('razao_social', razaoSocial);
    fd.append('nome_fantasia', nomeFantasia);
    fd.append('cnpj', cnpj);
    fd.append('email', email);
    var logoFile = document.getElementById('cliEdicaoLogo').files[0];
    if (logoFile) fd.append('logo', logoFile);

    btn.disabled = true;
    fetch('/gestor/clientes/salvar', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd,
    })
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            btn.disabled = false;
            if (!resp.ok) {
                erroEl.textContent = resp.erro || 'Erro ao salvar.';
                erroEl.style.display = 'block';
                return;
            }
            var c = resp.cliente;
            var row = document.getElementById('cli-row-' + c.id);
            if (row) {
                var razaoLink = row.querySelector('.cli-cel-razao a');
                if (razaoLink) { razaoLink.textContent = c.razao_social; }
                else { row.querySelector('.cli-cel-razao strong').textContent = c.razao_social; }
                row.querySelector('.cli-cel-fantasia').textContent = c.nome_fantasia || '-';
                row.querySelector('.cli-cel-cnpj').textContent = c.cnpj || '-';
                row.querySelector('.cli-cel-email').textContent = c.email || '-';
                if (c.logo) {
                    row.querySelector('.cli-cel-logo').innerHTML =
                        '<img src="/' + c.logo + '" alt="" style="width:36px;height:36px;border-radius:6px;object-fit:contain;background:#f6f7fb;border:1px solid var(--color-border);">';
                }
                marcarClienteEditado(row);
            }
            fecharEdicaoCliente();
            mostrarToastRelatorio('Cliente atualizado.');
        })
        .catch(function() {
            btn.disabled = false;
            erroEl.textContent = 'Erro de conexão ao salvar.';
            erroEl.style.display = 'block';
        });
}

// ── Confirmação de exclusão de cliente ──
function abrirExclusaoCliente(id, razaoSocial) {
    document.getElementById('cliExclusaoId').value = id;
    document.getElementById('cliExclusaoNome').textContent = razaoSocial;
    document.getElementById('cliExclusaoOverlay').classList.add('aberto');
}

function fecharExclusaoCliente() {
    document.getElementById('cliExclusaoOverlay').classList.remove('aberto');
}

document.getElementById('cliExclusaoOverlay').addEventListener('click', function(e) {
    if (e.target === this) fecharExclusaoCliente();
});

function confirmarExclusaoCliente() {
    var id = document.getElementById('cliExclusaoId').value;
    var btn = document.getElementById('cliExclusaoConfirmarBtn');
    var fd = new FormData();
    fd.append('csrf_token', CLI_CSRF_TOKEN);
    fd.append('id', id);

    btn.disabled = true;
    fetch('/gestor/clientes/excluir', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd,
    })
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            btn.disabled = false;
            fecharExclusaoCliente();
            if (!resp.ok) {
                mostrarToastRelatorio(resp.erro || 'Erro ao excluir.', 'err');
                return;
            }
            var row = document.getElementById('cli-row-' + id);
            if (row) row.remove();
            ['cliKpiTotal', 'cliContagemTitulo'].forEach(function(elId) {
                var el = document.getElementById(elId);
                if (el) el.textContent = Math.max(0, parseInt(el.textContent, 10) - 1);
            });
            mostrarToastRelatorio('Cliente excluído.');
        })
        .catch(function() {
            btn.disabled = false;
            fecharExclusaoCliente();
            mostrarToastRelatorio('Erro de conexão ao excluir.', 'err');
        });
}

function mostrarToastRelatorio(msg, tipo) {
    var t = document.getElementById('relToast');
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

document.getElementById('relDocsOverlay').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('aberto');
});
</script>

</body>
</html>
