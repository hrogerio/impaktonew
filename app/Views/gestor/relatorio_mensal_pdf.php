<?php
/**
 * GET /gestor/relatorios/pdf
 * Gera o Relatório Mensal consolidado (Ocupação, Contratos & Tempo de Contrato,
 * Clientes & Agências, Histórico/Auditoria) em um único PDF — A4 retrato.
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

$periodoHistorico = $_GET['periodo_historico'] ?? '3m';

$controller = new RelatorioController();
$dados = $controller->dadosCompletos($periodoHistorico);

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
function pctPdf($valor, $total) {
    return $total > 0 ? round(($valor / $total) * 100, 1) : 0;
}
function fmtDataPdf($d) {
    if (!$d || $d === '0000-00-00') return '-';
    try { return (new DateTime($d))->format('d/m/Y'); } catch (Exception $e) { return '-'; }
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
$pdf->SetTitle(s('Relatorio Mensal - Impakto Midia'));

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
    $pdf->SetFont(FONT_MAIN, 'B', 14);
    $pdf->SetTextColor(...$VERM);
    $pdf->SetXY($MX, 10);
    $pdf->Cell($CW, 8, s('Impakto Midia'), 0, 0, 'L');

    $pdf->SetFont(FONT_MAIN, '', 8.5);
    $pdf->SetTextColor(...$MUTED);
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
function tabela($pdf, array $headers, array $colWidths, array $rows, $MX, $VERM, $PRETO, $CINZAC, $CW = null, $MUTED = null) {
    $rowH = 6;
    $drawHeader = function() use ($pdf, $headers, $colWidths, $MX, $VERM) {
        $pdf->SetFont(FONT_MAIN, 'B', 8);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFillColor(...$VERM);
        $pdf->SetX($MX);
        foreach ($headers as $i => $h) {
            $pdf->Cell($colWidths[$i], 6.5, s($h), 0, 0, 'L', true);
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
            foreach ($row as $i => $val) $pdf->Cell($colWidths[$i], $rowH, s($val), 0, 0, 'L', true);
        } else {
            $pdf->SetX($MX);
            foreach ($row as $i => $val) $pdf->Cell($colWidths[$i], $rowH, s($val), 0, 0, 'L');
        }
        $pdf->Ln();
    }
    $pdf->Ln(3);
}

/** Tabela padrão de campanhas (Cliente/Campanha/Agência/Início/Fim/Duração/Pontos) */
function tabelaCampanhasPdf($pdf, array $lista, $MX, $VERM, $PRETO, $CINZAC, $CW, $MUTED) {
    tabela($pdf,
        ['Cliente', 'Campanha', 'Agencia', 'Inicio', 'Fim', 'Duracao (dias)', 'Pontos'],
        [36, 34, 30, 20, 20, 24, 16],
        array_map(fn($c) => [$c['cliente'] ?: '-', $c['campanha'] ?: '-', $c['agencia'] ?: '-', fmtDataPdf($c['inicio_contrato']), fmtDataPdf($c['fim_contrato']), $c['duracao_dias'], $c['qtd_pontos']], $lista),
        $MX, $VERM, $PRETO, $CINZAC, $CW, $MUTED
    );
}

$pdf->AddPage();
cabecalho($pdf, $CW, $MX, $VERM, $MUTED);

// ── Capa curta ──────────────────────────────────────────────────────────
$pdf->SetFont(FONT_MAIN, 'B', 22);
$pdf->SetTextColor(...$PRETO);
$pdf->Cell($CW, 12, s('Relatorio Mensal'), 0, 1, 'L');
$pdf->SetFont(FONT_MAIN, '', 10.5);
$pdf->SetTextColor(...$MUTED);
$pdf->Cell($CW, 7, s('Ocupacao, Contratos, Clientes e Historico de Pontos'), 0, 1, 'L');
$pdf->Ln(4);

// ============================================================
// 1) OCUPAÇÃO
// ============================================================
$oc = $dados['ocupacao'];
tituloSecao($pdf, 'Ocupacao por Regiao / Cidade', $CW, $MX, $VERM, $PRETO, $MUTED);
kpis($pdf, [
    ['Total de Pontos', number_format($oc['totais']['geral'])],
    ['Ocupados (' . pctPdf($oc['totais']['ocupados'], $oc['totais']['geral']) . '%)', number_format($oc['totais']['ocupados'])],
    ['Disponiveis (' . pctPdf($oc['totais']['disponiveis'], $oc['totais']['geral']) . '%)', number_format($oc['totais']['disponiveis'])],
], $CW, $MX, $PRETO, $MUTED, $CINZAC);

subtitulo($pdf, 'Por Regiao', $CW, $MUTED);
tabela($pdf,
    ['Regiao', 'Total', 'Ocup.', 'Disp.', 'Res.', 'Venc.', '% Ocup.'],
    [50, 22, 22, 22, 22, 22, 24],
    array_map(fn($r) => [$r['regiao'], $r['total'], $r['ocupados'], $r['disponiveis'], $r['reservados'], $r['vencidos'], pctPdf($r['ocupados'], $r['total']) . '%'], $oc['ocupacao_regiao']),
    $MX, $VERM, $PRETO, $CINZAC, $CW, $MUTED
);

// ============================================================
// 2) CONTRATOS & TEMPO DE CONTRATO
// ============================================================
$ct = $dados['contratos'];
tituloSecao($pdf, 'Contratos e Tempo de Contrato', $CW, $MX, $VERM, $PRETO, $MUTED);
kpis($pdf, [
    ['Duracao Media Geral', round($ct['duracao_agregada']['media_geral_dias'] / 30, 1) . ' meses'],
    ['Contratos Ativos', count($ct['contratos_com_duracao'])],
    ['Ja Vencidos', count($ct['vencidos'])],
], $CW, $MX, $PRETO, $MUTED, $CINZAC);

subtitulo($pdf, 'Historico Anual - Contratos Ativos por Mes', $CW, $MUTED);
tabela($pdf,
    ['Mes', 'Contratos Ativos'],
    [80, 40],
    array_map(fn($mes, $qtd) => [$mes, $qtd], array_keys($ct['ativos_por_mes']), array_values($ct['ativos_por_mes'])),
    $MX, $VERM, $PRETO, $CINZAC, $CW, $MUTED
);

subtitulo($pdf, 'Contratos Ativos por Cliente', $CW, $MUTED);
tabelaCampanhasPdf($pdf, $ct['campanhas_ativas'], $MX, $VERM, $PRETO, $CINZAC, $CW, $MUTED);

foreach ($ct['vencendo_agrupado'] as $mes => $campanhas) {
    subtitulo($pdf, 'Vencendo em ' . mesLabelPdf($mes), $CW, $MUTED);
    tabelaCampanhasPdf($pdf, $campanhas, $MX, $VERM, $PRETO, $CINZAC, $CW, $MUTED);
}

subtitulo($pdf, 'Contratos Vencidos (todos)', $CW, $MUTED);
tabela($pdf,
    ['No', 'Cidade', 'Cliente', 'Agencia', 'Venceu em', 'Dias Vencido'],
    [14, 32, 42, 34, 28, 16],
    array_map(fn($c) => [$c['numero'], $c['cidade'], $c['cliente'] ?: '-', $c['agencia'] ?: '-', fmtDataPdf($c['fim_contrato']), $c['dias_vencido']], $ct['vencidos']),
    $MX, $VERM, $PRETO, $CINZAC, $CW, $MUTED
);

// ============================================================
// 3) CLIENTES & AGÊNCIAS
// ============================================================
$cl = $dados['clientes'];
tituloSecao($pdf, 'Clientes e Agencias', $CW, $MX, $VERM, $PRETO, $MUTED);
kpis($pdf, [
    ['Clientes', count($cl['clientes'])],
    ['Agencias', count($cl['agencias'])],
], $CW, $MX, $PRETO, $MUTED, $CINZAC);

subtitulo($pdf, 'Todos os Clientes', $CW, $MUTED);
tabela($pdf,
    ['Cliente', 'Agencia', 'Pontos', 'Inicio', 'Fim Contrato'],
    [55, 55, 22, 24, 30],
    array_map(fn($c) => [$c['cliente'], $c['agencia'], $c['total_pontos'], fmtDataPdf($c['inicio_mais_antigo']), fmtDataPdf($c['fim_mais_recente'])], $cl['clientes']),
    $MX, $VERM, $PRETO, $CINZAC, $CW, $MUTED
);

subtitulo($pdf, 'Resumo por Agencia', $CW, $MUTED);
tabela($pdf,
    ['Agencia', 'Clientes', 'Total de Pontos'],
    [90, 40, 40],
    array_map(fn($a) => [$a['agencia'], $a['total_clientes'], $a['total_pontos']], $cl['agencias']),
    $MX, $VERM, $PRETO, $CINZAC, $CW, $MUTED
);

// ============================================================
// 4) HISTÓRICO / AUDITORIA
// ============================================================
$hi = $dados['historico'];
tituloSecao($pdf, 'Historico e Auditoria de Pontos', $CW, $MX, $VERM, $PRETO, $MUTED);
kpis($pdf, [
    ['Mudancas em ' . $hi['periodo_label'], number_format($hi['total_mudancas'])],
    ['Pontos com Mais Giro', count($hi['rotatividade'])],
], $CW, $MX, $PRETO, $MUTED, $CINZAC);

subtitulo($pdf, 'Rotatividade - Pontos com Mais Mudancas de Situacao', $CW, $MUTED);
tabela($pdf,
    ['No', 'Cidade', 'Logradouro', 'Mudancas'],
    [16, 34, 90, 26],
    array_map(fn($r) => [$r['numero'], $r['cidade'], $r['logradouro'], $r['total_mudancas']], $hi['rotatividade']),
    $MX, $VERM, $PRETO, $CINZAC, $CW, $MUTED
);

subtitulo($pdf, 'Linha do Tempo - Ultimas Alteracoes', $CW, $MUTED);
tabela($pdf,
    ['Data/Hora', 'No', 'Campo', 'De', 'Para'],
    [30, 16, 28, 44, 44],
    array_map(fn($h) => [(new DateTime($h['alterado_em']))->format('d/m/Y H:i'), $h['numero'], $h['campo'], $h['valor_antes'] ?: '-', $h['valor_depois'] ?: '-'], $hi['timeline']),
    $MX, $VERM, $PRETO, $CINZAC, $CW, $MUTED
);

ob_end_clean();
$nomeArquivo = 'relatorio-mensal-impakto-' . date('Y-m-d') . '.pdf';
$pdf->Output('D', $nomeArquivo);
