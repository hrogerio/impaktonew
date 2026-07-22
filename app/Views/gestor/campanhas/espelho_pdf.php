<?php
/**
 * GET /gestor/campanhas/espelho/pdf
 * Gera PDF Espelho de Colagem — A4 Retrato, 4 pontos por página (grid 2×2).
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

require_once __DIR__ . '/../../../../config/database.php';
$pdo = getDatabase();

$cliente  = trim($_GET['cliente']  ?? '');
$agencia  = trim($_GET['agencia']  ?? '');
$campanha = trim($_GET['campanha'] ?? '');
$situacao = trim($_GET['situacao'] ?? 'Ocupado');
$inicio   = trim($_GET['inicio']   ?? '') ?: null;
$fim      = trim($_GET['fim']      ?? '') ?: null;
$pontoIds = array_values(array_filter(array_map('intval', (array)($_GET['pontoIds'] ?? [])), fn($id) => $id > 0));

if (empty($pontoIds)) { ob_end_clean(); http_response_code(400); die('Nenhum painel informado.'); }

// Busca pontos ordenados por número
$ph   = implode(',', array_fill(0, count($pontoIds), '?'));
$stmt = $pdo->prepare("SELECT id, numero, logradouro, descricao, bairro, cidade, regiao FROM pontos WHERE id IN ($ph) ORDER BY numero ASC");
$stmt->execute($pontoIds);
$pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Busca foto principal (ou primeira) de cada ponto
$stmt2 = $pdo->prepare("SELECT ponto_id, caminho FROM ponto_fotos WHERE ponto_id IN ($ph) ORDER BY principal DESC, ordem ASC, id ASC");
$stmt2->execute($pontoIds);
$fotosPorPonto = [];
foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $f) {
    if (!isset($fotosPorPonto[$f['ponto_id']])) {
        $fotosPorPonto[$f['ponto_id']] = $f['caminho'];
    }
}

// ── Biblioteca PDF ────────────────────────────────────────────────────────────
$tfpdfPath   = __DIR__ . '/../../../../lib/fpdf/tfpdf.php';
$ttfontsPath = __DIR__ . '/../../../../lib/fpdf/font/unifont/ttfonts.php';
$fpdfPath    = __DIR__ . '/../../../../lib/fpdf/fpdf.php';

define('FPDF_FONTPATH', __DIR__ . '/../../../../lib/fpdf/font/');

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

// ── Helpers ───────────────────────────────────────────────────────────────────
function s($str) {
    if (defined('USE_TFPDF') && USE_TFPDF) return (string)($str ?? '');
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string)($str ?? ''));
}
function dataFmt($d) {
    if (!$d) return '-';
    try { return (new DateTime($d))->format('d/m/Y'); } catch (Exception $e) { return $d; }
}

// ── Cores ─────────────────────────────────────────────────────────────────────
$VERM   = [192, 57,  43 ];
$BRANCO = [255, 255, 255];
$PRETO  = [20,  20,  20 ];
$MUTED  = [140, 140, 155];
$CINZAC = [235, 235, 238];
$CINZAF = [245, 245, 248];

// ── PDF — A4 Retrato (210 × 297 mm) ──────────────────────────────────────────
if (defined('USE_TFPDF') && USE_TFPDF) {
    $pdf = new tFPDF('P', 'mm', 'A4');
} else {
    $pdf = new FPDF('P', 'mm', 'A4');
}
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);
$pdf->SetCreator('Impakto Midia OOH');
$pdf->SetTitle(s('Espelho de Colagem - ' . $cliente));

// Fontes
if (defined('USE_TFPDF') && USE_TFPDF) {
    $fontDir = __DIR__ . '/../../../../lib/fpdf/font/unifont/';
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

$PW = 210; $PH = 297;   // A4 retrato
$LW = 70;               // largura painel esquerdo (vermelho) — capa

// ─────────────────────────────────────────────────────────────────────────────
// CAPA
// ─────────────────────────────────────────────────────────────────────────────
$pdf->AddPage();
$nPontos = count($pontos);

// Painel esquerdo vermelho
$pdf->SetFillColor(...$VERM);
$pdf->Rect(0, 0, $LW, $PH, 'F');

// Logo branca
$logoB = __DIR__ . '/../../../../public/assets/img/logo_branca.png';
$logoN = __DIR__ . '/../../../../public/assets/img/logo.png';
$logoPath = file_exists($logoB) ? $logoB : $logoN;
if (file_exists($logoPath)) {
    $pdf->Image($logoPath, 7, 10, 54);
}

// Linha branca separadora
$pdf->SetDrawColor(...$BRANCO);
$pdf->SetLineWidth(0.4);
$pdf->Line(9, 38, $LW - 9, 38);

// Número de pontos
$pdf->SetFont(FONT_MAIN, 'B', 48);
$pdf->SetTextColor(...$BRANCO);
$pdf->SetXY(0, 110);
$pdf->Cell($LW, 18, (string)$nPontos, 0, 1, 'C');

$pdf->SetFont(FONT_MAIN, '', 10);
$pdf->SetTextColor(255, 200, 200);
$pdf->SetXY(0, 130);
$pdf->Cell($LW, 6, s($nPontos === 1 ? 'ponto' : 'pontos'), 0, 1, 'C');

// Data de emissão
$pdf->SetFont(FONT_MAIN, '', 8);
$pdf->SetTextColor(255, 180, 180);
$pdf->SetXY(0, $PH - 10);
$pdf->Cell($LW, 6, s(date('d/m/Y')), 0, 1, 'C');

// Painel direito branco
$pdf->SetFillColor(...$BRANCO);
$pdf->Rect($LW, 0, $PW - $LW, $PH, 'F');

$RX = $LW + 10;
$RW = $PW - $LW - 14;

// "ESPELHO DE COLAGEM"
$pdf->SetFont(FONT_MAIN, 'B', 16);
$pdf->SetTextColor(...$MUTED);
$pdf->SetXY($RX, 13);
$pdf->Cell($RW, 10, s('ESPELHO DE COLAGEM'), 0, 1, 'L');

// Linha vermelha
$pdf->SetFillColor(...$VERM);
$pdf->Rect($RX, 26, 50, 1.2, 'F');

// Nome do cliente
$pdf->SetFont(FONT_MAIN, 'B', 22);
$pdf->SetTextColor(...$PRETO);
$pdf->SetXY($RX, 30);
$pdf->MultiCell($RW, 11, s($cliente), 0, 'L');
$yApos = $pdf->GetY() + 4;

// Campos de informação
$campos = [
    ['Campanha:', $campanha ?: '-'],
    ['Agencia:',  $agencia  ?: '-'],
    ['Periodo:',  dataFmt($inicio) . ' a ' . dataFmt($fim)],
    ['Pontos:',   (string)$nPontos],
];
$y = max($yApos, 70);
foreach ($campos as [$lbl, $val]) {
    $pdf->SetFont(FONT_MAIN, 'B', 9.5);
    $pdf->SetTextColor(...$MUTED);
    $pdf->SetXY($RX, $y);
    $pdf->Cell(28, 7, s($lbl), 0, 0, 'L');

    $pdf->SetFont(FONT_MAIN, 'B', 11);
    $pdf->SetTextColor(...$PRETO);
    $pdf->SetXY($RX + 28, $y);
    $pdf->Cell($RW - 28, 7, s($val), 0, 1, 'L');
    $y += 11;
}

// Lista de pontos na capa
$y += 3;
$altPonto = 8.5;
$yMax     = $PH - 10;

if ($y < $yMax - 15) {
    $pdf->SetFont(FONT_MAIN, 'B', 8);
    $pdf->SetTextColor(...$MUTED);
    $pdf->SetXY($RX, $y);
    $pdf->Cell($RW, 6, 'Paineis aprovados', 0, 1, 'L');
    $y += 7;

    $espacoDisp   = $yMax - $y;
    $maxPorColuna = max(1, (int)floor($espacoDisp / $altPonto));
    $numCols      = ($nPontos > $maxPorColuna) ? 2 : 1;
    $largCol      = $RW / $numCols;
    $maxTotal     = $numCols * $maxPorColuna;

    $pontosExibidos = array_slice($pontos, 0, $maxTotal);
    $restantes      = count($pontos) - count($pontosExibidos);
    $yCol = [$y, $y];

    foreach ($pontosExibidos as $i => $p) {
        $colAtual = ($numCols === 2 && $i >= $maxPorColuna) ? 1 : 0;
        $xCol     = $RX + $colAtual * $largCol;
        $yAtual   = $yCol[$colAtual];
        if ($yAtual > $yMax) break;

        $pdf->SetFont(FONT_MAIN, 'B', 8.5);
        $pdf->SetTextColor(...$VERM);
        $pdf->SetXY($xCol, $yAtual);
        $pdf->Cell(16, 5, '#' . str_pad($p['numero'], 3, '0', STR_PAD_LEFT), 0, 0, 'L');

        $pdf->SetFont(FONT_MAIN, '', 8.5);
        $pdf->SetTextColor(...$PRETO);
        $pdf->SetXY($xCol + 16, $yAtual);
        $endStr = implode(' - ', array_filter([$p['logradouro'] ?? '', $p['cidade'] ?? '']));
        $pdf->Cell($largCol - 18, 5, s($endStr), 0, 1, 'L');

        $yCol[$colAtual] += $altPonto;
    }

    if ($restantes > 0) {
        $yFim = max($yCol[0], isset($yCol[1]) ? $yCol[1] : 0) + 1;
        if ($yFim <= $yMax) {
            $pdf->SetFont(FONT_MAIN, 'I', 8);
            $pdf->SetTextColor(...$MUTED);
            $pdf->SetXY($RX, $yFim);
            $pdf->Cell($RW, 5, s("... e mais {$restantes} " . ($restantes === 1 ? 'ponto' : 'pontos')), 0, 1, 'L');
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PÁGINAS DE PONTOS — grid 2 × 2
// ─────────────────────────────────────────────────────────────────────────────
// Dimensões do grid
$MAR    = 10;    // margem externa
$GAP_H  = 5;     // espaço horizontal entre colunas
$GAP_V  = 6;     // espaço vertical entre linhas
$HDR_H  = 12;    // cabeçalho topo da página (faixa com nome campanha)

$CELL_W = ($PW - $MAR * 2 - $GAP_H) / 2;               // ~92.5mm
$CELL_H = ($PH - $MAR * 2 - $GAP_V - $HDR_H) / 2;      // ~129.5mm

$FOTO_H = $CELL_H - 38;    // área da foto (~91mm)
$INFO_H = 38;               // rodapé info (~38mm)

// Função para desenhar uma célula de ponto
function desenharCelula($pdf, $x, $y, $cw, $ch, $fotoH, $infoH, $ponto, $fotoPath, $fontes, $cores) {
    [$VERM, $BRANCO, $PRETO, $MUTED, $CINZAC, $CINZAF] = $cores;
    [,,$PRETO,,,$CINZAF] = $cores;

    $infoY = $y + $fotoH;

    // ── Foto ──────────────────────────────────────────────────────────────────
    // Clipping: desenha um retângulo de fundo, depois a imagem sobreposta
    $pdf->SetFillColor(...$CINZAF);
    $pdf->Rect($x, $y, $cw, $fotoH, 'F');

    if ($fotoPath && file_exists($fotoPath)) {
        [$iw, $ih] = @getimagesize($fotoPath) ?: [4, 3];
        if ($iw < 1 || $ih < 1) { $iw = 4; $ih = 3; }

        // Fit (contain): a foto deve caber inteira, centralizada
        $ratioImg  = $iw / $ih;
        $ratioCel  = $cw / $fotoH;

        if ($ratioImg >= $ratioCel) {
            // Mais larga que a célula: ajustar pela largura
            $dW = $cw;
            $dH = $cw / $ratioImg;
            $dX = $x;
            $dY = $y + ($fotoH - $dH) / 2;
        } else {
            // Mais alta: ajustar pela altura
            $dH = $fotoH;
            $dW = $fotoH * $ratioImg;
            $dX = $x + ($cw - $dW) / 2;
            $dY = $y;
        }

        $pdf->Image($fotoPath, $dX, $dY, $dW, $dH);
    } else {
        // Placeholder sem foto
        $pdf->SetFont($fontes['main'], '', 8.5);
        $pdf->SetTextColor(180, 180, 190);
        $pdf->SetXY($x, $y + $fotoH / 2 - 4);
        $pdf->Cell($cw, 8, s('Sem foto'), 0, 0, 'C');
    }

    // ── Rodapé de informações ─────────────────────────────────────────────────
    $pdf->SetFillColor(...$BRANCO);
    $pdf->Rect($x, $infoY, $cw, $infoH, 'F');

    // Linha cinza topo do rodapé
    $pdf->SetFillColor(...$CINZAC);
    $pdf->Rect($x, $infoY, $cw, 0.5, 'F');

    // Faixa vermelha esquerda
    $pdf->SetFillColor(...$VERM);
    $pdf->Rect($x, $infoY, 3, $infoH, 'F');

    $xi = $x + 6;  // x de início do texto
    $wi = $cw - 8; // largura útil do texto

    // Número
    $pdf->SetFont($fontes['main'], 'B', 13);
    $pdf->SetTextColor(...$VERM);
    $pdf->SetXY($xi, $infoY + 7);
    $pdf->Cell($wi, 6, '#' . str_pad($ponto['numero'], 3, '0', STR_PAD_LEFT), 0, 1, 'L');

    // Logradouro
    $pdf->SetFont($fontes['main'], 'B', 9.5);
    $pdf->SetTextColor(...$PRETO);
    $pdf->SetXY($xi, $infoY + 15);
    $pdf->MultiCell($wi, 5, s($ponto['logradouro'] ?? ''), 0, 'L');
    $yApos = min($pdf->GetY(), $infoY + 25);

    // Descrição
    if (!empty($ponto['descricao'])) {
        $pdf->SetFont($fontes['main'], '', 8);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->SetXY($xi, $yApos);
        $pdf->Cell($wi, 4.5, s($ponto['descricao']), 0, 1, 'L');
        $yApos += 5;
    }

    // Cidade
    $pdf->SetFont($fontes['main'], 'B', 8.5);
    $pdf->SetTextColor(...$MUTED);
    $pdf->SetXY($xi, $infoY + $infoH - 10);
    $pdf->Cell($wi, 5, s($ponto['cidade'] ?? ''), 0, 0, 'L');
}

// Agrupa pontos em páginas de 4
$chunks = array_chunk($pontos, 4);

foreach ($chunks as $pagPontos) {
    $pdf->AddPage();

    // Cabeçalho da página
    $pdf->SetFillColor(...$VERM);
    $pdf->Rect(0, 0, $PW, $HDR_H, 'F');

    // Logo pequena no cabeçalho
    $logoH = __DIR__ . '/../../../../public/assets/img/logo_branca.png';
    if (!file_exists($logoH)) $logoH = __DIR__ . '/../../../../public/assets/img/logo.png';
    if (file_exists($logoH)) {
        $pdf->Image($logoH, $MAR, 1.5, 28);
    }

    $pdf->SetFont(FONT_MAIN, 'B', 7.5);
    $pdf->SetTextColor(...$BRANCO);
    $pdf->SetXY(42, 3.5);
    $pdf->Cell($PW - 42 - $MAR, 5, s('ESPELHO DE COLAGEM · ' . strtoupper($cliente) . ($campanha ? ' · ' . strtoupper($campanha) : '')), 0, 0, 'L');

    // Data no canto direito do header
    $pdf->SetFont(FONT_MAIN, '', 7);
    $pdf->SetTextColor(255, 200, 200);
    $pdf->SetXY(0, 3.5);
    $pdf->Cell($PW - $MAR, 5, s(date('d/m/Y')), 0, 0, 'R');

    // Posições do grid 2×2
    $posicoes = [
        [$MAR,              $HDR_H + $MAR / 2],
        [$MAR + $CELL_W + $GAP_H, $HDR_H + $MAR / 2],
        [$MAR,              $HDR_H + $MAR / 2 + $CELL_H + $GAP_V],
        [$MAR + $CELL_W + $GAP_H, $HDR_H + $MAR / 2 + $CELL_H + $GAP_V],
    ];

    $fontes = ['main' => FONT_MAIN];
    $cores  = [$VERM, $BRANCO, $PRETO, $MUTED, $CINZAC, $CINZAF];

    foreach ($pagPontos as $idx => $ponto) {
        [$cx, $cy] = $posicoes[$idx];
        $caminhoFoto = $fotosPorPonto[$ponto['id']] ?? null;
        $fotoAbsPath = $caminhoFoto ? __DIR__ . '/../../../../' . $caminhoFoto : null;

        desenharCelula($pdf, $cx, $cy, $CELL_W, $CELL_H, $FOTO_H, $INFO_H, $ponto, $fotoAbsPath, $fontes, $cores);
    }

    // Rodapé discreto
    $pdf->SetFont(FONT_MAIN, '', 6.5);
    $pdf->SetTextColor(...$MUTED);
    $pdf->SetXY(0, $PH - 5);
    $pdf->Cell($PW, 4, s('Impakto Midia OOH · ' . date('d/m/Y')), 0, 0, 'C');
}

// ── Download ──────────────────────────────────────────────────────────────────
ob_end_clean();
$nomeArq = 'Espelho_' . preg_replace('/[^a-zA-Z0-9]/', '_', $cliente) . '_' . date('Ymd') . '.pdf';
$pdf->Output('D', $nomeArq);
