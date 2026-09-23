<?php
/**
 * GET /gestor/financeiro/pedidos/pdf?id=
 * Gera o PDF do Pedido de Inserção (PI) ou Pedido de Produção (PP) — A4 Retrato.
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('memory_limit', '256M');
set_time_limit(60);
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    ob_end_clean();
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/_helpers.php';
$pdo = getDatabase();
$empresa = require __DIR__ . '/../../../../config/empresa.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { ob_end_clean(); http_response_code(400); die('Pedido inválido.'); }

$stmt = $pdo->prepare("SELECT * FROM pedidos_financeiros WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$pedido) { ob_end_clean(); http_response_code(404); die('Pedido não encontrado.'); }

$stmtI = $pdo->prepare("SELECT * FROM pedidos_financeiros_itens WHERE pedido_id = ? ORDER BY ordem ASC, id ASC");
$stmtI->execute([$id]);
$itens = $stmtI->fetchAll(PDO::FETCH_ASSOC);

$stmtP = $pdo->prepare("SELECT * FROM pedidos_financeiros_parcelas WHERE pedido_id = ? ORDER BY numero ASC, id ASC");
$stmtP->execute([$id]);
$parcelas = $stmtP->fetchAll(PDO::FETCH_ASSOC);

$tipo = $pedido['tipo'];
$numeroFmt = numeroPedidoFmt($pedido['tipo'], $pedido['numero_data'], (int)$pedido['numero_seq']);

// Usa tFPDF (suporte nativo a TTF/Unicode) se disponível, senão FPDF padrão
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
    ob_end_clean(); die('Biblioteca PDF não encontrada.');
}

function s($str) {
    if (defined('USE_TFPDF') && USE_TFPDF) return (string)($str ?? '');
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string)($str ?? ''));
}

// ── Cores ─────────────────────────────────────────────────────────────────
$VERM   = [192, 57,  43 ];
$BRANCO = [255, 255, 255];
$PRETO  = [20,  20,  20 ];
$MUTED  = [130, 130, 145];
$CINZAC = [242, 243, 246];

if (defined('USE_TFPDF') && USE_TFPDF) {
    $pdf = new tFPDF('P', 'mm', 'A4');
} else {
    $pdf = new FPDF('P', 'mm', 'A4');
}
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(true, 14);
$pdf->SetCreator('Impakto Mídia OOH');
$titulo = $tipo === 'PI' ? 'PEDIDO DE INSERÇÃO' : 'PEDIDO DE PRODUÇÃO';
$pdf->SetTitle(s($titulo . ' - ' . $pedido['cliente_razao_social']));

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

$PW = 210; $ML = 14; $MR = 14; $CW = $PW - $ML - $MR;

$pdf->AddPage();

// ── Cabeçalho: dados da Impakto (esq.) + título/número (dir.) ──────────────
$pdf->SetFillColor(...$VERM);
$pdf->Rect(0, 0, $PW, 3, 'F');

$logoPath = __DIR__ . '/../../../../public/assets/img/logo.png';
$logoY = 10;
if (file_exists($logoPath)) {
    $pdf->Image($logoPath, $ML, $logoY, 34);
}

$pdf->SetFont(FONT_MAIN, 'B', 9.5);
$pdf->SetTextColor(...$PRETO);
$pdf->SetXY($ML, $logoY + 15);
$pdf->Cell(90, 5, s($empresa['razao_social']), 0, 1, 'L');

$pdf->SetFont(FONT_MAIN, '', 8);
$pdf->SetTextColor(...$MUTED);
$linhasEmpresa = [
    $empresa['endereco'] . ' - ' . $empresa['cidade'],
    'CEP: ' . $empresa['cep'] . '   CNPJ: ' . $empresa['cnpj'],
    'e-mail: ' . $empresa['email'],
];
$y = $logoY + 20;
foreach ($linhasEmpresa as $ln) {
    $pdf->SetXY($ML, $y);
    $pdf->Cell(100, 4.2, s($ln), 0, 1, 'L');
    $y += 4.2;
}

// Título + número, alinhados à direita
$pdf->SetFont(FONT_MAIN, 'B', 15);
$pdf->SetTextColor(...$VERM);
$pdf->SetXY($PW - $MR - 90, 12);
$pdf->Cell(90, 7, s($titulo), 0, 1, 'R');

$pdf->SetFont(FONT_MAIN, 'B', 11);
$pdf->SetTextColor(...$PRETO);
$pdf->SetXY($PW - $MR - 90, 19);
$pdf->Cell(90, 6, s($numeroFmt), 0, 1, 'R');

$pdf->SetFont(FONT_MAIN, '', 9);
$pdf->SetTextColor(...$MUTED);
$pdf->SetXY($PW - $MR - 90, 26);
$pdf->Cell(90, 5, s('Emissão: ' . dataFmtBr($pedido['data_emissao'])), 0, 1, 'R');

$y = max($y, 33) + 4;

// Nome da campanha + período (mais relevante no P.I.)
$periodoTxt = periodoFmt($pedido['periodo_inicio'], $pedido['periodo_fim']);
if ($pedido['nome_campanha'] || $periodoTxt) {
    $pdf->SetFillColor(...$CINZAC);
    $pdf->Rect($ML, $y, $CW, 9, 'F');
    $pdf->SetFont(FONT_MAIN, 'B', 10);
    $pdf->SetTextColor(...$PRETO);
    $pdf->SetXY($ML + 3, $y + 2.2);
    $txt = trim(($pedido['nome_campanha'] ?: '') . ($periodoTxt ? '  ·  Período: ' . $periodoTxt : ''));
    $pdf->Cell($CW - 6, 5, s($txt), 0, 1, 'L');
    $y += 13;
} else {
    $y += 4;
}

// ── DADOS PARA FATURAMENTO ──────────────────────────────────────────────────
$pdf->SetFont(FONT_MAIN, 'B', 9.5);
$pdf->SetTextColor(...$VERM);
$pdf->SetXY($ML, $y);
$pdf->Cell($CW, 6, s('DADOS PARA FATURAMENTO'), 0, 1, 'L');
$pdf->SetDrawColor(...$VERM);
$pdf->SetLineWidth(0.4);
$pdf->Line($ML, $y + 6, $ML + 60, $y + 6);
$y += 9;

$pdf->SetFont(FONT_MAIN, 'B', 10.5);
$pdf->SetTextColor(...$PRETO);
$pdf->SetXY($ML, $y);
$pdf->Cell($CW, 5.5, s($pedido['cliente_razao_social']), 0, 1, 'L');
$y += 5.5;

$linhaCliente = function ($label, $valor) use (&$y, $ML, $CW, $pdf) {
    if (!$valor) return;
    $pdf->SetFont(FONT_MAIN, 'B', 8.5);
    $pdf->SetTextColor(...[130, 130, 145]);
    $pdf->SetXY($ML, $y);
    $pdf->Cell(20, 4.6, s($label), 0, 0, 'L');
    $pdf->SetFont(FONT_MAIN, '', 8.5);
    $pdf->SetTextColor(20, 20, 20);
    $pdf->SetXY($ML + 20, $y);
    $pdf->Cell($CW - 20, 4.6, s($valor), 0, 1, 'L');
    $y += 4.6;
};
$linhaCliente('CNPJ:', $pedido['cliente_cnpj']);
$linhaCliente('I.E.:', $pedido['cliente_ie']);
$enderecoCompleto = trim(implode(' - ', array_filter([$pedido['cliente_endereco'], $pedido['cliente_cidade'], $pedido['cliente_cep']])));
$linhaCliente('Endereço:', $enderecoCompleto);
$linhaCliente('Tel:', $pedido['cliente_telefone']);
$linhaCliente('E-mail:', $pedido['cliente_email']);
$y += 4;

// ── Tabela de itens ──────────────────────────────────────────────────────────
if ($tipo === 'PI') {
    $cols = [
        ['label' => 'MÍDIA', 'w' => 40, 'align' => 'L', 'key' => 'campo1'],
        ['label' => 'PONTO', 'w' => 78, 'align' => 'L', 'key' => 'campo2'],
        ['label' => 'VALOR UNITÁRIO', 'w' => 34, 'align' => 'R', 'key' => 'valor_unitario'],
        ['label' => 'VALOR TOTAL', 'w' => $CW - 40 - 78 - 34, 'align' => 'R', 'key' => 'valor_total'],
    ];
} else {
    $cols = [
        ['label' => 'MAT/SERVIÇO', 'w' => 45, 'align' => 'L', 'key' => 'campo1'],
        ['label' => 'DESCRIÇÃO',   'w' => 67, 'align' => 'L', 'key' => 'campo2'],
        ['label' => 'VALOR UNITÁRIO', 'w' => 38, 'align' => 'R', 'key' => 'valor_unitario'],
        ['label' => 'VALOR TOTAL', 'w' => $CW - 45 - 67 - 38, 'align' => 'R', 'key' => 'valor_total'],
    ];
}

$pdf->SetFillColor(...$VERM);
$pdf->SetTextColor(...$BRANCO);
$pdf->SetFont(FONT_MAIN, 'B', 8);
$pdf->SetXY($ML, $y);
foreach ($cols as $c) {
    $pdf->Cell($c['w'], 7, s($c['label']), 0, 0, $c['align'] === 'L' ? 'L' : $c['align'], true);
}
$pdf->Ln();
$y += 7;

$pdf->SetFont(FONT_MAIN, '', 9);
$linha = 0;
$alturaLinhaItem = 6.2;
foreach ($itens as $item) {
    $fill = $linha % 2 === 1;
    $pdf->SetFillColor(...$CINZAC);
    $pdf->SetTextColor(...$PRETO);
    $pdf->SetXY($ML, $y);
    foreach ($cols as $c) {
        $valor = $item[$c['key']] ?? null;
        if ($c['key'] === 'valor_total' || $c['key'] === 'valor_unitario') {
            $texto = $valor !== null ? moedaBr($valor) : '-';
        } elseif ($c['key'] === 'quantidade') {
            $texto = $valor !== null && $valor !== '' ? rtrim(rtrim(number_format((float)$valor, 2, ',', '.'), '0'), ',') : '-';
        } else {
            $texto = $valor ?: '-';
        }
        $align = $c['align'] === 'L' ? 'L' : $c['align'];
        $pdf->Cell($c['w'], $alturaLinhaItem, s($texto), 0, 0, $align, $fill);
    }
    $pdf->Ln();
    $y += $alturaLinhaItem;
    $linha++;
}

// Linha de total (valor mensal quando o pedido tem mais de 1 parcela)
$qtdParcelas = (int)$pedido['qtd_parcelas'];
$labelTotal = $qtdParcelas > 1 ? 'Valor Mensal' : 'Total';
$pdf->SetDrawColor(...$CINZAC);
$pdf->Line($ML, $y, $ML + $CW, $y);
$y += 3;
$pdf->SetFont(FONT_MAIN, 'B', 11);
$pdf->SetTextColor(...$VERM);
$pdf->SetXY($ML, $y);
$pdf->Cell($CW - 45, 7, s($labelTotal), 0, 0, 'R');
$pdf->Cell(45, 7, s(moedaBr($pedido['valor_total'])), 0, 1, 'R');
$y += 10;

// ── Vencimentos: grade de caixas com borda, uma por parcela ────────────────
if (!empty($parcelas)) {
    $pdf->SetFont(FONT_MAIN, 'B', 10);
    $pdf->SetTextColor(...$PRETO);
    $pdf->SetXY($ML, $y);
    $pdf->Cell($CW, 6, s('Vencimentos:'), 0, 1, 'L');
    $y += 7;

    $totalParcelas = count($parcelas);
    // Só 2 colunas no máximo, pra sobrar largura o bastante pra data + valor na mesma linha
    $numCols  = $totalParcelas > 4 ? 2 : 1;
    $porCol   = (int)ceil($totalParcelas / $numCols);
    $gap      = 4;
    $largCol  = ($CW - $gap * ($numCols - 1)) / $numCols;
    $altCel   = 9;

    $pdf->SetLineWidth(0.25);
    foreach ($parcelas as $i => $p) {
        $col = intdiv($i, $porCol);
        $linhaCol = $i % $porCol;
        $cellX = $ML + $col * ($largCol + $gap);
        $cellY = $y + $linhaCol * ($altCel + 2);

        $pdf->SetDrawColor(...$CINZAC);
        $pdf->SetFillColor(...$CINZAC);
        $pdf->Rect($cellX, $cellY, $largCol, $altCel, 'DF');

        $numLabel = $p['numero'] . 'ª';
        $pdf->SetFont(FONT_MAIN, 'B', 9);
        $pdf->SetTextColor(...$VERM);
        $wNum = $pdf->GetStringWidth($numLabel) + 2;
        $pdf->SetXY($cellX + 3, $cellY + 2.2);
        $pdf->Cell($wNum, 4.6, s($numLabel), 0, 0, 'L');

        $valorTxt = $p['valor'] !== null ? moedaBr((float)$p['valor']) : '';
        $wValor = $valorTxt !== '' ? $pdf->GetStringWidth(s($valorTxt)) + 3 : 0;

        $pdf->SetFont(FONT_MAIN, '', 8.5);
        $pdf->SetTextColor(...$PRETO);
        $pdf->SetXY($cellX + 3 + $wNum, $cellY + 2.2);
        $pdf->Cell($largCol - 6 - $wNum - $wValor, 4.6, s(dataExtensa($p['data_vencimento'])), 0, 0, 'L');

        if ($valorTxt !== '') {
            $pdf->SetFont(FONT_MAIN, 'B', 8.5);
            $pdf->SetTextColor(...$VERM);
            $pdf->SetXY($cellX + 3, $cellY + 2.2);
            $pdf->Cell($largCol - 6, 4.6, s($valorTxt), 0, 0, 'R');
        }
    }
    $y += $porCol * ($altCel + 2) + 4;
}

// ── Dados Bancários (esq.) + Valor Bruto (dir.) lado a lado ────────────────
$yBanco = $y;
$pdf->SetFont(FONT_MAIN, 'B', 9.5);
$pdf->SetTextColor(...$VERM);
$pdf->SetXY($ML, $yBanco);
$pdf->Cell(90, 6, s('DADOS BANCÁRIOS'), 0, 1, 'L');
$yBanco += 6.5;

$pdf->SetFont(FONT_MAIN, '', 9);
$pdf->SetTextColor(...$PRETO);
$linhasBanco = [
    strtoupper($empresa['banco']),
    'AG: ' . $empresa['agencia'],
    'C/C: ' . $empresa['conta'],
    'Chave Pix: ' . $empresa['chave_pix'],
];
foreach ($linhasBanco as $ln) {
    $pdf->SetXY($ML, $yBanco);
    $pdf->Cell(90, 5, s($ln), 0, 1, 'L');
    $yBanco += 5;
}

// Valor Bruto, alinhado à direita, centralizado na altura do bloco bancário
$pdf->SetFont(FONT_MAIN, 'B', 10.5);
$pdf->SetTextColor(...$PRETO);
$pdf->SetXY($PW - $MR - 90, $y + 8);
$pdf->Cell(45, 7, s('Valor Bruto'), 0, 0, 'L');
$pdf->SetFont(FONT_MAIN, 'B', 13);
$pdf->SetTextColor(...$VERM);
$pdf->Cell(45, 7, s(moedaBr($pedido['valor_bruto'])), 0, 1, 'R');

$y = max($yBanco, $y + 15) + 6;

if ($y > 255) { $pdf->AddPage(); $y = 20; }

// ── Observações ──────────────────────────────────────────────────────────────
if ($pedido['observacoes']) {
    $pdf->SetFont(FONT_MAIN, 'B', 9);
    $pdf->SetTextColor(...$MUTED);
    $pdf->SetXY($ML, $y);
    $pdf->Cell($CW, 5, s('OBSERVAÇÕES'), 0, 1, 'L');
    $y += 5;
    $pdf->SetFont(FONT_MAIN, '', 8.5);
    $pdf->SetTextColor(...$PRETO);
    $pdf->SetXY($ML, $y);
    $pdf->MultiCell($CW, 4.6, s($pedido['observacoes']), 0, 'L');
    $y = $pdf->GetY() + 4;
}

if ($y > 240) { $pdf->AddPage(); $y = 20; }

// ── Texto de concordância ─────────────────────────────────────────────────
$pdf->SetFont(FONT_MAIN, '', 8);
$pdf->SetTextColor(...$MUTED);
$pdf->SetXY($ML, $y);
$pdf->MultiCell($CW, 4.2, s('Concordamos com as condições do presente pedido, principalmente das notas importantes.'), 0, 'L');
$y = $pdf->GetY() + 8;

// Reserva espaço extra pra imagem da assinatura, se houver
$assinanteInfo = $pedido['assinante'] && isset(ASSINANTES[$pedido['assinante']]) ? ASSINANTES[$pedido['assinante']] : null;
$alturaAssinatura = $assinanteInfo ? 18 : 6;
$y += $alturaAssinatura;

if ($y > 270) { $pdf->AddPage(); $y = 20; $y += $alturaAssinatura; }

// ── Assinaturas ────────────────────────────────────────────────────────────
$colW = ($CW - 10) / 2;

// Imagem da assinatura escolhida, centralizada acima da linha esquerda
if ($assinanteInfo) {
    $assinaturaPath = __DIR__ . '/../../../../public/assets/img/assinaturas/' . $assinanteInfo['imagem'];
    if (file_exists($assinaturaPath)) {
        [$iw, $ih] = @getimagesize($assinaturaPath) ?: [0, 0];
        // Limita por largura E altura, pra assinaturas com proporções bem diferentes
        // (ex: mais "quadradas") não ficarem grandes/desproporcionais na página.
        $imgW = 40;
        $imgH = $iw > 0 ? $imgW * ($ih / $iw) : 14;
        $alturaMax = 14;
        if ($imgH > $alturaMax) {
            $imgH = $alturaMax;
            $imgW = $ih > 0 ? $imgH * ($iw / $ih) : $imgW;
        }
        $imgX = $ML + ($colW - $imgW) / 2;
        $imgY = $y - $imgH - 2; // encosta perto da linha, independente da altura da imagem
        $pdf->Image($assinaturaPath, $imgX, $imgY, $imgW, $imgH);
    }
}

$pdf->SetDrawColor(...$PRETO);
$pdf->SetLineWidth(0.3);
$pdf->Line($ML, $y, $ML + $colW, $y);
$pdf->Line($ML + $colW + 10, $y, $ML + $colW + 10 + $colW, $y);
$y += 3;

$pdf->SetFont(FONT_MAIN, '', 8.5);
$pdf->SetTextColor(...$PRETO);
$pdf->SetXY($ML, $y);
$pdf->Cell($colW, 5, s('Assinatura / ' . $empresa['razao_social']), 0, 0, 'C');
$pdf->SetXY($ML + $colW + 10, $y);
$pdf->Cell($colW, 5, s('Assinatura do responsável pelo pedido'), 0, 1, 'C');

if ($assinanteInfo) {
    $pdf->SetFont(FONT_MAIN, '', 7.5);
    $pdf->SetTextColor(...$MUTED);
    $pdf->SetXY($ML, $y + 4.5);
    $pdf->Cell($colW, 4, s($assinanteInfo['nome']), 0, 0, 'C');
}

// ── Download ──────────────────────────────────────────────────────────────
ob_end_clean();
$nomeArq = str_replace(' ', '_', $numeroFmt)
    . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $pedido['cliente_razao_social']) . '.pdf';
$pdf->Output('D', $nomeArq);
