<?php
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../config/cache.php';

class PontoController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = getDatabase();
    }

    public function listar(array $filtros, int $paginaAtual, int $limite)
    {
        // Validar e sanitizar filtros
        $filtrosSeguros = [];
        foreach ($filtros as $key => $value) {
            if (!empty($value)) {
                $filtrosSeguros[$key] = sanitizeString($value);
            }
        }
        
        // Validar paginação
        $paginaAtual = validateId($paginaAtual) ?: 1;
        $limite = validateId($limite) ?: 10;
        $limite = min($limite, 100); // Máximo 100 por página
        
        $offset = ($paginaAtual - 1) * $limite;
        $sql = "SELECT * FROM pontos WHERE ativo = 1";

        $params = [];

        // Aplicar filtros seguros
        if (!empty($filtrosSeguros['situacao'])) {
            $sql .= " AND situacao = :situacao";
            $params[':situacao'] = $filtrosSeguros['situacao'];
        }

        if (!empty($filtrosSeguros['regiao'])) {
            $sql .= " AND regiao = :regiao";
            $params[':regiao'] = $filtrosSeguros['regiao'];
        }

        if (!empty($filtrosSeguros['tipo'])) {
            $sql .= " AND tipo = :tipo";
            $params[':tipo'] = $filtrosSeguros['tipo'];
        }

        if (!empty($filtrosSeguros['cidade'])) {
            $sql .= " AND cidade = :cidade";
            $params[':cidade'] = $filtrosSeguros['cidade'];
        }

        // Busca otimizada
        if (!empty($filtrosSeguros['busca'])) {
            $sql .= " AND (logradouro LIKE :busca OR descricao LIKE :busca OR cliente LIKE :busca OR numero LIKE :busca)";
            $params[':busca'] = '%' . $filtrosSeguros['busca'] . '%';
        }

        // Ordenação inteligente
        $sql .= " ORDER BY 
            CASE 
                WHEN fim_contrato IS NULL OR fim_contrato = '0000-00-00' OR fim_contrato = '' THEN 1
                ELSE 0 
            END,
            fim_contrato ASC 
            LIMIT :offset, :limite";

        try {
            $stmt = $this->pdo->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);

            $stmt->execute();
            $pontos = $stmt->fetchAll();

            // Contagem total
            $sqlCount = "SELECT COUNT(*) FROM pontos WHERE ativo = 1";
            $paramsCount = [];
            
            // Repetir condições para contagem
            if (!empty($filtrosSeguros['situacao'])) {
                $sqlCount .= " AND situacao = :situacao";
                $paramsCount[':situacao'] = $filtrosSeguros['situacao'];
            }
            if (!empty($filtrosSeguros['regiao'])) {
                $sqlCount .= " AND regiao = :regiao";
                $paramsCount[':regiao'] = $filtrosSeguros['regiao'];
            }
            if (!empty($filtrosSeguros['tipo'])) {
                $sqlCount .= " AND tipo = :tipo";
                $paramsCount[':tipo'] = $filtrosSeguros['tipo'];
            }
            if (!empty($filtrosSeguros['cidade'])) {
                $sqlCount .= " AND cidade = :cidade";
                $paramsCount[':cidade'] = $filtrosSeguros['cidade'];
            }
            if (!empty($filtrosSeguros['busca'])) {
                $sqlCount .= " AND (logradouro LIKE :busca OR descricao LIKE :busca OR cliente LIKE :busca OR numero LIKE :busca)";
                $paramsCount[':busca'] = '%' . $filtrosSeguros['busca'] . '%';
            }

            $stmtCount = $this->pdo->prepare($sqlCount);
            $stmtCount->execute($paramsCount);
            $total = $stmtCount->fetchColumn();

            return [
                'pontos' => $pontos,
                'total' => $total,
                'limite' => $limite,
                'pagina' => $paginaAtual
            ];
            
        } catch (PDOException $e) {
            error_log("Erro na consulta de pontos: " . $e->getMessage());
            throw new Exception("Erro ao buscar pontos");
        }
    }

    public function buscarPorId(int $id)
    {
        $id = validateId($id);
        if (!$id) {
            return false;
        }

        $sql = "SELECT * FROM pontos WHERE id = :id AND ativo = 1";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Erro ao buscar ponto ID $id: " . $e->getMessage());
            return false;
        }
    }
    
    // Método para obter clientes (com cache)
    public function obterClientesAtivos() {
        return cache()->remember('clientes_ativos', function() {
            $sql = "SELECT DISTINCT cliente FROM pontos WHERE ativo = 1 AND cliente IS NOT NULL AND cliente != '' ORDER BY cliente";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }, 1800); // Cache por 30 minutos
    }

    // Método para estatísticas (com cache)
    public function obterEstatisticas() {
        return cache()->remember('estatisticas_dashboard', function() {
            $sql = "SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN situacao = 'Disponivel' THEN 1 END) as disponiveis,
                        COUNT(CASE WHEN situacao = 'Ocupado' THEN 1 END) as ocupados,
                        COUNT(CASE WHEN situacao = 'Reservado' THEN 1 END) as reservados,
                        COUNT(CASE WHEN situacao = 'Vencido' THEN 1 END) as vencidos
                    FROM pontos WHERE ativo = 1";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetch();
        }, 600); // Cache por 10 minutos
    }
}