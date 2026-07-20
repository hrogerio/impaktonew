<?php

require_once __DIR__ . '/../Models/RelatorioModel.php';

class RelatorioController {
    private $model;

    private const PERIODOS = [
        '15d' => ['sql' => 'INTERVAL 15 DAY',    'label' => '15 dias', 'modify' => '+15 days'],
        '1m'  => ['sql' => 'INTERVAL 1 MONTH',   'label' => '1 mês',   'modify' => '+1 month'],
        '3m'  => ['sql' => 'INTERVAL 3 MONTH',   'label' => '3 meses', 'modify' => '+3 months'],
        '6m'  => ['sql' => 'INTERVAL 6 MONTH',   'label' => '6 meses', 'modify' => '+6 months'],
        '12m' => ['sql' => 'INTERVAL 12 MONTH',  'label' => '12 meses','modify' => '+12 months'],
    ];

    public function __construct() {
        $this->model = new RelatorioModel();
    }

    private function periodo(string $chave): array {
        return self::PERIODOS[$chave] ?? self::PERIODOS['3m'];
    }

    public function dadosOcupacao(): array {
        $regiao = $this->model->ocupacaoPorRegiao();
        $cidade = $this->model->ocupacaoPorCidade();

        return [
            'ocupacao_regiao' => $regiao,
            'ocupacao_cidade' => $cidade,
            'totais' => [
                'geral'       => array_sum(array_column($regiao, 'total')),
                'ocupados'    => array_sum(array_column($regiao, 'ocupados')),
                'disponiveis' => array_sum(array_column($regiao, 'disponiveis')),
                'reservados'  => array_sum(array_column($regiao, 'reservados')),
                'vencidos'    => array_sum(array_column($regiao, 'vencidos')),
            ],
        ];
    }

    public function dadosContratos(): array {
        $vencidos   = $this->model->contratosVencidos();
        $comDuracao = $this->model->contratosAtivosComDuracao();

        // Contratos vencendo, agrupados por mês (Jul a Dez do ano corrente) e deduplicados por campanha
        $anoAtual = date('Y');
        $vencendoPorMes = [];
        for ($m = 7; $m <= 12; $m++) {
            $vencendoPorMes[$anoAtual . '-' . str_pad($m, 2, '0', STR_PAD_LEFT)] = 0;
        }
        $vencendoAgrupado = [];
        foreach ($comDuracao as $c) {
            $fim = new DateTime($c['fim_contrato']);
            if ($fim->format('Y') !== $anoAtual) continue;
            $mesChave = $fim->format('Y-m');
            $vencendoPorMes[$mesChave]++;
            $vencendoAgrupado[$mesChave][] = $c;
        }
        ksort($vencendoAgrupado);
        foreach ($vencendoAgrupado as $mes => $lista) {
            $vencendoAgrupado[$mes] = $this->agruparCampanhas($lista);
        }

        return [
            'vencendo_por_mes'      => $vencendoPorMes,
            'vencendo_agrupado'     => $vencendoAgrupado,
            'vencidos'              => $vencidos,
            'vencidos_agrupado'     => $this->agruparCampanhas($vencidos),
            'contratos_com_duracao' => $comDuracao,
            'campanhas_ativas'      => $this->agruparCampanhas($comDuracao),
            'duracao_agregada'      => $this->model->duracaoAgregada($comDuracao),
            'ativos_por_mes'        => $this->model->contratosAtivosPorMes(),
        ];
    }

    /** Normaliza texto pra comparação (remove acento e caixa) — evita que typos de digitação (ex: "Armazem" vs "Armazém") separem a mesma campanha em grupos diferentes */
    private function normalizarTexto(?string $texto): string {
        $texto = trim((string)$texto);
        if (class_exists('Normalizer')) {
            $decomposto = Normalizer::normalize($texto, Normalizer::FORM_D);
            if ($decomposto !== false) $texto = preg_replace('/\p{Mn}/u', '', $decomposto);
        }
        return mb_strtolower($texto);
    }

    /** Deduplica uma lista de contratos por campanha (cliente+campanha+agência+período), somando a quantidade de pontos */
    private function agruparCampanhas(array $contratos): array {
        $grupos = [];
        foreach ($contratos as $c) {
            $chave = implode('|', [
                $this->normalizarTexto($c['cliente'] ?? ''),
                $this->normalizarTexto($c['campanha'] ?? ''),
                $this->normalizarTexto($c['agencia'] ?? ''),
                $c['inicio_contrato'],
                $c['fim_contrato'],
            ]);
            if (!isset($grupos[$chave])) {
                $grupos[$chave] = $c;
                $grupos[$chave]['qtd_pontos'] = 0;
            }
            $grupos[$chave]['qtd_pontos']++;
        }
        usort($grupos, fn($a, $b) => strcmp($a['cliente'] ?? '', $b['cliente'] ?? ''));
        return $grupos;
    }

    public function dadosClientes(): array {
        $clientes = $this->model->pontosPorCliente();
        return [
            'clientes' => $clientes,
            'agencias' => $this->model->resumoPorAgencia(),
            'total_pontos_com_contrato' => array_sum(array_column($clientes, 'total_pontos')),
        ];
    }

    public function dadosHistorico(string $periodoChave): array {
        $periodo = $this->periodo($periodoChave);

        return [
            'periodo_chave'  => $periodoChave,
            'periodo_label'  => $periodo['label'],
            'total_mudancas' => $this->model->historicoTotalMudancas($periodo['sql']),
            'rotatividade'   => $this->model->historicoRotatividade($periodo['sql']),
            'timeline'       => $this->model->historicoTimeline($periodo['sql']),
        ];
    }

    /** Todos os dados de uma vez, usado pelo PDF consolidado */
    public function dadosCompletos(string $periodoHistorico = '3m'): array {
        return [
            'ocupacao'  => $this->dadosOcupacao(),
            'contratos' => $this->dadosContratos(),
            'clientes'  => $this->dadosClientes(),
            'historico' => $this->dadosHistorico($periodoHistorico),
        ];
    }
}
