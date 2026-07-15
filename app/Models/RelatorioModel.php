<?php

class RelatorioModel {
    private $pdo;

    /** Expressão reutilizada: fim de contrato, priorizando campanhas.fim, com fallback pra pontos.fim_contrato, tratando datas legadas 0000-00-00 */
    private const FIM_CONTRATO_SQL = "COALESCE(
        CASE WHEN CAST(c.fim AS CHAR) NOT IN ('0000-00-00','') THEN c.fim ELSE NULL END,
        CASE WHEN CAST(p.fim_contrato AS CHAR) NOT IN ('0000-00-00','') THEN p.fim_contrato ELSE NULL END
    )";

    /** Expressão reutilizada: início de contrato, priorizando campanhas.inicio, com fallback pra pontos.inicio_contrato */
    private const INICIO_CONTRATO_SQL = "COALESCE(
        CASE WHEN CAST(c.inicio AS CHAR) NOT IN ('0000-00-00','') THEN c.inicio ELSE NULL END,
        CASE WHEN CAST(p.inicio_contrato AS CHAR) NOT IN ('0000-00-00','') THEN p.inicio_contrato ELSE NULL END
    )";

    public function __construct() {
        $this->pdo = getDatabase();
    }

    // ============================================================
    // OCUPAÇÃO
    // ============================================================

    public function ocupacaoPorRegiao(): array {
        return $this->pdo->query("
            SELECT
                COALESCE(NULLIF(TRIM(regiao), ''), 'Sem Região') AS regiao,
                COUNT(*) AS total,
                SUM(CASE WHEN LOWER(situacao) = 'ocupado' THEN 1 ELSE 0 END) AS ocupados,
                SUM(CASE WHEN LOWER(situacao) IN ('disponivel','disponível') THEN 1 ELSE 0 END) AS disponiveis,
                SUM(CASE WHEN LOWER(situacao) = 'reservado' THEN 1 ELSE 0 END) AS reservados,
                SUM(CASE WHEN fim_contrato IS NOT NULL AND CAST(fim_contrato AS CHAR) != '0000-00-00' AND fim_contrato < CURDATE() THEN 1 ELSE 0 END) AS vencidos
            FROM pontos
            WHERE ativo = 1 OR ativo IS NULL
            GROUP BY regiao
            ORDER BY total DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ocupacaoPorCidade(): array {
        return $this->pdo->query("
            SELECT
                COALESCE(NULLIF(TRIM(cidade), ''), 'Sem Cidade') AS cidade,
                COUNT(*) AS total,
                SUM(CASE WHEN LOWER(situacao) = 'ocupado' THEN 1 ELSE 0 END) AS ocupados,
                SUM(CASE WHEN LOWER(situacao) IN ('disponivel','disponível') THEN 1 ELSE 0 END) AS disponiveis,
                SUM(CASE WHEN LOWER(situacao) = 'reservado' THEN 1 ELSE 0 END) AS reservados,
                SUM(CASE WHEN fim_contrato IS NOT NULL AND CAST(fim_contrato AS CHAR) != '0000-00-00' AND fim_contrato < CURDATE() THEN 1 ELSE 0 END) AS vencidos
            FROM pontos
            WHERE ativo = 1 OR ativo IS NULL
            GROUP BY cidade
            ORDER BY cidade ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // CONTRATOS
    // ============================================================

    public function contratosVencidos(): array {
        $fim = self::FIM_CONTRATO_SQL;
        return $this->pdo->query("
            SELECT
                p.numero, p.logradouro, p.cidade, p.regiao, p.contato,
                c.cliente AS cliente,
                c.agencia AS agencia,
                $fim AS fim_contrato,
                DATEDIFF(CURDATE(), $fim) AS dias_vencido
            FROM pontos p
            LEFT JOIN campanhas c ON c.ponto_id = p.id AND c.ativo = 1 AND c.situacao = 'Ocupado'
            WHERE
                p.situacao NOT IN ('Disponivel','Disponível')
                AND $fim IS NOT NULL
                AND $fim < CURDATE()
                AND $fim >= DATE_FORMAT(CURDATE(), '%Y-07-01')
                AND (p.ativo = 1 OR p.ativo IS NULL)
            ORDER BY fim_contrato DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Lista de contratos ativos com duração planejada (início -> fim), pra aba de Tempo de Contrato */
    public function contratosAtivosComDuracao(): array {
        $inicio = self::INICIO_CONTRATO_SQL;
        $fim    = self::FIM_CONTRATO_SQL;
        return $this->pdo->query("
            SELECT
                p.numero, p.logradouro, p.cidade, p.regiao,
                c.cliente AS cliente,
                c.agencia AS agencia,
                c.campanha AS campanha,
                $inicio AS inicio_contrato,
                $fim AS fim_contrato,
                DATEDIFF($fim, $inicio) AS duracao_dias
            FROM pontos p
            INNER JOIN campanhas c ON c.ponto_id = p.id AND c.ativo = 1 AND c.situacao = 'Ocupado'
            WHERE
                (p.ativo = 1 OR p.ativo IS NULL)
                AND $inicio IS NOT NULL
                AND $fim IS NOT NULL
            ORDER BY duracao_dias DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** KPIs agregados de duração (geral, por região, por cliente) — a partir da mesma base de contratosAtivosComDuracao() */
    public function duracaoAgregada(array $contratosComDuracao): array {
        if (empty($contratosComDuracao)) {
            return ['media_geral_dias' => 0, 'por_regiao' => [], 'por_cliente' => []];
        }

        $mediaGeral = array_sum(array_column($contratosComDuracao, 'duracao_dias')) / count($contratosComDuracao);

        $porRegiao = [];
        foreach ($contratosComDuracao as $c) {
            $r = $c['regiao'] ?: 'Sem Região';
            $porRegiao[$r]['soma'] = ($porRegiao[$r]['soma'] ?? 0) + $c['duracao_dias'];
            $porRegiao[$r]['qtd']  = ($porRegiao[$r]['qtd'] ?? 0) + 1;
        }
        foreach ($porRegiao as $r => &$d) {
            $d['media_dias'] = round($d['soma'] / $d['qtd']);
        }
        unset($d);
        uasort($porRegiao, fn($a, $b) => $b['media_dias'] <=> $a['media_dias']);

        $porCliente = [];
        foreach ($contratosComDuracao as $c) {
            $cli = $c['cliente'] ?: '-';
            $porCliente[$cli]['soma'] = ($porCliente[$cli]['soma'] ?? 0) + $c['duracao_dias'];
            $porCliente[$cli]['qtd']  = ($porCliente[$cli]['qtd'] ?? 0) + 1;
        }
        foreach ($porCliente as $cli => &$d) {
            $d['media_dias'] = round($d['soma'] / $d['qtd']);
        }
        unset($d);
        uasort($porCliente, fn($a, $b) => $b['media_dias'] <=> $a['media_dias']);

        return [
            'media_geral_dias' => round($mediaGeral),
            'por_regiao'  => $porRegiao,
            'por_cliente' => $porCliente,
        ];
    }

    /** Contratos ativos (em vigência) em cada um dos 12 meses do ano corrente — histórico anual pra visão comercial */
    public function contratosAtivosPorMes(): array {
        $inicio = self::INICIO_CONTRATO_SQL;
        $fim    = self::FIM_CONTRATO_SQL;

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM pontos p
            INNER JOIN campanhas c ON c.ponto_id = p.id AND c.ativo = 1 AND c.situacao = 'Ocupado'
            WHERE
                (p.ativo = 1 OR p.ativo IS NULL)
                AND $inicio IS NOT NULL
                AND $fim IS NOT NULL
                AND $inicio <= LAST_DAY(:mesInicio1)
                AND $fim >= :mesInicio2
        ");

        $meses = [];
        for ($m = 1; $m <= 12; $m++) {
            $chave = date('Y') . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
            $stmt->execute(['mesInicio1' => $chave . '-01', 'mesInicio2' => $chave . '-01']);
            $meses[$chave] = (int)$stmt->fetchColumn();
        }
        return $meses;
    }

    // ============================================================
    // CLIENTES / AGÊNCIAS
    // ============================================================

    public function pontosPorCliente(): array {
        return $this->pdo->query("
            SELECT
                TRIM(c.cliente) AS cliente,
                CASE
                    WHEN NULLIF(TRIM(c.agencia),'') IS NULL OR NULLIF(TRIM(c.agencia),'') = '-'
                    THEN 'Cliente direto'
                    ELSE TRIM(c.agencia)
                END AS agencia,
                COUNT(*) AS total_pontos,
                SUM(CASE WHEN LOWER(p.situacao) = 'ocupado' THEN 1 ELSE 0 END) AS ocupados,
                MIN(COALESCE(DATE(c.inicio), DATE(p.inicio_contrato))) AS inicio_mais_antigo,
                MAX(" . self::FIM_CONTRATO_SQL . ") AS fim_mais_recente
            FROM pontos p
            LEFT JOIN campanhas c ON c.ponto_id = p.id AND c.ativo = 1 AND c.situacao = 'Ocupado'
            WHERE
                NULLIF(TRIM(c.cliente),'') IS NOT NULL
                AND NULLIF(TRIM(c.cliente),'') != '-'
                AND (p.ativo = 1 OR p.ativo IS NULL)
            GROUP BY
                TRIM(c.cliente),
                CASE
                    WHEN NULLIF(TRIM(c.agencia),'') IS NULL OR NULLIF(TRIM(c.agencia),'') = '-'
                    THEN 'Cliente direto'
                    ELSE TRIM(c.agencia)
                END
            ORDER BY cliente ASC, agencia ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resumoPorAgencia(): array {
        return $this->pdo->query("
            SELECT
                CASE
                    WHEN NULLIF(TRIM(c.agencia),'') IS NULL OR NULLIF(TRIM(c.agencia),'') = '-'
                    THEN 'Cliente direto'
                    ELSE TRIM(c.agencia)
                END AS agencia,
                COUNT(DISTINCT NULLIF(TRIM(c.cliente),'')) AS total_clientes,
                COUNT(*) AS total_pontos
            FROM pontos p
            LEFT JOIN campanhas c ON c.ponto_id = p.id AND c.ativo = 1 AND c.situacao = 'Ocupado'
            WHERE
                NULLIF(TRIM(c.cliente),'') IS NOT NULL
                AND NULLIF(TRIM(c.cliente),'') != '-'
                AND (p.ativo = 1 OR p.ativo IS NULL)
            GROUP BY
                CASE
                    WHEN NULLIF(TRIM(c.agencia),'') IS NULL OR NULLIF(TRIM(c.agencia),'') = '-'
                    THEN 'Cliente direto'
                    ELSE TRIM(c.agencia)
                END
            ORDER BY total_pontos DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // HISTÓRICO / AUDITORIA (pontos_log)
    // ============================================================

    /** Pontos com mais mudanças de situação no período — indicador de rotatividade/giro */
    public function historicoRotatividade(string $intervalSQL, int $limite = 15): array {
        return $this->pdo->query("
            SELECT
                p.numero, p.logradouro, p.cidade,
                COUNT(*) AS total_mudancas
            FROM pontos_log pl
            INNER JOIN pontos p ON p.id = pl.ponto_id
            WHERE pl.campo = 'situacao'
              AND pl.alterado_em >= DATE_SUB(CURDATE(), $intervalSQL)
            GROUP BY pl.ponto_id, p.numero, p.logradouro, p.cidade
            ORDER BY total_mudancas DESC
            LIMIT $limite
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Linha do tempo de alterações recentes em pontos, dentro do período */
    public function historicoTimeline(string $intervalSQL, int $limite = 100): array {
        return $this->pdo->query("
            SELECT
                pl.campo, pl.valor_antes, pl.valor_depois, pl.alterado_em, pl.alterado_por,
                p.numero, p.logradouro, p.cidade
            FROM pontos_log pl
            INNER JOIN pontos p ON p.id = pl.ponto_id
            WHERE pl.alterado_em >= DATE_SUB(CURDATE(), $intervalSQL)
            ORDER BY pl.alterado_em DESC
            LIMIT $limite
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function historicoTotalMudancas(string $intervalSQL): int {
        return (int)$this->pdo->query("
            SELECT COUNT(*) FROM pontos_log
            WHERE alterado_em >= DATE_SUB(CURDATE(), $intervalSQL)
        ")->fetchColumn();
    }
}
