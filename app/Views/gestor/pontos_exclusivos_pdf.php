<?php
/**
 * GET /gestor/pontos/exclusivos/pdf
 * Gera PDF de apresentação dos painéis exclusivos (uso interno) — A4 Paisagem.
 * Reaproveita a estrutura visual do PDF de pré-seleção (capa + 1 página por ponto).
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
$pdo = getDatabase();

$pontoIds = array_values(array_filter(array_map('intval', (array)($_GET['pontoIds'] ?? [])), fn($id) => $id > 0));
if (empty($pontoIds)) { ob_end_clean(); http_response_code(400); die('Nenhum painel informado.'); }

// ── Busca pontos exclusivos com foto principal ─────────────────────────────────
$ph   = implode(',', array_fill(0, count($pontoIds), '?'));
$stmt = $pdo->prepare("
    SELECT p.id, p.numero, p.logradouro, p.descricao, p.bairro, p.cidade, p.regiao,
           p.latitude, p.longitude, p.formato, p.cliente_exclusivo, p.liberado_comercializacao,
           COALESCE(c.contato, p.contato) AS contato,
           COALESCE(
               (SELECT pf.caminho FROM ponto_fotos pf WHERE pf.ponto_id = p.id AND pf.principal = 1 LIMIT 1),
               (SELECT pf2.caminho FROM ponto_fotos pf2 WHERE pf2.ponto_id = p.id ORDER BY pf2.ordem ASC, pf2.id ASC LIMIT 1),
               p.foto
           ) AS foto_caminho
    FROM pontos p
    LEFT JOIN campanhas c ON c.ponto_id = p.id AND c.ativo = 1
    WHERE p.id IN ($ph)
      AND p.exclusivo = 1
    ORDER BY p.cliente_exclusivo ASC, p.numero ASC
");
$stmt->execute($pontoIds);
$pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($pontos)) { ob_end_clean(); http_response_code(400); die('Nenhum painel exclusivo encontrado.'); }

// ── Biblioteca PDF ────────────────────────────────────────────────────────────
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

// ── Helpers ───────────────────────────────────────────────────────────────────
function s($str) {
    if (defined('USE_TFPDF') && USE_TFPDF) return (string)($str ?? '');
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string)($str ?? ''));
}

// ── Cores ─────────────────────────────────────────────────────────────────────
$VERM     = [192, 57,  43 ];
$BRANCO   = [255, 255, 255];
$PRETO    = [20,  20,  20 ];
$MUTED    = [140, 140, 155];
$CINZAC   = [235, 235, 238];
$BLOQ_BG  = [30,  41,  59 ]; // #1e293b
$BLOQ_FG  = [248, 250, 252]; // #f8fafc
$LIB_BG   = [120, 53,  15 ]; // #78350f
$LIB_FG   = [254, 243, 199]; // #fef3c7

// ── PDF — A4 Paisagem (297 × 210 mm) ─────────────────────────────────────────
if (defined('USE_TFPDF') && USE_TFPDF) {
    $pdf = new tFPDF('L', 'mm', 'A4');
} else {
    $pdf = new FPDF('L', 'mm', 'A4');
}
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);
$pdf->SetCreator('Impakto Midia OOH');
$pdf->SetTitle(s('Paineis Exclusivos - Uso Interno'));

// Fontes
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
$LW = 80;               // largura painel esquerdo (vermelho)
$nPontos = count($pontos);

// ─────────────────────────────────────────────────────────────────────────────
// CAPA
// ─────────────────────────────────────────────────────────────────────────────
$pdf->AddPage();

// Painel esquerdo vermelho
$pdf->SetFillColor(...$VERM);
$pdf->Rect(0, 0, $LW, $PH, 'F');

// Logo branca
$logoB = __DIR__ . '/../../../public/assets/img/logo_branca.png';
$logoN = __DIR__ . '/../../../public/assets/img/logo.png';
$logoPath = file_exists($logoB) ? $logoB : $logoN;
if (file_exists($logoPath)) {
    $pdf->Image($logoPath, 8, 10, 62);
}

// Linha branca separadora
$pdf->SetDrawColor(...$BRANCO);
$pdf->SetLineWidth(0.4);
$pdf->Line(10, 42, $LW - 10, 42);

// Número de pontos
$pdf->SetFont(FONT_MAIN, 'B', 52);
$pdf->SetTextColor(...$BRANCO);
$pdf->SetXY(0, 94);
$pdf->Cell($LW, 20, (string)$nPontos, 0, 1, 'C');

$pdf->SetFont(FONT_MAIN, '', 11);
$pdf->SetTextColor(255, 200, 200);
$pdf->SetXY(0, 116);
$pdf->Cell($LW, 7, s($nPontos === 1 ? 'painel exclusivo' : 'painéis exclusivos'), 0, 1, 'C');

// Aviso de uso interno
$pdf->SetFont(FONT_MAIN, 'B', 8);
$pdf->SetTextColor(255, 200, 200);
$pdf->SetXY(4, $PH - 26);
$pdf->MultiCell($LW - 8, 5, s('🔒 Documento de uso interno — não enviar ao cliente.'), 0, 'C');

// Data de emissão
$pdf->SetFont(FONT_MAIN, '', 8.5);
$pdf->SetTextColor(255, 180, 180);
$pdf->SetXY(0, $PH - 10);
$pdf->Cell($LW, 6, s(date('d/m/Y')), 0, 1, 'C');

// ── Painel direito branco ─────────────────────────────────────────────────────
$pdf->SetFillColor(...$BRANCO);
$pdf->Rect($LW, 0, $PW - $LW, $PH, 'F');

$RX = $LW + 10;
$RW = $PW - $LW - 14;

// "RELATÓRIO INTERNO" — título pequeno
$pdf->SetFont(FONT_MAIN, 'B', 20);
$pdf->SetTextColor(...$MUTED);
$pdf->SetXY($RX, 13);
$pdf->Cell($RW, 11, s('RELATÓRIO INTERNO'), 0, 1, 'L');

// Linha vermelha
$pdf->SetFillColor(...$VERM);
$pdf->Rect($RX, 27, 60, 1.2, 'F');

// Título em destaque
$pdf->SetFont(FONT_MAIN, 'B', 27);
$pdf->SetTextColor(...$PRETO);
$pdf->SetXY($RX, 31);
$pdf->MultiCell($RW, 13, s('Painéis Exclusivos'), 0, 'L');
$yApos = $pdf->GetY() + 4;

$campos = [
    ['Situação:', 'Bloqueados para outros clientes'],
    ['Gerado em:', date('d/m/Y \à\s H:i')],
    ['Pontos:',   (string)$nPontos],
];
$y = max($yApos, 74);
foreach ($campos as [$lbl, $val]) {
    $pdf->SetFont(FONT_MAIN, 'B', 11);
    $pdf->SetTextColor(...$MUTED);
    $pdf->SetXY($RX, $y);
    $pdf->Cell(32, 7, s($lbl), 0, 0, 'L');

    $pdf->SetFont(FONT_MAIN, 'B', 13);
    $pdf->SetTextColor(...$PRETO);
    $pdf->SetXY($RX + 32, $y);
    $pdf->Cell($RW - 32, 7, s($val), 0, 1, 'L');
    $y += 12;
}

// ── Lista de pontos (2 colunas) ───────────────────────────────────────────────
$y += 2;
$altPonto    = 9.0;
$yMax        = $PH - 10;

if ($y < $yMax - 15) {
    $pdf->SetFont(FONT_MAIN, 'B', 8.5);
    $pdf->SetTextColor(...$MUTED);
    $pdf->SetXY($RX, $y);
    $pdf->Cell($RW, 6, 'Pontos', 0, 1, 'L');
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

        $pdf->SetFont(FONT_MAIN, 'B', 9.5);
        $pdf->SetTextColor(...$VERM);
        $pdf->SetXY($xCol, $yAtual);
        $pdf->Cell(18, 5, '#' . str_pad($p['numero'], 3, '0', STR_PAD_LEFT), 0, 0, 'L');

        $pdf->SetFont(FONT_MAIN, '', 8.5);
        $pdf->SetTextColor(...$PRETO);
        $pdf->SetXY($xCol + 18, $yAtual);
        $enderecoStr = implode(' - ', array_filter([$p['logradouro'] ?? '', $p['cidade'] ?? '']));
        $linha = $enderecoStr . ' — ' . ($p['cliente_exclusivo'] ?: '—');
        $pdf->Cell($largCol - 20, 5, s($linha), 0, 1, 'L');
        $yCol[$colAtual] += $altPonto;
    }

    if ($restantes > 0) {
        $yFim = max($yCol[0], isset($yCol[1]) ? $yCol[1] : 0) + 1;
        if ($yFim <= $yMax) {
            $pdf->SetFont(FONT_MAIN, 'I', 8.5);
            $pdf->SetTextColor(...$MUTED);
            $pdf->SetXY($RX, $yFim);
            $pdf->Cell($RW, 5, s("... e mais {$restantes} " . ($restantes === 1 ? 'ponto' : 'pontos')), 0, 1, 'L');
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PÁGINAS DE FOTOS — uma por ponto
// ─────────────────────────────────────────────────────────────────────────────
$FH     = 42;
$FOTO_H = $PH - $FH;

foreach ($pontos as $ponto) {
    $pdf->AddPage();

    $fotoCaminho = $ponto['foto_caminho'] ?? '';
    $imgPath = $fotoCaminho ? __DIR__ . '/../../../' . ltrim($fotoCaminho, '/') : '';

    if ($imgPath && file_exists($imgPath)) {
        [$iw, $ih] = @getimagesize($imgPath) ?: [4, 3];
        if ($iw < 1 || $ih < 1) { $iw = 4; $ih = 3; }

        $ratioImg  = $iw / $ih;
        $ratioPage = $PW / $FOTO_H;

        if ($ratioImg >= $ratioPage) {
            $dH = $FOTO_H;
            $dW = $FOTO_H * $ratioImg;
            $dX = -($dW - $PW) / 2;
            $dY = 0;
        } else {
            $dW = $PW;
            $dH = $PW / $ratioImg;
            $dX = 0;
            $dY = -($dH - $FOTO_H) / 2;
        }

        $pdf->Image($imgPath, $dX, $dY, $dW, $dH);
    } else {
        $pdf->SetFillColor(240, 241, 245);
        $pdf->Rect(0, 0, $PW, $FOTO_H, 'F');
        $pdf->SetFillColor(220, 180, 175);
        $pdf->Rect(0, $FOTO_H / 2 - 18, $PW, 0.6, 'F');
        $pdf->Rect(0, $FOTO_H / 2 + 18, $PW, 0.6, 'F');
        $pdf->SetFont(FONT_MAIN, 'B', 15);
        $pdf->SetTextColor(160, 80, 70);
        $pdf->SetXY(0, $FOTO_H / 2 - 14);
        $pdf->Cell($PW, 10, s('FOTO NÃO DISPONÍVEL'), 0, 0, 'C');
        $pdf->SetFont(FONT_MAIN, '', 10);
        $pdf->SetTextColor(160, 155, 165);
        $pdf->SetXY(0, $FOTO_H / 2 + 4);
        $pdf->Cell($PW, 7, s('Nenhuma foto cadastrada para este ponto'), 0, 0, 'C');
    }

    // ── Rodapé ────────────────────────────────────────────────────────────────
    $FY = $FOTO_H;

    $pdf->SetFillColor(...$BRANCO);
    $pdf->Rect(0, $FY, $PW, $FH, 'F');

    $pdf->SetFillColor(...$CINZAC);
    $pdf->Rect(0, $FY, $PW, 0.6, 'F');

    $pdf->SetFillColor(...$VERM);
    $pdf->Rect(0, $FY, 4, $FH, 'F');

    // Número do painel
    $pdf->SetFont(FONT_MAIN, 'B', 16);
    $pdf->SetTextColor(...$VERM);
    $pdf->SetXY(7, $FY + 8);
    $pdf->Cell(24, 8, '#' . str_pad($ponto['numero'], 3, '0', STR_PAD_LEFT), 0, 0, 'L');

    // Logradouro
    $pdf->SetFont(FONT_MAIN, 'B', 13);
    $pdf->SetTextColor(...$PRETO);
    $pdf->SetXY(33, $FY + 5);
    $pdf->Cell(108, 6, s($ponto['logradouro'] ?? ''), 0, 1, 'L');

    // Descrição
    if (!empty($ponto['descricao'])) {
        $pdf->SetFont(FONT_MAIN, '', 10);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetXY(33, $FY + 12);
        $pdf->Cell(108, 5, s($ponto['descricao']), 0, 0, 'L');
    }

    // Bairro · Cidade
    $loc = implode(' - ', array_filter([$ponto['bairro'] ?? '', $ponto['cidade'] ?? ''], fn($v) => trim($v, " \t-") !== ''));
    $pdf->SetFont(FONT_MAIN, 'B', 12);
    $pdf->SetTextColor(...$PRETO);
    $pdf->SetXY(33, $FY + 19);
    $pdf->Cell(108, 5, s($loc), 0, 0, 'L');

    // Cliente exclusivo
    $pdf->SetFont(FONT_MAIN, 'B', 11);
    $pdf->SetTextColor(...$VERM);
    $pdf->SetXY(33, $FY + 27);
    $pdf->Cell(108, 5, s('🔒 ' . ($ponto['cliente_exclusivo'] ?: '—')), 0, 0, 'L');

    // Formato · Contato
    $formatoContato = implode('   ·   ', array_filter([
        $ponto['formato'] ? 'Formato: ' . $ponto['formato'] : '',
        $ponto['contato'] ? 'Contato: ' . $ponto['contato'] : '',
    ]));
    if ($formatoContato) {
        $pdf->SetFont(FONT_MAIN, '', 9);
        $pdf->SetTextColor(...$MUTED);
        $pdf->SetXY(33, $FY + 34);
        $pdf->Cell(108, 5, s($formatoContato), 0, 0, 'L');
    }

    // Badge de comercialização
    $liberado = (int)$ponto['liberado_comercializacao'] === 1;
    $badgeTxt = $liberado ? 'LIBERADO P/ COMERCIALIZAÇÃO' : 'BLOQUEADO';
    $badgeBg  = $liberado ? $LIB_BG : $BLOQ_BG;
    $badgeFg  = $liberado ? $LIB_FG : $BLOQ_FG;
    $pdf->SetFont(FONT_MAIN, 'B', 9);
    $badgeW = $pdf->GetStringWidth(s($badgeTxt)) + 8;
    $pdf->SetFillColor(...$badgeBg);
    $pdf->SetTextColor(...$badgeFg);
    $pdf->SetXY(148, $FY + 30);
    $pdf->Cell($badgeW, 6, s($badgeTxt), 0, 0, 'C', true);

    // ── Botão Google Maps ─────────────────────────────────────────────────────
    if (!empty($ponto['latitude']) && !empty($ponto['longitude'])) {
        $mapsUrl = 'https://maps.google.com/?q=' . $ponto['latitude'] . ',' . $ponto['longitude'];
        $coords  = number_format((float)$ponto['latitude'], 6) . ', ' . number_format((float)$ponto['longitude'], 6);

        $btnH = 12;
        $btnY = $FY + 6;
        $padL = 6; $arrowW = 9; $gap = 3; $padR = 7;
        $pdf->SetFont(FONT_MAIN, 'B', 11);
        $txtW = $pdf->GetStringWidth('Ver no Google Maps');
        $btnW = $padL + $arrowW + $gap + $txtW + $padR;
        $btnX = $PW - 35 - $btnW - 6;

        $pdf->SetFillColor(...$BRANCO);
        $pdf->Rect($btnX, $btnY, $btnW, $btnH, 'F');
        $pdf->SetDrawColor(180, 185, 200);
        $pdf->SetLineWidth(0.35);
        $pdf->Rect($btnX, $btnY, $btnW, $btnH);

        $pdf->SetFont(FONT_MAIN, 'B', 10);
        $pdf->SetTextColor(...$VERM);
        $pdf->SetXY($btnX + $padL, $btnY + 2.4);
        $pdf->Cell($arrowW, 7, s('↗'), 0, 0, 'L');

        $pdf->SetFont(FONT_MAIN, 'B', 11);
        $pdf->SetTextColor(40, 45, 60);
        $pdf->SetXY($btnX + $padL + $arrowW + $gap, $btnY + 2.4);
        $pdf->Cell($txtW + $padR, 7, s('Ver no Google Maps'), 0, 0, 'L', false, $mapsUrl);

        $pdf->Link($btnX, $btnY, $btnW, $btnH, $mapsUrl);

        $pdf->SetFont(FONT_MAIN, '', 8);
        $pdf->SetTextColor(...$MUTED);
        $pdf->SetXY($btnX, $btnY + $btnH + 1.5);
        $pdf->Cell($btnW, 4, $coords, 0, 0, 'C');
    }

    // Logo Impakto — canto direito
    $logoRodape = __DIR__ . '/../../../public/assets/img/logo.png';
    if (file_exists($logoRodape)) {
        $pdf->Image($logoRodape, $PW - 32, $FY + 32, 27);
    }
}

// ── Download ──────────────────────────────────────────────────────────────────
ob_end_clean();
$nomeArq = 'PaineisExclusivos_' . date('Ymd') . '.pdf';
$pdf->Output('D', $nomeArq);
