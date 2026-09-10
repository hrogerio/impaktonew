<?php
// Helpers compartilhados entre as telas de Financeiro (listagem, formulário, PDF).

/**
 * Converte a sequência do dia (1,2,3...) em letra (A,B,C...Z,AA,AB...),
 * no mesmo esquema usado para colunas de planilha.
 */
function seqParaLetra(int $seq): string {
    $letra = '';
    for (; $seq > 0; $seq = intdiv($seq - 1, 26)) {
        $letra = chr(65 + (($seq - 1) % 26)) . $letra;
    }
    return $letra;
}

/**
 * Formata o número do pedido como "PI 260910-A" (tipo + data de emissão
 * no formato aamodd + sequência do dia em letra).
 */
function numeroPedidoFmt(string $tipo, string $numeroData, int $numeroSeq): string {
    try {
        $data = (new DateTime($numeroData))->format('ymd');
    } catch (Exception $e) {
        $data = '??????';
    }
    return $tipo . ' ' . $data . '-' . seqParaLetra($numeroSeq);
}

function moedaBr(float $v): string {
    return 'R$ ' . number_format($v, 2, ',', '.');
}

function dataFmtBr(?string $d): string {
    if (!$d) return '-';
    try { return (new DateTime($d))->format('d/m/Y'); } catch (Exception $e) { return (string)$d; }
}

/**
 * Número de meses entre dois meses (inclusive nas duas pontas).
 * Ex: 2026-07-01 a 2026-12-01 = 6.
 */
function mesesEntre(?string $inicio, ?string $fim): ?int {
    if (!$inicio || !$fim) return null;
    try {
        $di = new DateTime($inicio);
        $df = new DateTime($fim);
    } catch (Exception $e) {
        return null;
    }
    $meses = (((int)$df->format('Y')) - ((int)$di->format('Y'))) * 12
           + (((int)$df->format('n')) - ((int)$di->format('n'))) + 1;
    return max(1, $meses);
}

const MESES_ABREV = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
const MESES_COMPLETOS = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

/**
 * "10 de Setembro 2026"
 */
function dataExtensa(?string $d): string {
    if (!$d) return '-';
    try { $dt = new DateTime($d); } catch (Exception $e) { return (string)$d; }
    return $dt->format('j') . ' de ' . MESES_COMPLETOS[(int)$dt->format('n')] . ' ' . $dt->format('Y');
}

/**
 * Formata o período pro PDF: "Jul/2026 a Dez/2026 · 6 meses".
 */
function periodoFmt(?string $inicio, ?string $fim): string {
    if (!$inicio || !$fim) return '';
    try {
        $di = new DateTime($inicio);
        $df = new DateTime($fim);
    } catch (Exception $e) {
        return '';
    }
    $qtd = mesesEntre($inicio, $fim);
    $txtInicio = MESES_ABREV[(int)$di->format('n')] . '/' . $di->format('Y');
    $txtFim    = MESES_ABREV[(int)$df->format('n')] . '/' . $df->format('Y');
    if ($txtInicio === $txtFim) return $txtInicio;
    return $txtInicio . ' a ' . $txtFim . ' · ' . $qtd . ' ' . ($qtd === 1 ? 'mês' : 'meses');
}
