<?php
/**
 * GET /gestor/campanhas/checking/pdf
 * Gera PDF de checking fotográfico — A4 Paisagem.
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

$cliente     = trim($_GET['cliente']  ?? '');
$agencia     = trim($_GET['agencia']  ?? '');
$campanha    = trim($_GET['campanha'] ?? '');
$nomeProjeto = trim($_GET['nome_projeto'] ?? '');
$situacao    = trim($_GET['situacao'] ?? 'Ocupado');
$inicio   = trim($_GET['inicio']   ?? '') ?: null;
$fim      = trim($_GET['fim']      ?? '') ?: null;
$pontoIds = array_values(array_filter(array_map('intval', (array)($_GET['pontoIds'] ?? [])), fn($id) => $id > 0));

if (empty($pontoIds)) { ob_end_clean(); http_response_code(400); die('Nenhum painel informado.'); }

// Busca pontos
$ph   = implode(',', array_fill(0, count($pontoIds), '?'));
$stmt = $pdo->prepare("SELECT id, numero, logradouro, descricao, bairro, cidade, regiao, latitude, longitude FROM pontos WHERE id IN ($ph) ORDER BY cidade ASC, numero ASC");
$stmt->execute($pontoIds);
$pontos = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), null, 'id');

// Busca fotos dos pontos que já foram instalados
$stmt2 = $pdo->prepare("SELECT cf.*, p.numero, p.logradouro, p.descricao, p.bairro, p.cidade, p.regiao, p.latitude, p.longitude FROM checking_fotos cf JOIN pontos p ON p.id = cf.ponto_id WHERE cf.cliente=? AND cf.campanha=? AND cf.situacao=? AND cf.inicio<=>? AND cf.fim<=>? AND cf.ponto_id IN ($ph) ORDER BY p.cidade ASC, p.numero ASC, cf.ordem ASC, cf.id ASC");
$stmt2->execute(array_merge([$cliente, $campanha, $situacao, $inicio, $fim], $pontoIds));
$todasFotos = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Adiciona placeholder para pontos SEM foto (pré-checking / instalação pendente)
$pontosComFoto  = array_unique(array_column($todasFotos, 'ponto_id'));
$instaladosSet  = array_flip($pontosComFoto);
$nInstalados    = count($pontosComFoto);
foreach ($pontoIds as $pid) {
    if (!in_array($pid, $pontosComFoto) && isset($pontos[$pid])) {
        $p = $pontos[$pid];
        $todasFotos[] = [
            'ponto_id'   => $p['id'],
            'caminho'    => null,
            'numero'     => $p['numero'],
            'logradouro' => $p['logradouro'],
            'descricao'  => $p['descricao'],
            'bairro'     => $p['bairro'],
            'cidade'     => $p['cidade'],
            'regiao'     => $p['regiao'],
            'latitude'   => $p['latitude'],
            'longitude'  => $p['longitude'],
        ];
    }
}

// Instalados primeiro, depois pendentes — dentro de cada grupo, por cidade e número
usort($todasFotos, function($a, $b) {
    $aInstalado = !empty($a['caminho']) ? 0 : 1;
    $bInstalado = !empty($b['caminho']) ? 0 : 1;
    if ($aInstalado !== $bInstalado) return $aInstalado - $bInstalado;
    $c = strcmp($a['cidade'] ?? '', $b['cidade'] ?? '');
    if ($c !== 0) return $c;
    return ($a['numero'] ?? 0) <=> ($b['numero'] ?? 0);
});

if (empty($todasFotos)) { ob_end_clean(); http_response_code(400); die('Nenhum painel encontrado.'); }

// Usa tFPDF (suporte nativo a TTF/Unicode) se disponível, senão FPDF padrão
$tfpdfPath    = __DIR__ . '/../../../../lib/fpdf/tfpdf.php';
$ttfontsPath  = __DIR__ . '/../../../../lib/fpdf/font/unifont/ttfonts.php';
$fpdfPath     = __DIR__ . '/../../../../lib/fpdf/fpdf.php';

// Diretório das fontes TTF (para tFPDF) — deve ser definido ANTES de require tfpdf
define('FPDF_FONTPATH', __DIR__ . '/../../../../lib/fpdf/font/');

if (file_exists($tfpdfPath)) {
    // TTFontFile é necessário para tFPDF processar fontes TTF Unicode
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
function dataFmt($d) {
    if (!$d) return '-';
    try { return (new DateTime($d))->format('d/m/Y'); } catch (Exception $e) { return $d; }
}
function ckWrapLines($pdf, $text, $maxW) {
    $words = preg_split('/\s+/', trim($text));
    $lines = []; $cur = '';
    foreach ($words as $w) {
        $test = $cur === '' ? $w : $cur . ' ' . $w;
        if ($pdf->GetStringWidth($test) > $maxW && $cur !== '') {
            $lines[] = $cur;
            $cur = $w;
        } else {
            $cur = $test;
        }
    }
    if ($cur !== '') $lines[] = $cur;
    return $lines;
}

// ── Cores ─────────────────────────────────────────────────────────────────────
$VERM   = [192, 57,  43 ];   // vermelho Impakto
$BRANCO = [255, 255, 255];
$PRETO  = [20,  20,  20 ];
$MUTED  = [140, 140, 155];
$CINZAC = [235, 235, 238];

// ── PDF — A4 Paisagem (297 × 210 mm) ─────────────────────────────────────────
if (defined('USE_TFPDF') && USE_TFPDF) {
    $pdf = new tFPDF('L', 'mm', 'A4');
} else {
    $pdf = new FPDF('L', 'mm', 'A4');
}
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);
$pdf->SetCreator('Impakto Mídia OOH');
$pdf->SetTitle(s('Checking - ' . ($nomeProjeto ?: $campanha ?: $cliente)));

// Carrega fontes Inter (tFPDF) ou fallback Helvetica (FPDF)
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

$PW = 297; $PH = 210;   // dimensões paisagem
$LW = 80;               // largura painel esquerdo (vermelho)

// ─────────────────────────────────────────────────────────────────────────────
// CAPA
// ─────────────────────────────────────────────────────────────────────────────
$pdf->AddPage();

// Painel esquerdo vermelho
$pdf->SetFillColor(...$VERM);
$pdf->Rect(0, 0, $LW, $PH, 'F');

// Logo BRANCA no painel esquerdo
$logoB = __DIR__ . '/../../../../public/assets/img/logo_branca.png';
$logoN = __DIR__ . '/../../../../public/assets/img/barra.png';
$logoPath = file_exists($logoB) ? $logoB : $logoN;
if (file_exists($logoPath)) {
    $pdf->Image($logoPath, 8, 10, 62);   // ↑ era 56mm
}

// Linha branca separadora
$pdf->SetDrawColor(...$BRANCO);
$pdf->SetLineWidth(0.4);
$pdf->Line(10, 42, $LW - 10, 42);       // ↑ era y=40

// Número de pontos
$nPontos = count($pontoIds);
$pdf->SetFont(FONT_MAIN, 'B', 52);       // ↑ era 42
$pdf->SetTextColor(...$BRANCO);
$pdf->SetXY(0, 94);                      // ↑ era y=100
$pdf->Cell($LW, 20, (string)$nPontos, 0, 1, 'C');

$pdf->SetFont(FONT_MAIN, '', 11);        // ↑ era 9
$pdf->SetTextColor(255, 200, 200);
$pdf->SetXY(0, 116);                     // ↑ era y=120
$pdf->Cell($LW, 7, s($nPontos === 1 ? 'ponto' : 'pontos'), 0, 1, 'C');

// Data de emissão
$pdf->SetFont(FONT_MAIN, '', 8.5);       // ↑ era 6.5
$pdf->SetTextColor(255, 180, 180);
$pdf->SetXY(0, $PH - 10);
$pdf->Cell($LW, 6, s(date('d/m/Y')), 0, 1, 'C');

// ── Painel direito branco ─────────────────────────────────────────────────────
$pdf->SetFillColor(...$BRANCO);
$pdf->Rect($LW, 0, $PW - $LW, $PH, 'F');

$RX = $LW + 10;
$RW = $PW - $LW - 14;

// "CHECKING FOTOGRÁFICO" — título grande
$pdf->SetFont(FONT_MAIN, 'B', 20);       // ↑ era 16
$pdf->SetTextColor(...$MUTED);
$pdf->SetXY($RX, 13);
$pdf->Cell($RW, 11, s('CHECKING FOTOGRÁFICO'), 0, 1, 'L');

// Linha vermelha
$pdf->SetFillColor(...$VERM);
$pdf->Rect($RX, 27, 60, 1.2, 'F');      // ↑ era y=26, w=55

// Nome da CAMPANHA em destaque
$tituloCampanha = $nomeProjeto ?: $campanha ?: $cliente;
$pdf->SetFont(FONT_MAIN, 'B', 27);       // ↑ era 22
$pdf->SetTextColor(...$PRETO);
$pdf->SetXY($RX, 31);
$pdf->MultiCell($RW, 13, s($tituloCampanha), 0, 'L');   // ↑ H era 11
$yApos = $pdf->GetY() + 4;

// Campos de informação
$campos = [];
if ($nomeProjeto && $campanha) $campos[] = ['Motivo:', $campanha];
$campos[] = ['Agência:', $agencia ?: '-'];
$campos[] = ['Período:', dataFmt($inicio) . ' a ' . dataFmt($fim)];
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

// Contador instalados: "6 / 13"
$pdf->SetFont(FONT_MAIN, 'B', 11);
$pdf->SetTextColor(...$MUTED);
$pdf->SetXY($RX, $y);
$pdf->Cell(32, 7, s('Instalados:'), 0, 0, 'L');

$VERDE = [22, 144, 89];
$wNum  = $pdf->GetStringWidth((string)$nInstalados) + 2;
$pdf->SetFont(FONT_MAIN, 'B', 14);
$pdf->SetTextColor(...$VERDE);
$pdf->SetXY($RX + 32, $y);
$pdf->Cell($wNum, 7, s((string)$nInstalados), 0, 0, 'L');

$pdf->SetFont(FONT_MAIN, 'B', 13);
$pdf->SetTextColor(...$MUTED);
$pdf->SetXY($RX + 32 + $wNum, $y);
$pdf->Cell($RW - 32 - $wNum, 7, s('/ ' . $nPontos), 0, 1, 'L');
$y += 12;

// ── Lista de pontos com suporte a 2 colunas ───────────────────────────────────
$y += 2;
$altPonto    = 9.0;   // mm por linha de ponto
$yMax        = $PH - 10;

if ($y < $yMax - 15) {
    // Cabeçalho "Pontos"
    $pdf->SetFont(FONT_MAIN, 'B', 8.5);
    $pdf->SetTextColor(...$MUTED);
    $pdf->SetXY($RX, $y);
    $pdf->Cell($RW, 6, 'Pontos', 0, 1, 'L');
    $y += 7;

    // Calcula quantos cabem por coluna
    $espacoDisp   = $yMax - $y;
    $maxPorColuna = max(1, (int)floor($espacoDisp / $altPonto));
    $numCols      = ($nPontos > $maxPorColuna) ? 2 : 1;
    $largCol      = $RW / $numCols;
    $maxTotal     = $numCols * $maxPorColuna;

    $pontosArr      = array_values($pontos);
    $pontosExibidos = array_slice($pontosArr, 0, $maxTotal);
    $restantes      = count($pontosArr) - count($pontosExibidos);

    $VERDE = [22, 144, 89];
    $yCol  = [$y, $y];

    foreach ($pontosExibidos as $i => $p) {
        $colAtual = ($numCols === 2 && $i >= $maxPorColuna) ? 1 : 0;
        $xCol     = $RX + $colAtual * $largCol;
        $yAtual   = $yCol[$colAtual];

        if ($yAtual > $yMax) break;

        $instalado = isset($instaladosSet[$p['id']]);

        // Ícone ✓ / –
        $pdf->SetFont(FONT_MAIN, 'B', 9.5);
        $pdf->SetTextColor(...($instalado ? $VERDE : $MUTED));
        $pdf->SetXY($xCol, $yAtual);
        $pdf->Cell(7, 5, s($instalado ? '✓' : '–'), 0, 0, 'L');

        // Número (#270)
        $pdf->SetFont(FONT_MAIN, 'B', 9.5);
        $pdf->SetTextColor(...$VERM);
        $pdf->SetXY($xCol + 7, $yAtual);
        $pdf->Cell(16, 5, '#' . str_pad($p['numero'], 3, '0', STR_PAD_LEFT), 0, 0, 'L');

        // Logradouro - Cidade
        $pdf->SetFont(FONT_MAIN, '', 9.5);
        $pdf->SetTextColor(...($instalado ? $PRETO : $MUTED));
        $pdf->SetXY($xCol + 23, $yAtual);
        $enderecoStr = implode(' - ', array_filter([$p['logradouro'] ?? '', $p['cidade'] ?? '']));
        $pdf->Cell($largCol - 25, 5, s($enderecoStr), 0, 1, 'L');

        $yCol[$colAtual] += $altPonto;
    }

    // "e mais X pontos" se truncado
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
// PÁGINAS DE FOTOS — A4 Paisagem (297 × 210 mm)
// ─────────────────────────────────────────────────────────────────────────────
$FH     = 35;           // altura do rodapé (mm)
$FOTO_H = $PH - $FH;   // área da foto = 175mm

foreach ($todasFotos as $foto) {
    $pdf->AddPage();

    $imgPath = $foto['caminho'] ? __DIR__ . '/../../../../' . $foto['caminho'] : '';

    if ($imgPath && file_exists($imgPath)) {
        [$iw, $ih] = @getimagesize($imgPath) ?: [4, 3];
        if ($iw < 1 || $ih < 1) { $iw = 4; $ih = 3; }

        // "Cover": escala pela dimensão que preenche o espaço todo
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
        // Painel sem foto — pré-checking / instalação pendente
        $pdf->SetFillColor(240, 241, 245);
        $pdf->Rect(0, 0, $PW, $FOTO_H, 'F');
        // Faixa vermelha tênue horizontal
        $pdf->SetFillColor(220, 180, 175);
        $pdf->Rect(0, $FOTO_H / 2 - 18, $PW, 0.6, 'F');
        $pdf->Rect(0, $FOTO_H / 2 + 18, $PW, 0.6, 'F');
        $pdf->SetFont(FONT_MAIN, 'B', 15);
        $pdf->SetTextColor(160, 80, 70);
        $pdf->SetXY(0, $FOTO_H / 2 - 14);
        $pdf->Cell($PW, 10, s('INSTALAÇÃO PENDENTE'), 0, 0, 'C');
        $pdf->SetFont(FONT_MAIN, '', 10);
        $pdf->SetTextColor(160, 155, 165);
        $pdf->SetXY(0, $FOTO_H / 2 + 4);
        $pdf->Cell($PW, 7, s('Foto será disponibilizada após a instalação do painel'), 0, 0, 'C');
    }

    // ── Selo de observação (para defesa junto ao cliente/agência) ──────────────
    // Fica no canto superior direito da foto, sem tampar o centro da imagem.
    if (!empty($foto['legenda'])) {
        $pdf->SetFont(FONT_MAIN, 'B', 9.5);
        $padX = 6; $padY = 3; $lineH = 4.6;
        $margem = 6;
        $badgeMaxW = 78;
        $innerMaxW = $badgeMaxW - $padX * 2;

        $linhas = ckWrapLines($pdf, s($foto['legenda']), $innerMaxW);

        $maiorLinha = 0;
        foreach ($linhas as $ln) $maiorLinha = max($maiorLinha, $pdf->GetStringWidth($ln));
        $badgeW = min($maiorLinha + $padX * 2, $badgeMaxW);
        $badgeH = count($linhas) * $lineH + $padY * 2;
        $badgeX = $PW - $margem - $badgeW;
        $badgeY = $margem;

        $pdf->SetFillColor(...$VERM);
        $pdf->Rect($badgeX, $badgeY, $badgeW, $badgeH, 'F');

        $pdf->SetTextColor(...$BRANCO);
        $ly = $badgeY + $padY;
        foreach ($linhas as $ln) {
            $pdf->SetXY($badgeX, $ly);
            $pdf->Cell($badgeW, $lineH, $ln, 0, 0, 'C');
            $ly += $lineH;
        }
    }

    // ── Rodapé BRANCO ─────────────────────────────────────────────────────────
    $FY = $FOTO_H;

    // Fundo branco
    $pdf->SetFillColor(...$BRANCO);
    $pdf->Rect(0, $FY, $PW, $FH, 'F');

    // Linha separadora cinza no topo do rodapé
    $pdf->SetFillColor(...$CINZAC);
    $pdf->Rect(0, $FY, $PW, 0.6, 'F');

    // Faixa vermelha à esquerda
    $pdf->SetFillColor(...$VERM);
    $pdf->Rect(0, $FY, 4, $FH, 'F');    // ↑ era 3mm

    // Número do painel — VERMELHO
    $pdf->SetFont(FONT_MAIN, 'B', 16);
    $pdf->SetTextColor(...$VERM);
    $pdf->SetXY(7, $FY + 11);
    $pdf->Cell(24, 8, '#' . str_pad($foto['numero'], 3, '0', STR_PAD_LEFT), 0, 0, 'L');

    // Logradouro — PRETO
    $pdf->SetFont(FONT_MAIN, 'B', 13);
    $pdf->SetTextColor(...$PRETO);
    $pdf->SetXY(33, $FY + 7);
    $pdf->Cell(108, 6, s($foto['logradouro'] ?? ''), 0, 1, 'L');

    // Descrição do ponto — cinza médio
    if (!empty($foto['descricao'])) {
        $pdf->SetFont(FONT_MAIN, '', 11);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetXY(33, $FY + 15);
        $pdf->Cell(108, 5, s($foto['descricao']), 0, 0, 'L');
    }

    // Bairro · Cidade
    $loc = implode(' - ', array_filter([$foto['bairro'] ?? '', $foto['cidade'] ?? ''], fn($v) => trim($v, " \t-") !== ''));
    $pdf->SetFont(FONT_MAIN, 'B', 13);
    $pdf->SetTextColor(...$PRETO);
    $pdf->SetXY(33, $FY + 23);
    $pdf->Cell(108, 5, s($loc), 0, 0, 'L');

    // ── Botão Google Maps — ghost outline ────────────────────────────────────
    if (!empty($foto['latitude']) && !empty($foto['longitude'])) {
        $mapsUrl = 'https://maps.google.com/?q=' . $foto['latitude'] . ',' . $foto['longitude'];
        $coords  = number_format((float)$foto['latitude'], 6) . ', ' . number_format((float)$foto['longitude'], 6);

        $btnH = 13;
        $btnY = $FY + 7;
        $padL = 6; $arrowW = 9; $gap = 3; $padR = 7;
        $pdf->SetFont(FONT_MAIN, 'B', 12);
        $txtW = $pdf->GetStringWidth('Ver no Google Maps');
        $btnW = $padL + $arrowW + $gap + $txtW + $padR;
        $btnX = 148;

        // Fundo branco + borda cinza
        $pdf->SetFillColor(...$BRANCO);
        $pdf->Rect($btnX, $btnY, $btnW, $btnH, 'F');
        $pdf->SetDrawColor(180, 185, 200);
        $pdf->SetLineWidth(0.35);
        $pdf->Rect($btnX, $btnY, $btnW, $btnH);

        // Seta ↗ vermelha
        $pdf->SetFont(FONT_MAIN, 'B', 11);
        $pdf->SetTextColor(...$VERM);
        $pdf->SetXY($btnX + $padL, $btnY + 2.8);
        $pdf->Cell($arrowW, 8, s('↗'), 0, 0, 'L');

        // "Ver no Google Maps"
        $pdf->SetFont(FONT_MAIN, 'B', 12);
        $pdf->SetTextColor(40, 45, 60);
        $pdf->SetXY($btnX + $padL + $arrowW + $gap, $btnY + 2.8);
        $pdf->Cell($txtW + $padR, 8, s('Ver no Google Maps'), 0, 0, 'L', false, $mapsUrl);

        $pdf->Link($btnX, $btnY, $btnW, $btnH, $mapsUrl);

        // Coordenadas abaixo
        $pdf->SetFont(FONT_MAIN, '', 9);
        $pdf->SetTextColor(...$MUTED);
        $pdf->SetXY($btnX, $btnY + $btnH + 2);
        $pdf->Cell($btnW, 5, $coords, 0, 0, 'C');
    }

    // Logo Impakto — canto direito do rodapé
    $logoRodape = __DIR__ . '/../../../../public/assets/img/logo.png';
    if (file_exists($logoRodape)) {
        $pdf->Image($logoRodape, $PW - 35, $FY + 10, 30);
    }
}

// ── Download ──────────────────────────────────────────────────────────────────
ob_end_clean();
$nomeArq = 'Checking_' . preg_replace('/[^a-zA-Z0-9]/', '_', $tituloCampanha) . '_' . date('Ymd') . '.pdf';
$pdf->Output('D', $nomeArq);
