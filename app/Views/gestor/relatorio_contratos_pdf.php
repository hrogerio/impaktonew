<?php
/**
 * GET /gestor/relatorios/contratos/pdf
 * Gera apenas a seção de Contratos & Tempo de Contrato em PDF — A4 retrato.
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('memory_limit', '256M');
set_time_limit(120);
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    ob_end_clean();
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../Controllers/RelatorioController.php';

$controller = new RelatorioController();
$ct = $controller->dadosContratos();
$documentosPorGrupo = $controller->documentosPorGrupo();

// ── tFPDF (com fallback pra FPDF) ──────────────────────────────────────────
$tfpdfPath   = __DIR__ . '/../../../lib/fpdf/tfpdf.php';
$ttfontsPath = __DIR__ . '/../../../lib/fpdf/font/unifont/ttfonts.php';
$fpdfPath    = __DIR__ . '/../../../lib/fpdf/fpdf.php';

define('FPDF_FONTPATH', __DIR__ . '/../../../lib/fpdf/font/');

if (file_exists($tfpdfPath)) {
    if (file_exists($ttfontsPath)) require_once $ttfontsPath;
    require_once $tfpdfPath;
    define('USE_TFPDF', true);
} elseif (file_exists($fpdfPath)) {
    require_once $fpdfPath;
    define('USE_TFPDF', false);
} else {
    ob_end_clean(); die('Biblioteca PDF não encontrada.');
}

function s($str) {
    if (defined('USE_TFPDF') && USE_TFPDF) return (string)($str ?? '');
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string)($str ?? ''));
}
function fmtDataPdf($d) {
    if (!$d || $d === '0000-00-00') return '-';
    try { return (new DateTime($d))->format('d/m/Y'); } catch (Exception $e) { return '-'; }
}
function fmtDuracaoMesesPdf($dias) {
    $dias = (int)$dias;
    if ($dias < 30) return $dias . ' dias';
    return round($dias / 30) . ' meses';
}
function mesLabelPdf($mesStr) {
    $meses = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
              '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];
    [$ano, $m] = explode('-', $mesStr);
    return ($meses[$m] ?? $m) . '/' . substr($ano, 2);
}

$VERM   = [192, 57,  43 ];
$BRANCO = [255, 255, 255];
$PRETO  = [20,  20,  20 ];
$MUTED  = [130, 130, 145];
$CINZAC = [240, 240, 243];

if (defined('USE_TFPDF') && USE_TFPDF) {
    $pdf = new tFPDF('P', 'mm', 'A4');
} else {
    $pdf = new FPDF('P', 'mm', 'A4');
}
$pdf->SetMargins(12, 14, 12);
$pdf->SetAutoPageBreak(true, 16);
$pdf->SetCreator('Impakto Mídia OOH');
$pdf->SetTitle(s('Relatório de Contratos - Impakto'));

if (defined('USE_TFPDF') && USE_TFPDF) {
    $fontDir = __DIR__ . '/../../../lib/fpdf/font/unifont/';
    if (file_exists($fontDir . 'Inter-Regular.ttf')) {
        $pdf->AddFont('Inter', '',  'Inter-Regular.ttf',  true);
        $pdf->AddFont('Inter', 'B', 'Inter-SemiBold.ttf', true);
        $pdf->AddFont('Inter', 'I', 'Inter-Medium.ttf',   true);
        define('FONT_MAIN', 'Inter');
    } else {
        define('FONT_MAIN', 'Helvetica');
    }
} else {
    define('FONT_MAIN', 'Helvetica');
}

$PW = 210; $MX = 12; $CW = $PW - 2 * $MX; // largura útil de conteúdo

// ── Cabeçalho de página (chamado a cada nova página) ───────────────────────
function cabecalho($pdf, $CW, $MX, $VERM, $MUTED) {
    $logoPath = __DIR__ . '/../../../public/assets/img/logo.png';
    if (file_exists($logoPath)) {
        $pdf->Image($logoPath, $MX, 9, 30);
    } else {
        $pdf->SetFont(FONT_MAIN, 'B', 14);
        $pdf->SetTextColor(...$VERM);
        $pdf->SetXY($MX, 10);
        $pdf->Cell($CW / 2, 8, s('Impakto'), 0, 0, 'L');
    }

    $pdf->SetFont(FONT_MAIN, '', 8.5);
    $pdf->SetTextColor(...$MUTED);
    $pdf->SetXY($MX, 10);
    $pdf->Cell($CW, 8, s('Relatório gerado em ' . date('d/m/Y H:i')), 0, 1, 'R');

    $pdf->SetDrawColor(...$VERM);
    $pdf->SetLineWidth(0.5);
    $pdf->Line($MX, 19, $MX + $CW, 19);
    $pdf->SetY(23);
}

function tituloSecao($pdf, $texto, $CW, $MX, $VERM, $PRETO, $MUTED) {
    if ($pdf->GetY() > 250) { $pdf->AddPage(); cabecalho($pdf, $CW, $MX, $VERM, $MUTED); }
    $pdf->Ln(2);
    $pdf->SetFont(FONT_MAIN, 'B', 13);
    $pdf->SetTextColor(...$PRETO);
    $pdf->Cell($CW, 8, s($texto), 0, 1, 'L');
    $pdf->SetDrawColor(...$VERM);
    $pdf->SetLineWidth(0.6);
    $pdf->Line($MX, $pdf->GetY(), $MX + 55, $pdf->GetY());
    $pdf->Ln(4);
}

function subtitulo($pdf, $texto, $CW, $MUTED) {
    $MX = 12; $VERM = [192, 57, 43];
    if ($pdf->GetY() > 260) { $pdf->AddPage(); cabecalho($pdf, $CW, $MX, $VERM, $MUTED); }
    $pdf->SetFont(FONT_MAIN, 'B', 9.5);
    $pdf->SetTextColor(...$MUTED);
    $pdf->Cell($CW, 6, s($texto), 0, 1, 'L');
    $pdf->Ln(1);
}

/** KPIs simples em linha: [[label, valor], ...] */
function kpis($pdf, array $itens, $CW, $MX, $PRETO, $MUTED, $CINZAC) {
    $n = count($itens);
    if ($n === 0) return;
    $w = $CW / $n;
    $y = $pdf->GetY();
    $pdf->SetFillColor(...$CINZAC);
    foreach ($itens as $i => [$label, $valor]) {
        $x = $MX + $i * $w;
        $pdf->Rect($x + 1, $y, $w - 2, 16, 'F');
        $pdf->SetXY($x + 1, $y + 2);
        $pdf->SetFont(FONT_MAIN, 'B', 13);
        $pdf->SetTextColor(...$PRETO);
        $pdf->Cell($w - 2, 7, s($valor), 0, 0, 'C');
        $pdf->SetXY($x + 1, $y + 9.5);
        $pdf->SetFont(FONT_MAIN, '', 7);
        $pdf->SetTextColor(...$MUTED);
        $pdf->Cell($w - 2, 5, s($label), 0, 0, 'C');
    }
    $pdf->SetY($y + 20);
}

/** Tabela genérica com header repetido em nova página */
/** Encurta o texto com reticências até caber na largura da coluna, evitando invasão da célula vizinha */
function truncarTexto($pdf, $texto, $largura) {
    $texto = (string)$texto;
    $maxW = $largura - 2;
    if ($pdf->GetStringWidth($texto) <= $maxW) return $texto;
    while (mb_strlen($texto) > 1 && $pdf->GetStringWidth($texto . '...') > $maxW) {
        $texto = mb_substr($texto, 0, mb_strlen($texto) - 1);
    }
    return $texto . '...';
}

/** $destaque: true marca TODAS as linhas; um array de booleanos (indexado como $rows) marca só as linhas
 *  correspondentes. Linhas destacadas ganham fundo cinza-escuro + barra sólida na margem esquerda +
 *  texto em negrito — visível mesmo em impressão preto e branco (não depende de cor). */
function tabela($pdf, array $headers, array $colWidths, array $rows, $MX, $VERM, $PRETO, $CINZAC, $CW = null, $MUTED = null, $destaque = false) {
    $rowH = 6;
    $CINZAE = [205, 205, 208]; // cinza mais escuro, contrasta bem em tons de cinza (impressão P&B)
    $destaqueTodas = $destaque === true;
    $destaqueArr   = is_array($destaque) ? $destaque : [];
    $drawHeader = function() use ($pdf, $headers, $colWidths, $MX, $VERM) {
        $pdf->SetFont(FONT_MAIN, 'B', 8);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFillColor(...$VERM);
        $pdf->SetX($MX);
        foreach ($headers as $i => $h) {
            $pdf->Cell($colWidths[$i], 6.5, s(truncarTexto($pdf, $h, $colWidths[$i])), 0, 0, 'L', true);
        }
        $pdf->Ln();
    };
    // Garante espaço pro cabeçalho + pelo menos 1 linha antes de desenhar — senão o cabeçalho
    // fica "órfão" no fim da página, sem nenhuma linha visível abaixo dele.
    if ($pdf->GetY() + 6.5 + $rowH > 280) {
        $pdf->AddPage();
        if ($CW !== null && $MUTED !== null) cabecalho($pdf, $CW, $MX, $VERM, $MUTED);
    }
    $drawHeader();
    foreach ($rows as $idx => $row) {
        if ($pdf->GetY() + $rowH > 280) {
            $pdf->AddPage();
            if ($CW !== null && $MUTED !== null) cabecalho($pdf, $CW, $MX, $VERM, $MUTED);
            $drawHeader();
        }
        $rowDestaque = $destaqueTodas || ($destaqueArr[$idx] ?? false);
        $pdf->SetFont(FONT_MAIN, $rowDestaque ? 'B' : '', 7.5);
        $pdf->SetTextColor(...$PRETO);
        if ($rowDestaque) {
            $y = $pdf->GetY();
            $pdf->SetFillColor(...$CINZAE);
            $pdf->SetX($MX);
            foreach ($row as $i => $val) $pdf->Cell($colWidths[$i], $rowH, s(truncarTexto($pdf, $val, $colWidths[$i])), 0, 0, 'L', true);
            $pdf->SetFillColor(...$PRETO);
            $pdf->Rect($MX, $y, 1.2, $rowH, 'F');
        } elseif ($idx % 2 === 1) {
            $pdf->SetFillColor(...$CINZAC);
            $pdf->SetX($MX);
            foreach ($row as $i => $val) $pdf->Cell($colWidths[$i], $rowH, s(truncarTexto($pdf, $val, $colWidths[$i])), 0, 0, 'L', true);
        } else {
            $pdf->SetX($MX);
            foreach ($row as $i => $val) $pdf->Cell($colWidths[$i], $rowH, s(truncarTexto($pdf, $val, $colWidths[$i])), 0, 0, 'L');
        }
        $pdf->Ln();
    }
    $pdf->Ln(3);
}

/** Chave de agrupamento dos documentos financeiros (mesma lógica de campanhas/_helpers.php e relatorios.php) */
function docChavePdf(array $c): string {
    return md5(
        trim($c['cliente_raw'] ?? $c['cliente'] ?? '') . '|' . trim($c['agencia'] ?? '') . '|' . trim($c['motivo'] ?? '') .
        '|' . ($c['inicio_doc'] ?? '') . '|' . ($c['fim_doc'] ?? '')
    );
}

/** Texto da coluna Docs: tipos enviados (CT/P.I./P.P., em ordem de prioridade) ou "Sem doc" */
function docsLabelPdf(array $c, array $documentosPorGrupo): string {
    $docsGrupo = $documentosPorGrupo[docChavePdf($c)] ?? [];
    $tiposPresentes = array_unique(array_column($docsGrupo, 'tipo'));
    $labelsTipo = ['CONTRATO' => 'CT', 'PP' => 'P.P.', 'PI' => 'P.I.'];
    $tiposEmOrdem = array_values(array_filter(['CONTRATO', 'PP', 'PI'], fn($t) => in_array($t, $tiposPresentes, true)));
    return $tiposEmOrdem ? implode(', ', array_map(fn($t) => $labelsTipo[$t], $tiposEmOrdem)) : 'Sem doc';
}

/** Conta quantos contratos da lista não têm nenhum documento financeiro enviado */
function contarSemDocumentosPdf(array $lista, array $documentosPorGrupo): int {
    return count(array_filter($lista, fn($c) => empty($documentosPorGrupo[docChavePdf($c)] ?? [])));
}

/** Contrato cadastrado no sistema nos últimos 30 dias — mesmo critério usado na tela de Relatórios */
function ehNovoPdf(array $c): bool {
    if (empty($c['criado_em'])) return false;
    try { return (new DateTime())->diff(new DateTime($c['criado_em']))->days <= 30; }
    catch (Exception $e) { return false; }
}

/** Tabela padrão de campanhas (Cliente/Campanha/Agência/Início/Fim/Duração/Pontos/Novo/Docs).
 *  $destaqueTodas marca a tabela inteira (ex.: Vencidos); senão, destaca linha a linha os contratos sem documento.
 *  Contratos novos (30 dias) ganham "NOVO" na coluna própria, sem espremer o nome do cliente. */
function tabelaCampanhasPdf($pdf, array $lista, $MX, $VERM, $PRETO, $CINZAC, $CW, $MUTED, array $documentosPorGrupo = [], bool $destaqueTodas = false) {
    $destaque = $destaqueTodas ? true : array_values(array_map(fn($c) => docsLabelPdf($c, $documentosPorGrupo) === 'Sem doc', $lista));
    tabela($pdf,
        ['Cliente', 'Campanha', 'Agência', 'Contato', 'Início', 'Fim', 'Duração', 'Pontos', 'Novo', 'Docs'],
        [30, 25, 23, 17, 16, 16, 16, 11, 12, 20],
        array_map(fn($c) => [$c['cliente'] ?: '-', $c['campanha'] ?: '-', $c['agencia'] ?: '-', $c['contato'] ?: '-', fmtDataPdf($c['inicio_contrato']), fmtDataPdf($c['fim_contrato']), fmtDuracaoMesesPdf($c['duracao_dias']), $c['qtd_pontos'], ehNovoPdf($c) ? 'NOVO' : '-', docsLabelPdf($c, $documentosPorGrupo)], $lista),
        $MX, $VERM, $PRETO, $CINZAC, $CW, $MUTED, $destaque
    );
}

/** Gráfico de barras (mês -> valor), no mesmo estilo do "Histórico Anual" da tela */
function graficoBarras($pdf, array $dados, $CW, $MX, $VERM, $PRETO, $MUTED) {
    $n = count($dados);
    if ($n === 0) return;

    if ($pdf->GetY() > 240) { $pdf->AddPage(); cabecalho($pdf, $CW, $MX, $VERM, $MUTED); }

    $maxVal = max(1, max($dados));
    $gap  = 2;
    $w    = ($CW - ($n - 1) * $gap) / $n;
    $maxH = 32;
    $baseY = $pdf->GetY() + $maxH + 8;

    $i = 0;
    foreach ($dados as $mes => $qtd) {
        $x = $MX + $i * ($w + $gap);
        $h = max(2, ($qtd / $maxVal) * $maxH);

        $pdf->SetFont(FONT_MAIN, 'B', 8);
        $pdf->SetTextColor(...$VERM);
        $pdf->SetXY($x, $baseY - $h - 5);
        $pdf->Cell($w, 5, s((string)$qtd), 0, 0, 'C');

        $pdf->SetFillColor(...$VERM);
        $pdf->Rect($x, $baseY - $h, $w, $h, 'F');

        $pdf->SetFont(FONT_MAIN, '', 7);
        $pdf->SetTextColor(...$MUTED);
        $pdf->SetXY($x, $baseY + 2);
        $pdf->Cell($w, 5, s(mesLabelPdf($mes)), 0, 0, 'C');

        $i++;
    }
    $pdf->SetY($baseY + 10);
}

$pdf->AddPage();
cabecalho($pdf, $CW, $MX, $VERM, $MUTED);

// ── Capa curta ──────────────────────────────────────────────────────────
$pdf->SetFont(FONT_MAIN, 'B', 22);
$pdf->SetTextColor(...$PRETO);
$pdf->Cell($CW, 12, s('Relatório de Contratos'), 0, 1, 'L');
$pdf->SetFont(FONT_MAIN, '', 10.5);
$pdf->SetTextColor(...$MUTED);
$pdf->Cell($CW, 7, s('Contratos Ativos, Vencidos e a Vencer'), 0, 1, 'L');
$pdf->Ln(4);

// ============================================================
// CONTRATOS & TEMPO DE CONTRATO
// ============================================================
tituloSecao($pdf, 'Contratos e Tempo de Contrato', $CW, $MX, $VERM, $PRETO, $MUTED);
kpis($pdf, [
    ['Contratos Ativos', count($ct['campanhas_ativas'])],
    ['Novos (30 dias)', count(array_filter($ct['campanhas_ativas'], 'ehNovoPdf'))],
    ['Já Vencidos', count($ct['vencidos_agrupado'])],
    ['Sem Documentos', contarSemDocumentosPdf($ct['campanhas_ativas'], $documentosPorGrupo)],
], $CW, $MX, $PRETO, $MUTED, $CINZAC);

subtitulo($pdf, 'Contratos Ativos por Cliente', $CW, $MUTED);
tabelaCampanhasPdf($pdf, $ct['campanhas_ativas'], $MX, $VERM, $PRETO, $CINZAC, $CW, $MUTED, $documentosPorGrupo);

foreach ($ct['vencendo_agrupado'] as $mes => $campanhas) {
    subtitulo($pdf, 'Vencendo em ' . mesLabelPdf($mes), $CW, $MUTED);
    tabelaCampanhasPdf($pdf, $campanhas, $MX, $VERM, $PRETO, $CINZAC, $CW, $MUTED, $documentosPorGrupo);
}

subtitulo($pdf, 'Contratos Vencidos (todos)', $CW, $MUTED);
tabelaCampanhasPdf($pdf, $ct['vencidos_agrupado'], $MX, $VERM, $PRETO, $CINZAC, $CW, $MUTED, $documentosPorGrupo, true);

subtitulo($pdf, 'Histórico Anual - Contratos Ativos por Mês', $CW, $MUTED);
graficoBarras($pdf, $ct['ativos_por_mes'], $CW, $MX, $VERM, $PRETO, $MUTED);

ob_end_clean();
$nomeArquivo = 'relatorio-contratos-impakto-' . date('Y-m-d') . '.pdf';
$pdf->Output('D', $nomeArquivo);
