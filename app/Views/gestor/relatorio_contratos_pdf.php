<?php
/**
 * GET /gestor/relatorios/contratos/pdf
 * Gera apenas a seção de Contratos & Tempo de Contrato em PDF — A4 retrato.
 */
ini_set('display_errors', 1);
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
    ob_end_clean(); die('Biblioteca PDF nao encontrada.');
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
$pdf->SetCreator('Impakto Midia OOH');
$pdf->SetTitle(s('Relatorio de Contratos - Impakto Midia'));

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
        $pdf->Image($logoPath, $MX, 8, 34);
    } else {
        $pdf->SetFont(FONT_MAIN, 'B', 14);
        $pdf->SetTextColor(...$VERM);
        $pdf->SetXY($MX, 10);
        $pdf->Cell($CW / 2, 8, s('Impakto Midia'), 0, 0, 'L');
    }

    $pdf->SetFont(FONT_MAIN, '', 8.5);
    $pdf->SetTextColor(...$MUTED);
    $pdf->SetXY($MX, 10);
    $pdf->Cell($CW, 8, s('Relatorio gerado em ' . date('d/m/Y H:i')), 0, 1, 'R');

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

function tabela($pdf, array $headers, array $colWidths, array $rows, $MX, $VERM, $PRETO, $CINZAC, $CW = null, $MUTED = null) {
    $rowH = 6;
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
    $drawHeader();
    $pdf->SetFont(FONT_MAIN, '', 7.5);
    $pdf->SetTextColor(...$PRETO);
    foreach ($rows as $idx => $row) {
        if ($pdf->GetY() + $rowH > 280) {
            $pdf->AddPage();
            if ($CW !== null && $MUTED !== null) cabecalho($pdf, $CW, $MX, $VERM, $MUTED);
            $drawHeader();
            $pdf->SetFont(FONT_MAIN, '', 7.5);
            $pdf->SetTextColor(...$PRETO);
        }
        if ($idx % 2 === 1) {
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

/** Tabela padrão de campanhas (Cliente/Campanha/Agência/Início/Fim/Duração/Pontos) */
function tabelaCampanhasPdf($pdf, array $lista, $MX, $VERM, $PRETO, $CINZAC, $CW, $MUTED) {
    tabela($pdf,
        ['Cliente', 'Campanha', 'Agencia', 'Inicio', 'Fim', 'Duracao', 'Pontos'],
        [32, 30, 36, 20, 20, 22, 14],
        array_map(fn($c) => [$c['cliente'] ?: '-', $c['campanha'] ?: '-', $c['agencia'] ?: '-', fmtDataPdf($c['inicio_contrato']), fmtDataPdf($c['fim_contrato']), fmtDuracaoMesesPdf($c['duracao_dias']), $c['qtd_pontos']], $lista),
        $MX, $VERM, $PRETO, $CINZAC, $CW, $MUTED
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
$pdf->Cell($CW, 12, s('Relatorio de Contratos'), 0, 1, 'L');
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
    ['Ja Vencidos', count($ct['vencidos'])],
], $CW, $MX, $PRETO, $MUTED, $CINZAC);

subtitulo($pdf, 'Contratos Ativos por Cliente', $CW, $MUTED);
tabelaCampanhasPdf($pdf, $ct['campanhas_ativas'], $MX, $VERM, $PRETO, $CINZAC, $CW, $MUTED);

foreach ($ct['vencendo_agrupado'] as $mes => $campanhas) {
    subtitulo($pdf, 'Vencendo em ' . mesLabelPdf($mes), $CW, $MUTED);
    tabelaCampanhasPdf($pdf, $campanhas, $MX, $VERM, $PRETO, $CINZAC, $CW, $MUTED);
}

subtitulo($pdf, 'Contratos Vencidos (todos)', $CW, $MUTED);
tabelaCampanhasPdf($pdf, $ct['vencidos_agrupado'], $MX, $VERM, $PRETO, $CINZAC, $CW, $MUTED);

subtitulo($pdf, 'Historico Anual - Contratos Ativos por Mes', $CW, $MUTED);
graficoBarras($pdf, $ct['ativos_por_mes'], $CW, $MX, $VERM, $PRETO, $MUTED);

ob_end_clean();
$nomeArquivo = 'relatorio-contratos-impakto-' . date('Y-m-d') . '.pdf';
$pdf->Output('D', $nomeArquivo);
