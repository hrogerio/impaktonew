<?php
/**
 * GET /gestor/midia-kit/pdf
 * Gera o PDF do Mídia Kit institucional — A4 Paisagem.
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
$pdo = getDatabase();

$itens = $pdo->query("SELECT * FROM midia_kit_paginas WHERE ativo = 1 ORDER BY ordem ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Usa tFPDF (suporte nativo a TTF/Unicode) se disponível, senão FPDF padrão
$tfpdfPath    = __DIR__ . '/../../../lib/fpdf/tfpdf.php';
$ttfontsPath  = __DIR__ . '/../../../lib/fpdf/font/unifont/ttfonts.php';
$fpdfPath     = __DIR__ . '/../../../lib/fpdf/fpdf.php';

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

// ── Helpers ───────────────────────────────────────────────────────────────
function s($str) {
    if (defined('USE_TFPDF') && USE_TFPDF) return (string)($str ?? '');
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string)($str ?? ''));
}

// ── Cores ─────────────────────────────────────────────────────────────────
$VERM   = [192, 57,  43 ];
$BRANCO = [255, 255, 255];
$PRETO  = [20,  20,  20 ];
$MUTED  = [140, 140, 155];
$CINZAC = [235, 235, 238];

// ── PDF — A4 Paisagem (297 × 210 mm) ───────────────────────────────────────
if (defined('USE_TFPDF') && USE_TFPDF) {
    $pdf = new tFPDF('L', 'mm', 'A4');
} else {
    $pdf = new FPDF('L', 'mm', 'A4');
}
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);
$pdf->SetCreator('Impakto Mídia OOH');
$pdf->SetTitle(s('Mídia Kit Institucional - Impakto'));

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

$PW = 297; $PH = 210;
$logoColor = __DIR__ . '/../../../public/assets/img/logo.png';
$logoBar   = __DIR__ . '/../../../public/assets/img/barra.png';

// ─────────────────────────────────────────────────────────────────────────
// CAPA
// ─────────────────────────────────────────────────────────────────────────
$pdf->AddPage();
$pdf->SetFillColor(...$BRANCO);
$pdf->Rect(0, 0, $PW, $PH, 'F');

if (file_exists($logoColor)) {
    $pdf->Image($logoColor, 20, 75, 90);
}

$pdf->SetFont(FONT_MAIN, 'B', 15);
$pdf->SetTextColor(...$VERM);
$pdf->SetXY(20, 115);
$pdf->Cell(150, 8, s('Mídia Kit Institucional'), 0, 1, 'L');

$pdf->SetFont(FONT_MAIN, '', 8.5);
$pdf->SetTextColor(...$MUTED);
$pdf->SetXY(20, $PH - 16);
$pdf->Cell(100, 6, s(date('d/m/Y')), 0, 0, 'L');

// ─────────────────────────────────────────────────────────────────────────
// INSTITUCIONAL
// ─────────────────────────────────────────────────────────────────────────
$pdf->AddPage();
$pdf->SetFillColor(...$BRANCO);
$pdf->Rect(0, 0, $PW, $PH, 'F');

$pdf->SetFont(FONT_MAIN, 'B', 26);
$pdf->SetTextColor(...$VERM);
$pdf->SetXY(20, 18);
$pdf->Cell(150, 12, s('Mídia OOH'), 0, 1, 'L');

$pdf->SetFont(FONT_MAIN, 'B', 26);
$pdf->SetTextColor(...$PRETO);
$pdf->SetXY(20, 30);
$pdf->Cell(150, 12, s('Out of Home'), 0, 1, 'L');

$pdf->SetFont(FONT_MAIN, '', 11);
$pdf->SetTextColor(60, 60, 60);
$pdf->SetXY(20, 50);
$pdf->MultiCell(160, 6, s(
    "O principal objetivo da Mídia Out of Home (OOH) é impactar as pessoas enquanto elas estão em movimento, seja durante trajetos por ruas, avenidas e rodovias, ou em locais públicos como praças, shoppings, parques e eventos.\n\n" .
    "Em essência, a Mídia Out of Home pode ser definida como um conjunto estratégico de mensagens projetadas para alcançar consumidores fora de suas residências, conectando marcas ao público em momentos do dia a dia."
), 0, 'L');

$pdf->SetFillColor(...$VERM);
$pdf->Rect(0, $PH - 40, $PW, 24, 'F');
$pdf->SetFont(FONT_MAIN, 'B', 12);
$pdf->SetTextColor(...$BRANCO);
$pdf->SetXY(15, $PH - 36);
$pdf->MultiCell($PW - 30, 6, s('Nossa área de cobertura abrange os estados de Pernambuco, Paraíba, Alagoas e Rio Grande do Norte, garantindo presença e impacto nas principais regiões do Nordeste.'), 0, 'L');

// ─────────────────────────────────────────────────────────────────────────
// PÁGINAS DINÂMICAS — cases e divisores
// ─────────────────────────────────────────────────────────────────────────
foreach ($itens as $item) {
    $pdf->AddPage();

    if ($item['tipo'] === 'divisor') {
        $pdf->SetFillColor(...$BRANCO);
        $pdf->Rect(0, 0, $PW, $PH, 'F');

        $palavras = preg_split('/\s+/', trim($item['titulo']));
        $y = 55;
        foreach ($palavras as $i => $palavra) {
            $cor = ($i % 2 === 0) ? $PRETO : $VERM;
            $pdf->SetFont(FONT_MAIN, 'B', 34);
            $pdf->SetTextColor(...$cor);
            $pdf->SetXY(20, $y);
            $pdf->Cell(200, 16, s($palavra), 0, 1, 'L');
            $y += 17;
        }
        continue;
    }

    // ── Case: header + foto ────────────────────────────────────────────
    $HH = 24; // altura do header (mm)

    $pdf->SetFillColor(...$BRANCO);
    $pdf->Rect(0, 0, $PW, $HH, 'F');

    // Logomarca do cliente — contain-fit numa caixa de até 32×16mm, à esquerda
    $logoW = 0;
    $logoPath = $item['logo'] ? __DIR__ . '/../../../' . $item['logo'] : '';
    if ($logoPath && file_exists($logoPath)) {
        $boxX = 8; $boxY = 4; $boxW = 32; $boxH = 16;
        [$lw, $lh] = @getimagesize($logoPath) ?: [4, 3];
        if ($lw < 1 || $lh < 1) { $lw = 4; $lh = 3; }

        $ratioLogo = $lw / $lh;
        $ratioBox  = $boxW / $boxH;

        if ($ratioLogo >= $ratioBox) {
            $dW = $boxW;
            $dH = $boxW / $ratioLogo;
        } else {
            $dH = $boxH;
            $dW = $boxH * $ratioLogo;
        }
        $pdf->Image($logoPath, $boxX, $boxY + ($boxH - $dH) / 2, $dW, $dH);
        $logoW = $dW;
    }

    // Nome do cliente — ao lado da logo
    $pdf->SetFont(FONT_MAIN, 'B', 14);
    $pdf->SetTextColor(...$VERM);
    $pdf->SetXY(8 + $logoW + 4, 9);
    $pdf->Cell(80, 7, s(strtoupper($item['titulo'])), 0, 0, 'L');

    // Formato do painel (em cima) e localização (embaixo) — centralizados
    $pdf->SetFont(FONT_MAIN, 'B', 10);
    $pdf->SetTextColor(...$PRETO);
    $pdf->SetXY($PW / 2 - 70, 5);
    $pdf->Cell(140, 6, s(strtoupper($item['subtitulo'] ?? '')), 0, 0, 'C');

    if (!empty($item['localizacao'])) {
        $pdf->SetFont(FONT_MAIN, 'B', 11);
        $pdf->SetTextColor(...$VERM);
        $pdf->SetXY($PW / 2 - 70, 13);
        $pdf->Cell(140, 6, s(strtoupper($item['localizacao'])), 0, 0, 'C');
    }

    if (file_exists($logoColor)) {
        $pdf->Image($logoColor, $PW - 55, 4, 45);
    }

    $FOTO_Y = $HH;
    $FOTO_H = $PH - $HH;

    $imgPath = $item['foto'] ? __DIR__ . '/../../../' . $item['foto'] : '';
    if ($imgPath && file_exists($imgPath)) {
        [$iw, $ih] = @getimagesize($imgPath) ?: [4, 3];
        if ($iw < 1 || $ih < 1) { $iw = 4; $ih = 3; }

        $ratioImg  = $iw / $ih;
        $ratioArea = $PW / $FOTO_H;

        if ($ratioImg >= $ratioArea) {
            $dH = $FOTO_H;
            $dW = $FOTO_H * $ratioImg;
            $dX = -($dW - $PW) / 2;
            $dY = $FOTO_Y;
        } else {
            $dW = $PW;
            $dH = $PW / $ratioImg;
            $dX = 0;
            $dY = $FOTO_Y - ($dH - $FOTO_H) / 2;
        }

        $pdf->Image($imgPath, $dX, $dY, $dW, $dH);
    } else {
        $pdf->SetFillColor(240, 241, 245);
        $pdf->Rect(0, $FOTO_Y, $PW, $FOTO_H, 'F');
    }
}

// ─────────────────────────────────────────────────────────────────────────
// ENCERRAMENTO
// ─────────────────────────────────────────────────────────────────────────
$pdf->AddPage();
$pdf->SetFillColor(...$BRANCO);
$pdf->Rect(0, 0, $PW, $PH, 'F');

$pdf->SetFont(FONT_MAIN, 'I', 13);
$pdf->SetTextColor(...$VERM);
$pdf->SetXY(0, 70);
$pdf->Cell($PW, 8, s('Realização'), 0, 1, 'C');

if (file_exists($logoColor)) {
    $pdf->Image($logoColor, $PW / 2 - 45, 82, 90);
}

$pdf->SetFont(FONT_MAIN, 'B', 11);
$pdf->SetTextColor(...$PRETO);
$pdf->SetXY(0, 130);
$pdf->Cell($PW, 7, s('Acesse nosso Instagram'), 0, 1, 'C');

$pdf->SetFont(FONT_MAIN, 'B', 12);
$pdf->SetTextColor(...$VERM);
$pdf->SetXY(0, 138);
$pdf->Cell($PW, 7, s('@impaktomidiaooh'), 0, 1, 'C');

// ── Download ─────────────────────────────────────────────────────────────
ob_end_clean();
$nomeArq = 'MidiaKit_Impakto_' . date('Ymd') . '.pdf';
$pdf->Output('D', $nomeArq);
