<?php
session_start();

// ADICIONE ESTE BLOCO AQUI - Logo após o session_start()
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: ../../public/index.php");
    exit;
}

if (!isset($_SESSION['usuario'])) {
    header("Location: ../../public/index.php?erro=nao_logado");
    exit;
}

$paginaAtual = 'pontos';

// Conectar ao banco
try {
    require_once __DIR__ . '/../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// Parâmetros de filtro e paginação
$busca = $_GET['busca'] ?? '';
$tipo = $_GET['tipo'] ?? '';
$regiao = $_GET['regiao'] ?? '';
$situacao = $_GET['situacao'] ?? '';
$cliente = $_GET['cliente'] ?? '';
$cidade = $_GET['cidades'] ?? '';
$pagina = (int)($_GET['pagina'] ?? 1);
$itensPorPagina = 5;
$offset = ($pagina - 1) * $itensPorPagina;
$status = $_GET['status'] ?? 'ativo';
$corredor = $_GET['corredor'] ?? '';

// Buscar clientes para filtro
$sqlClientes = "SELECT DISTINCT cliente FROM pontos WHERE cliente IS NOT NULL AND cliente != '' ORDER BY cliente";
$stmtClientes = $pdo->prepare($sqlClientes);
$stmtClientes->execute();
$clientes = $stmtClientes->fetchAll(PDO::FETCH_COLUMN);


// Buscar cidades para filtro
$sqlCidades = "SELECT DISTINCT cidade FROM pontos WHERE cidade IS NOT NULL AND cidade != '' ORDER BY cidade";
$stmtCidades = $pdo->prepare($sqlCidades);
$stmtCidades->execute();
$cidades = $stmtCidades->fetchAll(PDO::FETCH_COLUMN);

// Buscar corredores para filtro
$sqlCorredores = "SELECT DISTINCT corredor FROM pontos WHERE corredor IS NOT NULL AND corredor != '' ORDER BY corredor";
$stmtCorredores = $pdo->prepare($sqlCorredores);
$stmtCorredores->execute();
$corredores = $stmtCorredores->fetchAll(PDO::FETCH_COLUMN);



// Construir filtros
$where = [];
$params = [];

if ($status === 'ativo') {
    $where[] = "(ativo = 1 OR ativo IS NULL)";
} elseif ($status === 'inativo') {
    $where[] = "ativo = 0";
}

if ($busca) {
    // Busca inteligente: prioriza número exato, depois parcial
    $buscaLimpa = trim($busca);
    
    // Se buscar só números, prioriza campo número
    if (is_numeric($buscaLimpa)) {
        $where[] = "(numero = ? OR numero LIKE ? OR logradouro LIKE ? OR cidade LIKE ? OR descricao LIKE ?)";
        $params[] = $buscaLimpa;
        $params[] = "%$buscaLimpa%";
        $params[] = "%$buscaLimpa%";
        $params[] = "%$buscaLimpa%";
        $params[] = "%$buscaLimpa%";
    } else {
        // Busca textual normal
        $where[] = "(numero LIKE ? OR logradouro LIKE ? OR cidade LIKE ? OR descricao LIKE ? OR cidade LIKE ? OR regiao LIKE ?)";
        $params[] = "%$buscaLimpa%";
        $params[] = "%$buscaLimpa%";
        $params[] = "%$buscaLimpa%";
        $params[] = "%$buscaLimpa%";
        $params[] = "%$buscaLimpa%";
        $params[] = "%$buscaLimpa%";
    }
}

if ($tipo) {
    $where[] = "tipo = ?";
    $params[] = $tipo;
}

if ($regiao) {
    $where[] = "regiao = ?";
    $params[] = $regiao;
}

if ($situacao) {
    $where[] = "situacao = ?";
    $params[] = $situacao;
}

if ($cliente) {
    $where[] = "cliente = ?";
    $params[] = $cliente;
}

if ($cidade) {
    $where[] = "cidade = ?";
    $params[] = $cidade;
}

if ($corredor) {
    $where[] = "corredor = ?";
    $params[] = $corredor;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Contar total
$sqlCount = "SELECT COUNT(*) FROM pontos $whereSql";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$total = $stmtCount->fetchColumn();
$totalPaginas = ceil($total / $itensPorPagina);

// Buscar pontos COM ID - ORDENADO POR TÉRMINO DE CONTRATO
$sql = "SELECT id, numero, logradouro, descricao, cidade, regiao, cliente, agencia, tipo, situacao,
               CASE 
                   WHEN fim_contrato IS NULL OR fim_contrato = '0000-00-00' OR fim_contrato = '' 
                   THEN NULL
                   ELSE DATE(fim_contrato)
               END as fim_contrato_clean
        FROM pontos $whereSql 
        ORDER BY 
             CASE 
                 WHEN fim_contrato IS NULL OR fim_contrato = '0000-00-00' OR fim_contrato = '' 
                 THEN 1
                 ELSE 0
             END,
             fim_contrato ASC,
             numero ASC
        LIMIT " . (int)$itensPorPagina . " OFFSET " . (int)$offset;

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pontos = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erro na consulta de pontos: " . $e->getMessage());
    
    $sqlFallback = "SELECT id, numero, logradouro, descricao, cidade, regiao, cliente, agencia, tipo, situacao,
                           fim_contrato as fim_contrato_clean
                      FROM pontos $whereSql 
                      ORDER BY 
                           CASE 
                               WHEN fim_contrato IS NULL OR fim_contrato = '0000-00-00' OR fim_contrato = '' 
                               THEN 1
                               ELSE 0
                           END,
                           fim_contrato ASC,
                           numero ASC
                      LIMIT " . (int)$itensPorPagina . " OFFSET " . (int)$offset;
    
    $stmt = $pdo->prepare($sqlFallback);
    $stmt->execute($params);
    $pontos = $stmt->fetchAll();
}

function formatarData($data) {
    if (!$data || $data === '0000-00-00') {
        return '<span>-</span>';
    }
    try {
        $date = new DateTime($data);
        $mesIngles = $date->format('M');
        $ano = $date->format('Y');
        $mesPortugues = traduzirMes($mesIngles);
        return '<span>' . $mesPortugues . '/' . $ano . '</span>';
    } catch (Exception $e) {
        return '<span class="text-danger">Inválida</span>';
    }
}

function calcularStatus($data) {
    if (!$data || $data === '0000-00-00') {
        return '<small class="status-badge status-sem-prazo">Sem prazo</small>';
    }
    try {
        $fim = new DateTime($data);
        $hoje = new DateTime();
        $diff = $hoje->diff($fim);
        
        // Calcula dias totais
        $diasRestantes = (int)$hoje->diff($fim)->format('%r%a');
        
        if ($diasRestantes < 0) {
            // Vencido
            $diasVencidos = abs($diasRestantes);
            return '<small class="status-badge status-vencido"> Vencido há '  . $diasVencidos . 'd</small>';
        } elseif ($diasRestantes <= 7) {
            // Menos de 1 semana
            return '<small class="status-badge status-critico">' . $diasRestantes . ' dias</small>';
        } elseif ($diasRestantes <= 30) {
            // Menos de 1 mês
            return '<small class="status-badge status-urgente"> (' . $diasRestantes . ' dias)</small>';
        } elseif ($diasRestantes <= 90) {
            // Menos de 3 meses
            $meses = floor($diasRestantes / 30);
            return '<small class="status-badge status-atencao">(' . $meses . ' mes' . ($meses > 1 ? 'es' : '') . ')</small>';
        } elseif ($diasRestantes <= 180) {
            // Entre 3 e 6 meses
            $meses = floor($diasRestantes / 30);
            return '<small class="status-badge status-ok"> (' . $meses . ' meses)</small>';
        } else {
            // Mais de 6 meses
            $meses = floor($diasRestantes / 30);
            return '<small class="status-badge status-excelente">(' . $meses . ' meses)</small>';
        }
    } catch (Exception $e) {
        return '<small class="status-badge status-erro">Erro</small>';
    }
}

function badgeSituacao($situacao) {
    $situacao = trim($situacao);
    
    $classes = [
        'Disponível' => 'situacao-disponivel',
        'Ocupado' => 'situacao-ocupado',
        'Reservado' => 'situacao-reservado',
        'Vencido' => 'situacao-vencido',
        'Permuta' => 'situacao-permuta',
        'Bisemana' => 'situacao-bisemana',
    ];
    
    $class = $classes[$situacao] ?? 'situacao-outro';
    
    return "<span class='badge-situacao {$class}'>" . htmlspecialchars($situacao) . "</span>";
}

function traduzirMes($mes) {
    $meses = [
        'Jan' => 'Jan', 'Feb' => 'Fev', 'Mar' => 'Mar', 'Apr' => 'Abr',
        'May' => 'Mai', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Ago',
        'Sep' => 'Set', 'Oct' => 'Out', 'Nov' => 'Nov', 'Dec' => 'Dez'
    ];
    return $meses[$mes] ?? $mes;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/impaktonew/public/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet"> 
    <link rel="stylesheet" href="/impaktonew/public/assets/css/gestor.css">
    
    <style>
        .badge-situacao {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.5rem;
            font-weight: 600;
            white-space: nowrap;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .situacao-disponivel {
            background: #1a9059ff;
            color: white;
        }
        
        .situacao-ocupado {
            background: #dc3545;
            color: white;
        }
        
        .situacao-reservado {
            background: #fd7e14;
            color: white;
        }
        
        .situacao-vencido {
            background: #6c757d;
            color: white;
        }
        
        .situacao-permuta {
            background: #51086eff;
            color: white;
        }
        
        .situacao-bisemana {
            background: #0dcaf0;
            color: white;
        }
        
        .situacao-outro {
            background: #198754ff;
            color: white;
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
            display: inline-block;
        }
        
        .status-sem-prazo {
            background: #e9ecef;
            color: #6c757d;
        }
        
        .status-vencido {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-urgente {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-atencao {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-ok {
            background: #d4edda;
            color: #155724;
        }
        
        .status-erro {
            background: #f8d7da;
            color: #721c24;
        }
        
        .link-info {
            color: #C0392B;
            font-weight: 700;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
            display: inline-block;
        }
        
        .link-info:hover {
            color: #A93226;
            transform: translateX(3px);
        }
        
        .table td:last-child {
            text-align: center;
        }
        
        .busca-row {
            position: relative;
        }
    </style>
    
    <title>Lista de Pontos - Impakto Mídia</title>
</head>
<body>

<div class="header">
    <div class="header-content">
        <div class="logo">
            <img src="/impaktonew/public/assets/img/logo.png" alt="Impakto Mídia" class="logo-img">
        </div>

        <form method="get" class="busca-row">
            <span class="busca-icon">🔍</span>
            <input type="text" name="busca" class="busca-input" 
                   placeholder="Buscar por número e endereço ou descrição..." 
                   value="<?= htmlspecialchars($busca) ?>"
                   autocomplete="off">
            <?php if ($busca): ?>
                <a href="?" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #999; text-decoration: none; font-size: 1.2rem; cursor: pointer;" title="Limpar busca">✕</a>
            <?php endif; ?>
        </form>
        
        <nav class="main-nav">
            <a href="/impaktonew/gestor/index.php" class="nav-link">Dashboard</a>
            <a href="/impaktonew/app/Views/gestor/listar_ponto.php" class="nav-link active">Pontos</a>
            <a href="/impaktonew/app/Views/gestor/relatorios/pre_selecao.php" class="nav-link">Pré-Seleção</a>
            <a href="/impaktonew/app/Views/gestor/relatorios/pre_selecao.php" class="nav-link">Relatórios</a>
        </nav>
        
        <div class="user-info">
            <a href="?logout=1" class="btn-logout" onclick="return confirm('Tem certeza que deseja sair?')">Sair</a>
        </div>
    </div>
</div>

<div class="container">
    <div class="controls">       
        <form method="get" class="filtros-grid">
            <input type="hidden" name="busca" value="<?= htmlspecialchars($busca) ?>">
                        
            
            <div class="filtro-group">
                <label>Região</label>
                <select name="regiao" onchange="this.form.submit()">
                    <option value="">Todas as regiões</option>
                    <option value="Metropolitana" <?= $regiao === 'Metropolitana' ? 'selected' : '' ?>>Metropolitana</option>
                    <option value="Agreste" <?= $regiao === 'Agreste' ? 'selected' : '' ?>>Agreste</option>
                    <option value="Mata Norte" <?= $regiao === 'Mata Norte' ? 'selected' : '' ?>>Mata Norte</option>
                    <option value="Sertão" <?= $regiao === 'Sertão' ? 'selected' : '' ?>>Sertão</option>
                </select>
            </div>

            <div class="filtro-group">
                <label>Cidade</label>
                <select name="cidades" onchange="this.form.submit()">
                    <option value="">Todas as cidades</option>
                    <?php foreach ($cidades as $cid): ?>
                        <option value="<?= htmlspecialchars($cid) ?>" <?= $cidade === $cid ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cid) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
                       
            
            <div class="filtro-group">
                <label>Cliente</label>
                <select name="cliente" onchange="this.form.submit()">
                    <option value="">Todos os clientes</option>
                    <?php foreach ($clientes as $cli): ?>
                        <option value="<?= htmlspecialchars($cli) ?>" <?= $cliente === $cli ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cli) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>


               <div class="filtro-group">
                <label>Corredor</label>
                <select name="corredor" onchange="this.form.submit()">
                    <option value="">Todos os corredores</option>
                    <?php foreach ($corredores as $cor): ?>
                        <option value="<?= htmlspecialchars($cor) ?>" <?= $corredor === $cor ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cor) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            

             <div class="filtro-group">
                <label>Situação</label>
                <select name="situacao" onchange="this.form.submit()">
                    <option value="">Situações</option>
                    <option value="Disponivel" <?= $situacao === 'Disponivel' ? 'selected' : '' ?>>Disponivel</option>
                    <option value="Ocupado" <?= $situacao === 'Ocupado' ? 'selected' : '' ?>>Ocupado</option>
                    <option value="Reservado" <?= $situacao === 'Reservado' ? 'selected' : '' ?>>Reservado</option>
                    <option value="Vencido" <?= $situacao === 'Vencido' ? 'selected' : '' ?>>Vencido</option>
                </select>
            </div>         

            <a href="?" class="btn-reset">Limpar Filtros</a>
        </form>
    </div>
    
    <div class="stats">
        <div class="stats-number"><?= number_format($total) ?></div>
        <div class="stats-text">
            <?= $total === 1 ? 'ponto encontrado' : 'pontos encontrados' ?>
            <?php if ($busca): ?>
                para "<strong><?= htmlspecialchars($busca) ?></strong>"
            <?php endif; ?>
            <?php if ($tipo || $regiao || $situacao || $cliente || $cidade): ?>
                <span style="color: #999;">
                    com filtros ativos
                </span>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Logradouro</th>
                    <th>Cidade</th>
                    <th>Cliente</th>
                    <th>Tipo</th>
                    <th>Situação</th>
                    <th>Vencimento</th>
                    <th>Detalhes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pontos)): ?>
                <tr>
                    <td colspan="8" class="empty-state">
                        <div class="empty-state-icon">🔍</div>
                        <div>Nenhum ponto encontrado com os filtros selecionados</div>
                        <div style="margin-top: 0.5rem; font-size: 0.8rem; color: var(--color-text-muted);">
                             Tente ajustar os filtros ou fazer uma nova busca
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($pontos as $ponto): ?>
                    <tr>
                        <td class="numero-col"><?= htmlspecialchars($ponto['numero'] ?? '-') ?></td>
                        <td>
                            <div style="font-weight: 600;"><?= htmlspecialchars($ponto['logradouro'] ?? '-') ?></div>
                            <?php if (!empty($ponto['descricao'])): ?>
                                <div style="font-size: 0.8rem; color: var(--color-text-muted); margin-top: 2px;">
                                    <?= htmlspecialchars(substr($ponto['descricao'], 0, 60)) ?><?= strlen($ponto['descricao']) > 60 ? '...' : '' ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div><?= htmlspecialchars($ponto['cidade'] ?? '-') ?></div>
                            <?php if (!empty($ponto['regiao'])): ?>
                                <div style="font-size: 0.8rem; color: var(--color-text-muted);"><?= htmlspecialchars($ponto['regiao']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div><?= htmlspecialchars($ponto['cliente'] ?? '-') ?></div>
                            <?php if (!empty($ponto['agencia'])): ?>
                                <div style="font-size: 0.8rem; color: var(--color-text-muted);"><?= htmlspecialchars($ponto['agencia']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($ponto['tipo'] ?? '-') ?></td>
                        <td><?= badgeSituacao($ponto['situacao'] ?? 'N/A') ?></td>
                        <td>
                            <div style="margin-bottom: 4px;"><?= formatarData($ponto['fim_contrato_clean']) ?></div>
                            <?= calcularStatus($ponto['fim_contrato_clean']) ?>
                        </td>
                        <td>
    <a href="detalhes_ponto.php?id=<?= urlencode($ponto['id']) ?>" 
       target="_blank" 
       class="link-info">
        +Info
    </a>
</td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($totalPaginas > 1): ?>
    <div class="paginacao">
        <?php if ($pagina > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])) ?>">« Anterior</a>
        <?php endif; ?>
        
        <?php
        $inicio = max(1, $pagina - 2);
        $fim = min($totalPaginas, $pagina + 2);
        
        for ($i = $inicio; $i <= $fim; $i++):
        ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>"
               class="<?= $i == $pagina ? 'ativo' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
        
        <?php if ($pagina < $totalPaginas): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])) ?>">Próximo »</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const buscaInput = document.querySelector('.busca-input');
    
    if (buscaInput && !buscaInput.value) {
        buscaInput.focus();
    }
    
    if (buscaInput && buscaInput.value.trim()) {
        const termo = buscaInput.value.trim();
        const cells = document.querySelectorAll('.table td');
        
        cells.forEach(cell => {
            const texto = cell.textContent;
            if (texto && texto.toLowerCase().includes(termo.toLowerCase())) {
                const regex = new RegExp('(' + termo.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                cell.innerHTML = cell.innerHTML.replace(
                    regex,
                    '<mark style="background: #fff3cd; padding: 2px 4px; border-radius: 3px;">$1</mark>'
                );
            }
        });
    }
    
    const selects = document.querySelectorAll('select[onchange]');
    selects.forEach(select => {
        select.addEventListener('change', function() {
            document.body.classList.add('loading');
        });
    });
    
    window.addEventListener('pageshow', function() {
        document.body.classList.remove('loading');
    });
    
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            buscaInput.focus();
            buscaInput.select();
        }
    });
});
</script>

</body>
</html>