<?php
session_start();

// Verificar se usuário está logado
if (!isset($_SESSION['usuario'])) {
    header("Location: ../../../../public/index.php?erro=nao_logado");
    exit;
}

$paginaAtual = 'pre_selecao';

// Gerar token CSRF se não existir
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// INICIALIZAR VARIÁVEIS
$erro = '';
$sucesso = '';
$pontos_encontrados = [];

// PROCESSAR FORMULÁRIO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Token Check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['_token'] ?? '')) {
        die("Erro de segurança: Token CSRF inválido.");
    }
    
    $cliente = trim($_POST['cliente'] ?? '');
    $agencia = trim($_POST['agencia'] ?? '');
    $numeracao = trim($_POST['numeracao'] ?? '');
    
    if (empty($cliente)) {
        $erro = "Cliente é obrigatório";
    } elseif (empty($numeracao)) {
        $erro = "Numeração dos pontos é obrigatória";
    } else {
        // Processar numeração
        $numeros = array_map('trim', explode(',', $numeracao));
        $numeros = array_filter($numeros);
        $numeros = array_slice($numeros, 0, 100);
        
        if (empty($numeros)) {
            $erro = "Nenhum número válido foi informado";
        } else {
            try {
                // Conectar ao banco remoto
                require_once __DIR__ . '/../../../../config/database.php';
                $pdo = getDatabase();
                
                // Buscar pontos
                $placeholders = implode(',', array_fill(0, count($numeros), '?'));
                $sql = "SELECT numero, logradouro, descricao, cidade, regiao, tipo, situacao, 
                               cliente, agencia, inicio_contrato, fim_contrato
                        FROM pontos 
                        WHERE numero IN ($placeholders)
                        ORDER BY FIELD(numero, " . implode(',', array_fill(0, count($numeros), '?')) . ")";
                
                $stmt = $pdo->prepare($sql);
                $params = array_merge($numeros, $numeros);
                $stmt->execute($params);
                $pontos_encontrados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($pontos_encontrados)) {
                    $erro = "Nenhum ponto foi encontrado com os números informados";
                } else {
                    $sucesso = "Encontrados " . count($pontos_encontrados) . " pontos";
                }
                
            } catch (Exception $e) {
                $erro = "Erro ao buscar pontos: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pré-Seleção - Impakto</title>
    
    <link rel="icon" href="/impaktonew/public/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet"> 
    <link rel="stylesheet" href="/impaktonew/public/assets/css/gestor.css"> 
</head>
<body>

<div class="header">
    <div class="header-content">
        <div class="logo">
            <img src="/impaktonew/public/assets/img/logo.png" alt="Impakto Mídia" class="logo-img">
        </div>
        
        <nav class="main-nav">
            <a href="/impaktonew/gestor/index.php" class="nav-link <?= $paginaAtual === 'dashboard' ? 'active' : '' ?>">
                Dashboard
            </a>
            <a href="/impaktonew/app/Views/gestor/listar_ponto.php" class="nav-link <?= $paginaAtual === 'pontos' ? 'active' : '' ?>">
                Pontos
            </a>
            <a href="/impaktonew/app/Views/gestor/relatorios/pre_selecao.php" class="nav-link <?= $paginaAtual === 'pre_selecao' ? 'active' : '' ?>">
                Pré-Seleção
            </a>
            <a href="/impaktonew/app/Views/gestor/relatorios.php" class="nav-link <?= $paginaAtual === 'relatorios' ? 'active' : '' ?>">
                Relatórios
            </a>
            <a href="#" class="nav-link disabled" title="Em desenvolvimento">
                Google Maps
            </a>
        </nav>
        
        <div class="user-info">
            <span class="welcome-text">
                👋 Bem-vindo, <strong><?= htmlspecialchars($_SESSION['usuario']) ?></strong>
            </span>
            <a href="/impaktonew/gestor/index.php?logout=1" class="btn-logout" onclick="return confirm('Tem certeza que deseja sair?')">
                Sair
            </a>
        </div>
    </div>
</div>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>Pré-Seleção de Pontos</h2>
        </div>
        
        <?php if ($erro): ?>
            <div class="alert alert-error">
                <span>⚠️</span>
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($sucesso): ?>
            <div class="alert alert-success">
                <span>✅</span>
                <?= htmlspecialchars($sucesso) ?>
            </div>
        <?php endif; ?>
        
        <form method="post">
            <input type="hidden" name="_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="cliente">Cliente *</label>
                    <input type="text" name="cliente" id="cliente" required maxlength="100"
                            value="<?= htmlspecialchars($_POST['cliente'] ?? '') ?>"
                            placeholder="Nome do cliente">
                </div>
                
                <div class="form-group">
                    <label for="agencia">Agência</label>
                    <input type="text" name="agencia" id="agencia" maxlength="100"
                            value="<?= htmlspecialchars($_POST['agencia'] ?? '') ?>"
                            placeholder="Nome da agência (opcional)">
                </div>
            </div>
            
            <div class="form-group">
                <label for="numeracao">Numeração dos Pontos *</label>
                <textarea name="numeracao" id="numeracao" rows="5" required maxlength="1000"
                          placeholder="Digite os números separados por vírgula. Ex: 205, 206, 207, 208"><?= htmlspecialchars($_POST['numeracao'] ?? '') ?></textarea>
                <div class="form-text">
                    Máximo 100 pontos por consulta. Separe os números com vírgula.
                </div>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">
                    Gerar Pré-Seleção
                </button>
                <button type="reset" class="btn btn-secondary">
                    Limpar Campos
                </button>
                <a href="/impaktonew/app/Views/gestor/listar_ponto.php" class="btn btn-outline">
                    Voltar à Lista
                </a>
            </div>
        </form>
    </div>
    
    <?php if (!empty($pontos_encontrados)): ?>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Logradouro</th>
                    <th>Cidade</th>
                    <th>Tipo</th>
                    <th>Situação</th>
                    <th>Cliente Atual</th>
                    <th>Vencimento</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pontos_encontrados as $ponto): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($ponto['numero'] ?? '') ?></strong></td>
                    <td>
                        <div style="font-weight: 600;"><?= htmlspecialchars($ponto['logradouro'] ?? '') ?></div>
                        <?php if (!empty($ponto['descricao'])): ?>
                            <small class="text-muted"><?= htmlspecialchars(substr($ponto['descricao'], 0, 50)) ?><?= strlen($ponto['descricao']) > 50 ? '...' : '' ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div><?= htmlspecialchars($ponto['cidade'] ?? '') ?></div>
                        <?php if (!empty($ponto['regiao'])): ?>
                            <small class="text-muted"><?= htmlspecialchars($ponto['regiao']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($ponto['tipo'] ?? '') ?></td>
                    <td>
                        <?php 
                        $situacao = $ponto['situacao'] ?? '';
                        $badgeClass = 'badge-' . strtolower(str_replace(' ', '', $situacao));
                        ?>
                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($situacao) ?></span>
                    </td>
                    <td>
                        <div><?= htmlspecialchars($ponto['cliente'] ?? '-') ?></div>
                        <?php if (!empty($ponto['agencia'])): ?>
                            <small class="text-muted"><?= htmlspecialchars($ponto['agencia']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        $fim = $ponto['fim_contrato'] ?? '';
                        if ($fim && $fim !== '0000-00-00') {
                            try {
                                $date = new DateTime($fim);
                                echo $date->format('m/Y');
                            } catch (Exception $e) {
                                echo '-';
                            }
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="card card-acoes" style="margin-top: 2rem;">
        <h3 style="margin-bottom: 1rem; color: var(--color-text-dark);">📄 Ações com os Resultados</h3>
        <div class="btn-group">
            <button onclick="window.print()" class="btn btn-outline">
                Imprimir
            </button>
            <button onclick="exportToCSV()" class="btn btn-outline">
                Exportar CSV
            </button>
            <button onclick="copyToClipboard()" class="btn btn-outline">
                Copiar Lista
            </button>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('cliente').focus();
});

function exportToCSV() {
    const table = document.querySelector('.table');
    if (!table) return;
    
    let csv = '';
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        cols.forEach(col => {
            rowData.push('"' + col.textContent.trim().replace(/"/g, '""') + '"');
        });
        csv += rowData.join(',') + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'pre_selecao_pontos.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

function copyToClipboard() {
    const pontos = document.querySelectorAll('.table tbody tr');
    let texto = 'PONTOS SELECIONADOS:\n\n';
    
    pontos.forEach(ponto => {
        const numero = ponto.querySelector('td:nth-child(1)').textContent.trim();
        const logradouro = ponto.querySelector('td:nth-child(2)').textContent.trim();
        const cidade = ponto.querySelector('td:nth-child(3)').textContent.trim();
        texto += `${numero} - ${logradouro}, ${cidade}\n`;
    });
    
    navigator.clipboard.writeText(texto).then(() => {
        alert('Lista copiada para a área de transferência!');
    });
}
</script>

</body>
</html>