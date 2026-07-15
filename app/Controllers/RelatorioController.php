<?php

require_once __DIR__ . '/../Models/RelatorioModel.php';

class RelatorioController {
    private $model;

    private const PERIODOS = [
        '15d' => ['sql' => 'INTERVAL 15 DAY',    'label' => '15 dias'],
        '1m'  => ['sql' => 'INTERVAL 1 MONTH',   'label' => '1 mês'],
        '3m'  => ['sql' => 'INTERVAL 3 MONTH',   'label' => '3 meses'],
        '6m'  => ['sql' => 'INTERVAL 6 MONTH',   'label' => '6 meses'],
        '12m' => ['sql' => 'INTERVAL 12 MONTH',  'label' => '12 meses'],
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

    public function dadosContratos(string $periodoChave): array {
        $periodo = $this->periodo($periodoChave);

        $vencendo = $this->model->contratosVencendo($periodo['sql']);
        $vencidos = $this->model->contratosVencidos();
        $comDuracao = $this->model->contratosAtivosComDuracao();

        $vencendoPorMes = [];
        foreach ($vencendo as $c) {
            $mes = (new DateTime($c['fim_contrato']))->format('Y-m');
            $vencendoPorMes[$mes] = ($vencendoPorMes[$mes] ?? 0) + 1;
        }
        ksort($vencendoPorMes);

        return [
            'periodo_chave'   => $periodoChave,
            'periodo_label'   => $periodo['label'],
            'vencendo'        => $vencendo,
            'vencendo_por_mes'=> $vencendoPorMes,
            'vencidos'        => $vencidos,
            'contratos_com_duracao' => $comDuracao,
            'duracao_agregada'      => $this->model->duracaoAgregada($comDuracao),
            'ativos_por_mes'        => $this->model->contratosAtivosPorMes(),
        ];
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
    public function dadosCompletos(string $periodoContratos = '3m', string $periodoHistorico = '3m'): array {
        return [
            'ocupacao'  => $this->dadosOcupacao(),
            'contratos' => $this->dadosContratos($periodoContratos),
            'clientes'  => $this->dadosClientes(),
            'historico' => $this->dadosHistorico($periodoHistorico),
        ];
    }
}
