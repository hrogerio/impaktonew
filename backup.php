<?php
// AUDITORIA DE CAMPOS VAZIOS — pode deixar no servidor
// Protegido por login de sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

$paginaAtual = 'auditoria';

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDatabase();
    $total = (int)$pdo->query("SELECT COUNT(*) FROM pontos WHERE ativo = 1 OR ativo IS NULL")->fetchColumn();

    $campos = array(
        'numero','logradouro','descricao','bairro','cidade','sentido',
        'tipo','cliente','agencia','foto','regiao','formato',
        'situacao','inicio_contrato','fim_contrato','latitude','longitude',
        'observacoes','contato','corredor'
    );

    $resultado = array();
    foreach ($campos as $campo) {
        $vazios = (int)$pdo->query("
            SELECT COUNT(*) FROM pontos
            WHERE (ativo = 1 OR ativo IS NULL)
            AND ($campo IS NULL OR TRIM($campo) = '' OR $campo = '0000-00-00')
        ")->fetchColumn();
        $resultado[$campo] = $vazios;
    }

    // Ordenar por mais vazios primeiro
    arsort($resultado);

} catch (Exception $e) {
    die("<p style='color:red'>Erro: " . htmlspecialchars($e->getMessage()) . "</p>");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Auditoria de Campos</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
    <style>
        .audit-table { width:100%; border-collapse:collapse; font-size:0.85rem; }
        .audit-table th { background:var(--color-accent-primary); color:white; padding:0.6rem 1rem; text-align:left; font-size:0.78rem; font-weight:700; }
        .audit-table td { padding:0.6rem 1rem; border-bottom:1px solid var(--color-border); vertical-align:middle; }
        .audit-table tr:hover { background:#fafafa; }
        .bar-wrap { display:flex; align-items:center; gap:0.5rem; }
        .bar-bg { flex:1; height:10px; background:#f0f0f0; border-radius:5px; overflow:hidden; }
        .bar-fill { height:100%; border-radius:5px; transition:width 0.4s; }
        .tag { display:inline-block; padding:2px 8px; border-radius:12px; font-size:0.72rem; font-weight:700; }
        .tag-ok      { background:#dcfce7; color:#166534; }
        .tag-baixo   { background:#fef9c3; color:#854d0e; }
        .tag-medio   { background:#ffedd5; color:#9a3412; }
        .tag-alto    { background:#fee2e2; color:#991b1b; }
        .tag-critico { background:#fde8e8; color:#c0392b; }
        .impacto     { font-size:0.78rem; color:var(--color-text-muted); }
    </style>
</head>
<body>
<?php include __DIR__ . '/app/Views/partials/env_banner.php'; ?>

<?php require __DIR__ . '/app/Views/layouts/_nav.php'; ?>

<div class="container" style="padding-bottom:2rem">
    <div class="welcome" style="margin-bottom:1rem">
        <h2>🔍 Auditoria de Campos Vazios</h2>
        <p>Análise de preenchimento da tabela <strong>pontos</strong> — <?= $total ?> registros ativos.</p>
    </div>

    <div class="table-container">
        <table class="audit-table">
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Vazios</th>
                    <th>Preenchidos</th>
                    <th>% Vazio</th>
                    <th style="width:200px">Preenchimento</th>
                    <th>Nível</th>
                    <th>Impacto no sistema</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $impactos = array(
                'numero'          => array('Obrigatório — chave de identificação do ponto', 'critico'),
                'logradouro'      => array('Aparece na listagem e detalhes — vazio deixa linha em branco', 'alto'),
                'cidade'          => array('Usado nos filtros e relatórios de ocupação por cidade', 'alto'),
                'situacao'        => array('Determina os KPIs do dashboard e relatórios', 'critico'),
                'tipo'            => array('Filtro na listagem e campo nos relatórios', 'medio'),
                'regiao'          => array('Filtro na listagem e agrupamento nos relatórios', 'medio'),
                'cliente'         => array('Relatório de pontos por cliente fica incompleto', 'medio'),
                'agencia'         => array('Relatório de clientes por agência fica incompleto', 'medio'),
                'fim_contrato'    => array('Ordenação padrão da listagem e relatório de contratos', 'medio'),
                'inicio_contrato' => array('Relatório de contratos — data de início ausente', 'baixo'),
                'foto'            => array('Imagem não aparece na tela de detalhes do ponto', 'baixo'),
                'latitude'        => array('Mapa do Google Maps não posiciona o ponto', 'baixo'),
                'longitude'       => array('Mapa do Google Maps não posiciona o ponto', 'baixo'),
                'formato'         => array('Campo informativo — não afeta filtros principais', 'baixo'),
                'descricao'       => array('Texto de apoio nos detalhes — ausência não quebra nada', 'baixo'),
                'corredor'        => array('Filtro secundário na listagem', 'baixo'),
                'contato'         => array('Campo informativo nos detalhes', 'baixo'),
                'bairro'          => array('Campo informativo — não usado em filtros', 'ok'),
                'sentido'         => array('Campo informativo — não usado em filtros', 'ok'),
                'observacoes'     => array('Campo livre — ausência esperada', 'ok'),
            );

            $cores = array(
                'ok'      => array('#86efac', '#166534', 'OK'),
                'baixo'   => array('#fde68a', '#854d0e', 'Baixo'),
                'medio'   => array('#fdba74', '#9a3412', 'Médio'),
                'alto'    => array('#fca5a5', '#991b1b', 'Alto'),
                'critico' => array('#f87171', '#7f1d1d', 'Crítico'),
            );

            foreach ($resultado as $campo => $vazios):
                $preenchidos = $total - $vazios;
                $pctVazio    = $total > 0 ? round(($vazios / $total) * 100, 1) : 0;
                $pctPreench  = 100 - $pctVazio;

                // Cor da barra baseada no % vazio
                if ($pctVazio == 0)      $barCor = '#22c55e';
                elseif ($pctVazio <= 20) $barCor = '#84cc16';
                elseif ($pctVazio <= 50) $barCor = '#f59e0b';
                elseif ($pctVazio <= 80) $barCor = '#f97316';
                else                     $barCor = '#ef4444';

                $info  = isset($impactos[$campo]) ? $impactos[$campo][0] : '-';
                $nivel = isset($impactos[$campo]) ? $impactos[$campo][1] : 'ok';
                $cor   = $cores[$nivel];
            ?>
            <tr>
                <td><code><?= $campo ?></code></td>
                <td><strong><?= $vazios ?></strong></td>
                <td><?= $preenchidos ?></td>
                <td><strong><?= $pctVazio ?>%</strong></td>
                <td>
                    <div class="bar-wrap">
                        <div class="bar-bg">
                            <div class="bar-fill" style="width:<?= $pctPreench ?>%;background:<?= $barCor ?>"></div>
                        </div>
                        <span style="font-size:0.72rem;font-weight:700;width:32px;text-align:right"><?= $pctPreench ?>%</span>
                    </div>
                </td>
                <td>
                    <span class="tag tag-<?= $nivel ?>"><?= $cor[2] ?></span>
                </td>
                <td class="impacto"><?= $info ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Legenda -->
    <div style="margin-top:1rem;display:flex;gap:1rem;flex-wrap:wrap;font-size:0.78rem;">
        <span class="tag tag-critico">Crítico</span> Campo obrigatório para funcionamento
        &nbsp;
        <span class="tag tag-alto">Alto</span> Afeta listagem e filtros
        &nbsp;
        <span class="tag tag-medio">Médio</span> Afeta relatórios
        &nbsp;
        <span class="tag tag-baixo">Baixo</span> Afeta exibição visual
        &nbsp;
        <span class="tag tag-ok">OK</span> Campo opcional
    </div>
</div>
</body>
</html>