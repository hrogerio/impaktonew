<?php
require_once __DIR__ . '/../../config/cache.php';

class PontoModel {
    private $pdo;

    public function __construct() {
        $config = require __DIR__ . '/../../config/database.php';
        $this->pdo = getDatabase();
    }

    /**
     * Lista pontos com filtros e paginação
     */
    public function listarPaginado(array $filtros = [], int $pagina = 1, int $limite = 5): array {
        $offset = ($pagina - 1) * $limite;

        $sql = "SELECT * FROM pontos WHERE 1=1";
        $params = $this->montarFiltros($sql, $filtros);

        $sql .= " ORDER BY id DESC LIMIT :limite OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        // Bind params dinâmicos
        foreach ($params as $chave => $valor) {
            $stmt->bindValue(":$chave", $valor);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Conta total de registros
     */
    public function contar(array $filtros = []): int {
        $sql = "SELECT COUNT(*) as total FROM pontos WHERE 1=1";
        $params = $this->montarFiltros($sql, $filtros);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($resultado['total'] ?? 0);
    }

    /**
     * Monta filtros para SQL
     */
    private function montarFiltros(&$sql, array $filtros): array {
        $params = [];

        if (!empty($filtros['situacao'])) {
            $sql .= " AND situacao = :situacao";
            $params['situacao'] = $filtros['situacao'];
        }

        if (!empty($filtros['regiao'])) {
            $sql .= " AND regiao = :regiao";
            $params['regiao'] = $filtros['regiao'];
        }

        if (!empty($filtros['tipo'])) {
            $sql .= " AND tipo = :tipo";
            $params['tipo'] = $filtros['tipo'];
        }

        if (!empty($filtros['cidade'])) {
            $sql .= " AND cidade = :cidade";
            $params['cidade'] = $filtros['cidade'];
        }

        if (!empty($filtros['busca'])) {
            $sql .= " AND (descricao LIKE :busca OR cliente LIKE :busca)";
            $params['busca'] = '%' . $filtros['busca'] . '%';
        }

        return $params;
    }

    public function buscarPorNumeros($numeros) {
        if (empty($numeros)) return [];

        $placeholders = implode(',', array_fill(0, count($numeros), '?'));
        $sql = "SELECT * FROM pontos WHERE numero IN ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($numeros);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Métodos com cache
    public function obterClientesAtivos() {
        return cache()->remember('clientes_ativos', function() {
            $sql = "SELECT DISTINCT cliente FROM pontos WHERE ativo = 1 AND cliente IS NOT NULL AND cliente != '' ORDER BY cliente";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }, 1800);
    }

    public function obterEstatisticas() {
        return cache()->remember('estatisticas_dashboard', function() {
            $sql = "SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN situacao = 'Disponível' THEN 1 END) as disponiveis,
                        COUNT(CASE WHEN situacao = 'Ocupado' THEN 1 END) as ocupados,
                        COUNT(CASE WHEN situacao = 'Reservado' THEN 1 END) as reservados,
                        COUNT(CASE WHEN situacao = 'Vencido' THEN 1 END) as vencidos
                    FROM pontos WHERE ativo = 1";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }, 600);
    }
}