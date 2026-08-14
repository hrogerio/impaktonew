<?php
/**
 * PDF / Impressão da Reserva — /gestor/reservas/pdf?id=X
 * Página limpa otimizada para print → Salvar como PDF
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(404); die('Reserva não encontrada.'); }

try {
    require_once __DIR__ . '/../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) { die("Erro na conexão."); }

$stmt = $pdo->prepare("SELECT * FROM pre_selecoes WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$ps = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ps) { http_response_code(404); die('Reserva não encontrada.'); }

$stmt2 = $pdo->prepare("
    SELECT p.id, p.numero, p.logradouro, p.descricao, p.cidade, p.regiao,
           p.tipo, p.situacao, p.formato,
           COALESCE(
               (SELECT pf.caminho FROM ponto_fotos pf WHERE pf.ponto_id = p.id AND pf.principal = 1 LIMIT 1),
               p.foto
           ) AS foto
    FROM pre_selecao_pontos psp
    JOIN pontos p ON p.id = psp.ponto_id
    WHERE psp.pre_selecao_id = ?
    ORDER BY psp.ordem ASC, p.numero ASC
");
$stmt2->execute([$id]);
$pontos = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Período
$periodo = '';
if ($ps['sem_periodo']) $periodo = 'Sem período definido';
elseif ($ps['periodo_ini'] && $ps['periodo_fim'])
    $periodo = date('d/m/Y', strtotime($ps['periodo_ini'])) . ' a ' . date('d/m/Y', strtotime($ps['periodo_fim']));
elseif ($ps['periodo_ini'])
    $periodo = 'A partir de ' . date('d/m/Y', strtotime($ps['periodo_ini']));

// Agrupar por região
$grupos = []; $ordem = [];
foreach ($pontos as $p) {
    $reg = trim($p['regiao'] ?? '') ?: 'Sem região';
    if (!isset($grupos[$reg])) { $grupos[$reg] = []; $ordem[] = $reg; }
    $grupos[$reg][] = $p;
}
sort($ordem);

$CORES = ['Disponivel'=>'#1a9059','Disponível'=>'#1a9059','Ocupado'=>'#dc3545',
          'Reservado'=>'#fd7e14','Vencido'=>'#6c757d','Permuta'=>'#51086e','Bisemana'=>'#0284c7'];

function corSit($sit, $cores) { return $cores[$sit] ?? '#888'; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Proposta — <?= htmlspecialchars($ps['cliente']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body {
            font-family:'Montserrat',Arial,sans-serif;
            font-size:10pt; color:#1a1a1a;
            background:white;
        }

        /* ── Botão imprimir (some no print) ── */
        .no-print {
            position:fixed; top:1rem; right:1rem;
            display:flex; gap:0.5rem;
        }
        .btn-print {
            padding:0.5rem 1.1rem; background:#c0392b; color:white;
            border:none; border-radius:6px; font-family:inherit;
            font-size:0.85rem; font-weight:700; cursor:pointer;
        }
        .btn-voltar {
            padding:0.5rem 1.1rem; background:#f3f4f6; color:#555;
            border:1px solid #ddd; border-radius:6px; font-family:inherit;
            font-size:0.85rem; font-weight:600; cursor:pointer;
            text-decoration:none; display:inline-flex; align-items:center;
        }
        @media print { .no-print { display:none !important; } }

        /* ── Página ── */
        .pagina { max-width:800px; margin:0 auto; padding:1.5cm 1.8cm; }
        @media print {
            .pagina { padding:0; max-width:100%; }
            body { print-color-adjust:exact; -webkit-print-color-adjust:exact; }
        }

        /* ── Cabeçalho ── */
        .cabecalho {
            display:flex; align-items:flex-start; justify-content:space-between;
            border-bottom:3px solid #c0392b; padding-bottom:0.7rem; margin-bottom:1.2rem;
        }
        .logo { height:44px; width:auto; }
        .cab-titulo { font-size:1.1rem; font-weight:800; color:#1a1a1a; margin-top:0.2rem; }
        .cab-sub { font-size:0.82rem; color:#555; margin-top:0.2rem; }
        .cab-data { font-size:0.72rem; color:#888; text-align:right; line-height:1.6; }

        /* ── Meta ── */
        .meta-grid {
            display:grid; grid-template-columns:repeat(3,1fr); gap:0.5rem 1rem;
            background:#f8f8f8; border:1px solid #e8e8e8; border-radius:6px;
            padding:0.75rem 1rem; margin-bottom:1.2rem;
        }
        .meta-item {}
        .meta-label { font-size:0.6rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; color:#888; }
        .meta-val   { font-size:0.82rem; font-weight:700; color:#1a1a1a; margin-top:1px; }

        /* ── Grupo/Região ── */
        .grupo {
            background:#f0f0f0; padding:0.3rem 0.65rem;
            font-size:0.68rem; font-weight:800; text-transform:uppercase;
            letter-spacing:0.7px; color:#222;
            border-left:4px solid #c0392b; margin:1rem 0 0;
            page-break-after:avoid;
        }

        /* ── Tabela de pontos ── */
        table { width:100%; border-collapse:collapse; font-size:0.8rem; }
        th {
            border-bottom:1.5px solid #555; padding:0.3rem 0.5rem;
            text-align:left; font-size:0.62rem; font-weight:700;
            color:#555; text-transform:uppercase; letter-spacing:0.4px;
            background:#fafafa;
        }
        td { padding:0.35rem 0.5rem; border-bottom:1px solid #ececec; vertical-align:middle; }
        tr:last-child td { border-bottom:none; }

        .col-num  { width:42px; font-weight:800; color:#c0392b; }
        .col-foto { width:70px; padding:3px 5px !important; }
        .thumb    { width:62px; height:50px; object-fit:cover; border-radius:4px; display:block; }
        .thumb-vazio { width:62px; height:50px; background:#f0f0f0; border-radius:4px; display:flex; align-items:center; justify-content:center; font-size:1rem; color:#ccc; }
        .col-sit  { white-space:nowrap; }
        .sit-badge {
            display:inline-block; padding:2px 7px; border-radius:8px;
            font-size:0.6rem; font-weight:800; text-transform:uppercase;
            color:white; white-space:nowrap;
        }
        .col-link { width:50px; text-align:center; }
        .link-ponto { font-size:0.65rem; font-weight:700; color:#c0392b; text-decoration:none; }

        /* ── Total ── */
        .total {
            border-top:2px solid #333; margin-top:0.8rem; padding-top:0.4rem;
            font-size:0.85rem; font-weight:800; text-align:right;
        }

        /* ── Aviso de prazo ── */
        .aviso-prazo {
            margin-top:1rem;
            font-size:0.65rem; color:#999;
            border-top:1px dashed #ddd;
            padding-top:0.5rem;
            line-height:1.6;
        }

        /* ── Rodapé ── */
        .rodape {
            margin-top:2rem; font-size:0.65rem; color:#bbb;
            border-top:1px solid #e0e0e0; padding-top:0.4rem;
            display:flex; justify-content:space-between;
        }
    </style>
</head>
<body>

<div class="no-print">
    <a href="/gestor/reservas/ver?id=<?= $id ?>" class="btn-voltar">← Voltar</a>
    <button class="btn-print" onclick="window.print()">🖨️ Imprimir / Salvar PDF</button>
</div>

<div class="pagina">

    <!-- Cabeçalho -->
    <div class="cabecalho">
        <div>
            <img src="/public/assets/img/barra.png" alt="Impakto Mídia OOH" class="logo">
            <div class="cab-titulo">Pré-Seleção de Mídia Exterior</div>
            <div class="cab-sub">
                <?= htmlspecialchars($ps['cliente']) ?>
                <?php if ($ps['agencia']): ?> / <?= htmlspecialchars($ps['agencia']) ?><?php endif; ?>
            </div>
        </div>
        <div class="cab-data">
            Emitido em<br>
            <strong><?= date('d/m/Y') ?></strong><br>
            Proposta #<?= $ps['id'] ?>
        </div>
    </div>

    <!-- Meta -->
    <div class="meta-grid">
        <div class="meta-item">
            <div class="meta-label">Cliente</div>
            <div class="meta-val"><?= htmlspecialchars($ps['cliente']) ?></div>
        </div>
        <?php if ($ps['agencia']): ?>
        <div class="meta-item">
            <div class="meta-label">Agência</div>
            <div class="meta-val"><?= htmlspecialchars($ps['agencia']) ?></div>
        </div>
        <?php endif; ?>
        <div class="meta-item">
            <div class="meta-label">Período</div>
            <div class="meta-val"><?= htmlspecialchars($periodo ?: '—') ?></div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Total de Pontos</div>
            <div class="meta-val"><?= count($pontos) ?></div>
        </div>
    </div>

    <!-- Pontos agrupados por região -->
    <?php $n = 0; foreach ($ordem as $reg):
        $pts = $grupos[$reg] ?? [];
        if (empty($pts)) continue;
    ?>
    <div class="grupo"><?= htmlspecialchars($reg) ?> &nbsp;(<?= count($pts) ?> ponto<?= count($pts) > 1 ? 's' : '' ?>)</div>
    <table>
        <thead>
            <tr>
                <th class="col-foto"></th>
                <th class="col-num">Nº</th>
                <th>Logradouro</th>
                <th style="width:100px">Cidade</th>
                <th style="width:80px" class="col-sit">Situação</th>
                <th class="col-link">Link</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($pts as $p): $n++; $cor = corSit($p['situacao'], $CORES); ?>
        <tr>
            <td class="col-foto">
                <?php if ($p['foto']): ?>
                <img class="thumb" src="<?= (strpos($p['foto'],'http')===0?'':'/') . htmlspecialchars($p['foto']) ?>"
                     onerror="this.outerHTML='<div class=\'thumb-vazio\'>📷</div>'">
                <?php else: ?><div class="thumb-vazio">📷</div><?php endif; ?>
            </td>
            <td class="col-num"><?= htmlspecialchars($p['numero']) ?></td>
            <td>
                <div style="font-weight:600"><?= htmlspecialchars($p['logradouro']) ?></div>
                <?php if ($p['descricao']): ?>
                <div style="font-size:0.68rem;color:#777"><?= htmlspecialchars($p['descricao']) ?></div>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($p['cidade'] ?? '—') ?></td>
            <td class="col-sit">
                <span class="sit-badge" style="background:<?= $cor ?>">
                    <?= htmlspecialchars($p['situacao'] ?? '—') ?>
                </span>
            </td>
            <td class="col-link">
                <a class="link-ponto" href="<?= rtrim((defined('BASE')?BASE:''), '/') ?>/public/p.php?id=<?= $p['id'] ?>">
                    +Info
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endforeach; ?>

    <div class="total">TOTAL: <?= count($pontos) ?> ponto<?= count($pontos) > 1 ? 's' : '' ?></div>

    <?php $prazo72h = date('d/m/Y \à\s H:i', strtotime('+72 hours')); ?>
    <div class="aviso-prazo">
        ⚠ Esta proposta é válida por 72 horas a partir da emissão. Os pontos listados estão reservados até <strong><?= $prazo72h ?></strong>.
        Após esse prazo, sem confirmação, retornam à disponibilidade e poderão ser negociados com outros clientes.
    </div>

    <div class="rodape">
        <span>Impakto Mídia · impaktomidia.com.br</span>
        <span>Proposta #<?= $ps['id'] ?> · <?= date('d/m/Y H:i') ?></span>
    </div>

</div>

</body>
</html>
