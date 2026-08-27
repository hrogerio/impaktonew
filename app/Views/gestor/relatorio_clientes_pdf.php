<?php
/**
 * GET /gestor/relatorios/clientes/pdf
 * Exporta a relação de Todos os Clientes (Razão Social/Nome Fantasia/CNPJ/E-mail) em PDF — A4 retrato.
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
$clientes = $controller->dadosClientes()['clientes'];

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

$VERM   = [192, 57,  43 ];
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
$pdf->SetTitle(s('Relacao de Clientes - Impakto'));

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

$PW = 210; $MX = 12; $CW = $PW - 2 * $MX;

function cabecalho($pdf, $CW, $MX, $VERM, $MUTED) {
    $logoPath = __DIR__ . '/../../../public/assets/img/barra.png';
    if (file_exists($logoPath)) {
        $pdf->Image($logoPath, $MX, 8, 34);
    } else {
        $pdf->SetFont(FONT_MAIN, 'B', 14);
        $pdf->SetTextColor(...$VERM);
        $pdf->SetXY($MX, 10);
        $pdf->Cell($CW / 2, 8, s('Impakto'), 0, 0, 'L');
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

function truncarTexto($pdf, $texto, $largura) {
    $texto = (string)$texto;
    $maxW = $largura - 2;
    if ($pdf->GetStringWidth($texto) <= $maxW) return $texto;
    while (mb_strlen($texto) > 1 && $pdf->GetStringWidth($texto . '...') > $maxW) {
        $texto = mb_substr($texto, 0, mb_strlen($texto) - 1);
    }
    return $texto . '...';
}

function tabela($pdf, array $headers, array $colWidths, array $rows, $MX, $VERM, $PRETO, $CINZAC, $CW, $MUTED) {
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
    if ($pdf->GetY() + 6.5 + $rowH > 280) {
        $pdf->AddPage();
        cabecalho($pdf, $CW, $MX, $VERM, $MUTED);
    }
    $drawHeader();
    foreach ($rows as $idx => $row) {
        if ($pdf->GetY() + $rowH > 280) {
            $pdf->AddPage();
            cabecalho($pdf, $CW, $MX, $VERM, $MUTED);
            $drawHeader();
        }
        $pdf->SetFont(FONT_MAIN, '', 7.5);
        $pdf->SetTextColor(...$PRETO);
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

$pdf->AddPage();
cabecalho($pdf, $CW, $MX, $VERM, $MUTED);

$pdf->SetFont(FONT_MAIN, 'B', 22);
$pdf->SetTextColor(...$PRETO);
$pdf->Cell($CW, 12, s('Relacao de Clientes'), 0, 1, 'L');
$pdf->SetFont(FONT_MAIN, '', 10.5);
$pdf->SetTextColor(...$MUTED);
$pdf->Cell($CW, 7, s(count($clientes) . ' clientes cadastrados'), 0, 1, 'L');
$pdf->Ln(4);

tabela($pdf,
    ['Razao Social', 'Nome Fantasia', 'CNPJ', 'E-mail'],
    [55, 55, 35, 41],
    array_map(fn($c) => [$c['razao_social'], $c['nome_fantasia'] ?: '-', $c['cnpj'] ?: '-', $c['email'] ?: '-'], $clientes),
    $MX, $VERM, $PRETO, $CINZAC, $CW, $MUTED
);

ob_end_clean();
$nomeArquivo = 'relacao-clientes-impakto-' . date('Y-m-d') . '.pdf';
$pdf->Output('D', $nomeArquivo);
