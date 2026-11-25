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

    /**
     * Raio-X da Rua: retrato do que a empresa tem no ar agora — onde, em que formato, com quais marcas,
     * o que vai liberar nos próximos 90 dias e quais pontos estão parados há mais tempo.
     *
     * Classificação de cada ponto (mutuamente exclusiva, pra que as barras somem 100%):
     *   no_ar     → Ocupado com contrato vigente (ou sem data de fim)
     *   vencido   → Vencido, ou Ocupado com fim já passado (está na rua, mas precisa de ação)
     *   reservado → Reservado
     *   livre     → Disponível ou sem situação
     */
    public function dadosRaioX(): array {
        $pontos = $this->model->raioXPontos();
        $hoje   = new DateTime('today');

        $vazio = fn() => ['total' => 0, 'no_ar' => 0, 'vencido' => 0, 'reservado' => 0, 'livre' => 0];
        $totais = $vazio();
        $grupos = ['regiao' => [], 'cidade' => [], 'tipo' => [], 'formato' => []];
        $marcas = [];
        $semMarca = 0;
        $cortesias = 0;
        $exclusivos = 0;
        $saindo = ['30' => [], '60' => [], '90' => []];
        $livres = [];

        foreach ($pontos as $p) {
            $sit = $this->normalizarTexto($p['situacao']);
            $fim = (!empty($p['fim_contrato'])) ? new DateTime($p['fim_contrato']) : null;

            if ($sit === 'vencido' || ($sit === 'ocupado' && $fim && $fim < $hoje)) $cat = 'vencido';
            elseif ($sit === 'ocupado')   $cat = 'no_ar';
            elseif ($sit === 'reservado') $cat = 'reservado';
            else                          $cat = 'livre';

            $regiao  = trim((string)$p['regiao'])  ?: 'Sem Região';
            $cidade  = trim((string)$p['cidade'])  ?: 'Sem Cidade';
            $tipo    = trim((string)$p['tipo']);    $tipo    = ($tipo === '' || $tipo === '-') ? 'Não informado' : $tipo;
            $formato = trim((string)$p['formato']); $formato = ($formato === '' || $formato === '-') ? 'Não informado' : $formato;

            // Agrupa ignorando acento/caixa ("Jaboatao" = "Jaboatão"); o rótulo exibido fica com a grafia acentuada
            foreach (['regiao' => $regiao, 'cidade' => $cidade, 'tipo' => $tipo, 'formato' => $formato] as $dim => $valor) {
                $chave = $this->normalizarTexto($valor);
                $grupos[$dim][$chave] ??= $vazio() + ['nome' => $valor];
                if (strlen($valor) > mb_strlen($valor)) $grupos[$dim][$chave]['nome'] = $valor;
                $grupos[$dim][$chave]['total']++;
                $grupos[$dim][$chave][$cat]++;
            }
            $totais['total']++;
            $totais[$cat]++;
            if (!empty($p['exclusivo'])) $exclusivos++;

            if ($cat === 'no_ar' || $cat === 'vencido') {
                if (!empty($p['cortesia'])) $cortesias++;
                if (!empty($p['marca']) && $p['marca'] !== '-') {
                    $chave = $this->normalizarTexto($p['marca']);
                    $marcas[$chave] ??= ['marca' => $p['marca'], 'faces' => 0, 'cidades' => []];
                    $marcas[$chave]['faces']++;
                    $marcas[$chave]['cidades'][$cidade] = true;
                } else {
                    $semMarca++;
                }
            }

            if ($cat === 'no_ar' && $fim) {
                $dias = (int)$hoje->diff($fim)->days;
                $faixa = $dias <= 30 ? '30' : ($dias <= 60 ? '60' : ($dias <= 90 ? '90' : null));
                if ($faixa) $saindo[$faixa][] = $p + ['dias' => $dias];
            }

            if ($cat === 'livre') {
                $livres[] = $p + ['dias_parado' => $p['ultima_mudanca'] ? (int)(new DateTime($p['ultima_mudanca']))->diff($hoje)->days : null];
            }
        }

        foreach ($grupos as &$g) {
            uasort($g, fn($a, $b) => $b['total'] <=> $a['total']);
            $g = array_column($g, null, 'nome');
        }
        unset($g);

        foreach ($marcas as &$m) { $m['cidades'] = count($m['cidades']); }
        unset($m);
        usort($marcas, fn($a, $b) => [$b['faces'], $a['marca']] <=> [$a['faces'], $b['marca']]);

        foreach ($saindo as &$lista) { usort($lista, fn($a, $b) => $a['dias'] <=> $b['dias']); }
        unset($lista);

        // Parados há mais tempo primeiro; sem registro no log = parado desde antes do início do histórico
        usort($livres, fn($a, $b) => ($b['dias_parado'] ?? PHP_INT_MAX) <=> ($a['dias_parado'] ?? PHP_INT_MAX));

        $noRua = $totais['no_ar'] + $totais['vencido'];
        $facesComMarca = $noRua - $semMarca;
        $top5 = array_sum(array_column(array_slice($marcas, 0, 5), 'faces'));

        return [
            'totais'        => $totais,
            'na_rua'        => $noRua,
            'exclusivos'    => $exclusivos,
            'cortesias'     => $cortesias,
            'sem_marca'     => $semMarca,
            'qtd_marcas'    => count($marcas),
            'qtd_cidades'   => count($grupos['cidade']),
            'qtd_regioes'   => count($grupos['regiao']),
            'top5_share'    => $facesComMarca > 0 ? round($top5 / $facesComMarca * 100) : 0,
            'por_regiao'    => $grupos['regiao'],
            'por_cidade'    => $grupos['cidade'],
            'por_tipo'      => $grupos['tipo'],
            'por_formato'   => $grupos['formato'],
            'marcas'        => $marcas,
            'saindo'        => $saindo,
            'livres'        => $livres,
            'inicio_historico' => $this->model->inicioHistoricoSituacao(),
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
            $vencendoAgrupado[$mesChave][] = $c;
        }
        ksort($vencendoAgrupado);
        foreach ($vencendoAgrupado as $mes => $lista) {
            $vencendoAgrupado[$mes] = $this->agruparCampanhas($lista);
            $vencendoPorMes[$mes] = count($vencendoAgrupado[$mes]);
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
                $grupos[$chave]['pontos'] = [];
                $grupos[$chave]['_contatos'] = [];
            }
            $contatoPonto = trim((string)($c['contato'] ?? ''));
            if ($contatoPonto !== '' && $contatoPonto !== '-' && !in_array($contatoPonto, $grupos[$chave]['_contatos'], true)) {
                $grupos[$chave]['_contatos'][] = $contatoPonto;
            }
            $grupos[$chave]['qtd_pontos']++;
            $grupos[$chave]['pontos'][] = [
                'ponto_id'   => $c['ponto_id']   ?? null,
                'numero'     => $c['numero']     ?? null,
                'logradouro' => $c['logradouro'] ?? '',
                'cidade'     => $c['cidade']     ?? '',
                'regiao'     => $c['regiao']     ?? '',
            ];
        }
        foreach ($grupos as &$g) {
            $g['contato'] = $g['_contatos'] ? implode(', ', $g['_contatos']) : null;
            unset($g['_contatos']);
        }
        unset($g);
        usort($grupos, fn($a, $b) => strcmp($a['cliente'] ?? '', $b['cliente'] ?? ''));
        return $grupos;
    }

    public function dadosClientes(): array {
        return [
            'clientes'          => $this->model->todosClientes(),
            'agencias'          => $this->model->resumoPorAgencia(),
            'agencias_cadastro' => $this->model->todasAgencias(),
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

    public function documentosPorGrupo(): array {
        return $this->model->documentosPorGrupo();
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
