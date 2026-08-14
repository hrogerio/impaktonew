<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

$paginaAtual = 'pontos';

try {
    require_once __DIR__ . '/../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// Buscar TODOS os pontos ativos para o JS filtrar no browser
$sql = "
    SELECT
        id, numero, logradouro, descricao, cidade, regiao,
        cliente, agencia, tipo, situacao, corredor,
        CASE
            WHEN fim_contrato IS NULL OR fim_contrato = '0000-00-00' OR fim_contrato = ''
            THEN NULL
            ELSE DATE(fim_contrato)
        END AS fim_contrato
    FROM pontos
    WHERE ativo = 1 OR ativo IS NULL
    ORDER BY numero ASC
";
$pontos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Listas para os filtros
$regioes   = $pdo->query("SELECT DISTINCT regiao   FROM pontos WHERE regiao   IS NOT NULL AND regiao   != '' AND (ativo=1 OR ativo IS NULL) ORDER BY regiao"  )->fetchAll(PDO::FETCH_COLUMN);
$cidades   = $pdo->query("SELECT DISTINCT cidade   FROM pontos WHERE cidade   IS NOT NULL AND cidade   != '' AND (ativo=1 OR ativo IS NULL) ORDER BY cidade"  )->fetchAll(PDO::FETCH_COLUMN);
$clientes  = $pdo->query("SELECT DISTINCT cliente  FROM pontos WHERE cliente  IS NOT NULL AND cliente  != '' AND (ativo=1 OR ativo IS NULL) ORDER BY cliente" )->fetchAll(PDO::FETCH_COLUMN);
$corredores= $pdo->query("SELECT DISTINCT corredor FROM pontos WHERE corredor IS NOT NULL AND corredor != '' AND (ativo=1 OR ativo IS NULL) ORDER BY corredor")->fetchAll(PDO::FETCH_COLUMN);

// Serializar pontos para JSON (seguro)
$pontosJson = json_encode($pontos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pontos · SGI</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css">
    <link rel="stylesheet" href="/public/assets/css/pontos.css">
</head>
<body>

<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="container" style="padding-bottom:2rem;">

    <!-- Busca instantânea -->
    <div class="search-bar">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Buscar por número, logradouro, cidade, cliente..." autocomplete="off">
        <button class="search-clear" id="searchClear" title="Limpar">✕</button>
        <span class="search-kbd">Ctrl+K</span>
    </div>

    <!-- Filtros dropdown -->
    <div class="filtros-wrap">
        <select class="filtro-select" id="filtroRegiao">
            <option value="">Todas as regiões</option>
            <?php foreach ($regioes as $r): ?>
            <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
            <?php endforeach; ?>
        </select>

        <select class="filtro-select" id="filtroCidade">
            <option value="">Todas as cidades</option>
            <?php foreach ($cidades as $c): ?>
            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
        </select>

        <select class="filtro-select" id="filtroCliente">
            <option value="">Todos os clientes</option>
            <?php foreach ($clientes as $c): ?>
            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
        </select>

        <select class="filtro-select" id="filtroSituacao">
            <option value="">Todas as situações</option>
            <option value="Disponivel">Disponível</option>
            <option value="Ocupado">Ocupado</option>
            <option value="Reservado">Reservado</option>
            <option value="Vencido">Vencido</option>
           
        </select>

        <select class="filtro-select" id="filtroCorredor">
            <option value="">Todos os corredores</option>
            <?php foreach ($corredores as $c): ?>
            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
        </select>

        <select class="filtro-select" id="filtroTipo">
            <option value="">Todos os tipos</option>
             <option value="Painel">Painel</option>
            <option value="Outdoor">Outdoor</option>
            <option value="Frontlight">Frontlight</option>
            <option value="Painel LED">LED</option>        
           
        </select>

        <button class="btn-limpar-filtros" id="btnLimpar">Limpar filtros</button>
    </div>

    <!-- Contador de resultados -->
    <div class="result-bar">
        <span><span class="result-count" id="resultCount">0</span> <span class="result-total" id="resultTotal"></span></span>
        <span id="sortInfo" style="font-size:0.75rem;color:var(--color-text-muted);"></span>
    </div>

    <!-- Tabela -->
    <div class="table-container">
        <table class="table" id="tabelaPontos">
            <thead>
                <tr>
                    <th data-col="numero">Nº<span class="sort-icon"></span></th>
                    <th data-col="logradouro">Logradouro<span class="sort-icon"></span></th>
                    <th data-col="cidade" class="col-regiao">Cidade / Região<span class="sort-icon"></span></th>
                    <th data-col="cliente">Cliente<span class="sort-icon"></span></th>
                    <th data-col="tipo" class="col-tipo">Tipo<span class="sort-icon"></span></th>
                    <th data-col="situacao">Situação<span class="sort-icon"></span></th>
                    <th data-col="fim_contrato">Vencimento<span class="sort-icon"></span></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tabelaBody">
                <!-- Preenchido via JS -->
            </tbody>
        </table>
    </div>

</div>

<script>
var PONTOS = <?= $pontosJson ?>;
var sortCol = 'fim_contrato';
var sortDir = 'asc';
var filtros = { busca:'', regiao:'', cidade:'', cliente:'', situacao:'', corredor:'', tipo:'' };

function badgeSit(sit) {
    if (!sit) return '<span class="badge-sit sit-outro">-</span>';
    var s = sit.trim().toLowerCase().replace(/\s+/g,'');
    var cls = 'sit-outro';
    if (s==='disponível'||s==='disponivel') cls='sit-disponivel';
    else if (s==='ocupado') cls='sit-ocupado';
    else if (s==='reservado') cls='sit-reservado';
    else if (s==='vencido') cls='sit-vencido';
    else if (s==='permuta') cls='sit-permuta';
    else if (s==='bisemana') cls='sit-bisemana';
    return '<span class="badge-sit '+cls+'">'+esc(sit)+'</span>';
}

function badgeContrato(data) {
    if (!data) return '<span class="badge-contrato ctr-sem">Sem prazo</span>';
    var fim = new Date(data);
    var hoje = new Date();
    hoje.setHours(0,0,0,0);
    fim.setHours(0,0,0,0);
    var dias = Math.round((fim - hoje) / 86400000);
    var mes = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'][fim.getMonth()];
    var label = mes + '/' + fim.getFullYear().toString().slice(2);
    if (dias < 0)   return '<div style="font-size:0.78rem;font-weight:600;color:#6b21a8;">'+label+'</div><span class="badge-contrato ctr-vencido">Vencido há '+Math.abs(dias)+'d</span>';
    if (dias <= 7)  return '<div style="font-size:0.78rem;font-weight:600;">'+label+'</div><span class="badge-contrato ctr-critico">'+dias+' dias</span>';
    if (dias <= 30) return '<div style="font-size:0.78rem;font-weight:600;">'+label+'</div><span class="badge-contrato ctr-urgente">'+dias+' dias</span>';
    if (dias <= 90) return '<div style="font-size:0.78rem;font-weight:600;">'+label+'</div><span class="badge-contrato ctr-atencao">'+Math.floor(dias/30)+'m</span>';
    return '<div style="font-size:0.78rem;font-weight:600;">'+label+'</div><span class="badge-contrato ctr-ok">'+Math.floor(dias/30)+'m</span>';
}

function esc(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function highlight(text, termo) {
    if (!termo || !text) return esc(text||'');
    var re = new RegExp('(' + termo.replace(/[.*+?^${}()|[\]\\]/g,'\\$&') + ')', 'gi');
    return esc(text).replace(re, '<mark>$1</mark>');
}

function normalizar(str) {
    return (str||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
}

function filtrar(pontos) {
    var busca = normalizar(filtros.busca);
    return pontos.filter(function(p) {
        if (filtros.regiao   && (p.regiao   ||'').trim() !== filtros.regiao)   return false;
        if (filtros.cidade   && (p.cidade   ||'').trim() !== filtros.cidade)   return false;
        if (filtros.cliente  && (p.cliente  ||'').trim() !== filtros.cliente)  return false;
        if (filtros.situacao && (p.situacao ||'').trim() !== filtros.situacao) return false;
        if (filtros.corredor && (p.corredor ||'').trim() !== filtros.corredor) return false;
        if (filtros.tipo     && (p.tipo     ||'').trim() !== filtros.tipo)     return false;
        if (busca) {
            var campos = [p.numero, p.logradouro, p.descricao, p.cidade, p.regiao, p.cliente, p.agencia, p.tipo, p.corredor];
            var encontrou = false;
            for (var i=0; i<campos.length; i++) {
                if (normalizar(campos[i]).indexOf(busca) !== -1) { encontrou = true; break; }
            }
            if (!encontrou) return false;
        }
        return true;
    });
}

function ordenar(pontos) {
    var col = sortCol;
    var dir = sortDir === 'asc' ? 1 : -1;
    return pontos.slice().sort(function(a, b) {
        var va = a[col] || '';
        var vb = b[col] || '';
        if (col === 'numero') {
            va = parseInt(va) || 0;
            vb = parseInt(vb) || 0;
            return (va - vb) * dir;
        }
        if (col === 'fim_contrato') {
            // Sem prazo sempre vai para o final (independente da direção)
            var semA = !va;
            var semB = !vb;
            if (semA && semB) return 0;
            if (semA) return 1;
            if (semB) return -1;
            va = new Date(va).getTime();
            vb = new Date(vb).getTime();
            return (va - vb) * dir;
        }
        va = normalizar(va);
        vb = normalizar(vb);
        return va < vb ? -dir : va > vb ? dir : 0;
    });
}

function renderizar() {
    var resultado = ordenar(filtrar(PONTOS));
    var tbody = document.getElementById('tabelaBody');
    var busca = filtros.busca;

    var total = PONTOS.length;
    var filtrado = resultado.length;
    var temFiltro = filtros.busca || filtros.regiao || filtros.cidade || filtros.cliente ||
                    filtros.situacao || filtros.corredor || filtros.tipo;

    if (temFiltro) {
        document.getElementById('resultCount').innerHTML =
            '<strong style="font-size:1.1rem;color:var(--color-accent-primary)">' + filtrado + '</strong>' +
            '<span style="color:var(--color-text-muted);font-weight:400;"> ' +
            (filtrado === 1 ? 'resultado' : 'resultados') +
            ' &nbsp;·&nbsp; </span>' +
            '<span style="color:var(--color-text-muted);font-weight:400;">' + total + ' pontos no total</span>';
        document.getElementById('resultTotal').textContent = '';
    } else {
        document.getElementById('resultCount').innerHTML =
            '<strong style="font-size:1.1rem;color:var(--color-text-dark)">' + total + '</strong>' +
            '<span style="color:var(--color-text-muted);font-weight:400;"> ' +
            (total === 1 ? 'ponto cadastrado' : 'pontos cadastrados') + '</span>';
        document.getElementById('resultTotal').textContent = '';
    }

    document.getElementById('btnLimpar').className = 'btn-limpar-filtros' + (temFiltro ? ' visible' : '');

    if (resultado.length === 0) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="8"><div class="empty-icon">🔍</div><div>Nenhum ponto encontrado</div></td></tr>';
        return;
    }

    var html = '';
    for (var i=0; i<resultado.length; i++) {
        var p = resultado[i];
        html += '<tr>';
        html += '<td class="col-num">' + highlight(p.numero, busca) + '</td>';
        html += '<td><div class="col-local-main">' + highlight(p.logradouro, busca) + '</div>';
        if (p.descricao) html += '<div class="col-local-sub">' + highlight(p.descricao.substring(0,60) + (p.descricao.length>60?'...':''), busca) + '</div>';
        html += '</td>';
        html += '<td class="col-regiao"><div>' + highlight(p.cidade, busca) + '</div>';
        if (p.regiao) html += '<div class="col-local-sub">' + highlight(p.regiao, busca) + '</div>';
        html += '</td>';
        html += '<td><div class="col-cliente-main">' + highlight(p.cliente||'-', busca) + '</div>';
        if (p.agencia) html += '<div class="col-cliente-sub">' + highlight(p.agencia, busca) + '</div>';
        html += '</td>';
        html += '<td class="col-tipo">' + esc(p.tipo||'-') + '</td>';
        html += '<td>' + badgeSit(p.situacao) + '</td>';
        html += '<td>' + badgeContrato(p.fim_contrato) + '</td>';
        html += '<td><a href="/gestor/pontos/detalhes?id=' + encodeURIComponent(p.id) + '" class="link-info">+Info</a></td>';
        html += '</tr>';
    }
    tbody.innerHTML = html;
}

// Busca com debounce
var debounceTimer;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(debounceTimer);
    var val = this.value;
    document.getElementById('searchClear').className = 'search-clear' + (val ? ' visible' : '');
    debounceTimer = setTimeout(function() {
        filtros.busca = val;
        renderizar();
    }, 120);
});

document.getElementById('searchClear').addEventListener('click', function() {
    document.getElementById('searchInput').value = '';
    this.className = 'search-clear';
    filtros.busca = '';
    renderizar();
    document.getElementById('searchInput').focus();
});

// Filtros dropdown
var mapaFiltros = {
    'filtroRegiao':   'regiao',
    'filtroCidade':   'cidade',
    'filtroCliente':  'cliente',
    'filtroSituacao': 'situacao',
    'filtroCorredor': 'corredor',
    'filtroTipo':     'tipo'
};
Object.keys(mapaFiltros).forEach(function(id) {
    document.getElementById(id).addEventListener('change', function() {
        filtros[mapaFiltros[id]] = this.value;
        this.className = 'filtro-select' + (this.value ? ' ativo' : '');
        renderizar();
    });
});

// Limpar tudo
document.getElementById('btnLimpar').addEventListener('click', function() {
    filtros = { busca:'', regiao:'', cidade:'', cliente:'', situacao:'', corredor:'', tipo:'' };
    document.getElementById('searchInput').value = '';
    document.getElementById('searchClear').className = 'search-clear';
    Object.keys(mapaFiltros).forEach(function(id) {
        document.getElementById(id).value = '';
        document.getElementById(id).className = 'filtro-select';
    });
    renderizar();
});

// Ordenação por coluna
document.querySelectorAll('.table thead th[data-col]').forEach(function(th) {
    th.addEventListener('click', function() {
        var col = this.getAttribute('data-col');
        if (sortCol === col) {
            sortDir = sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            sortCol = col;
            sortDir = 'asc';
        }
        document.querySelectorAll('.table thead th').forEach(function(t) {
            t.classList.remove('sort-asc','sort-desc');
        });
        this.classList.add('sort-' + sortDir);
        renderizar();
    });
});

// Ctrl+K foca na busca
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.getElementById('searchInput').focus();
        document.getElementById('searchInput').select();
    }
});

// Render inicial — marca coluna de vencimento como ativa
renderizar();
document.querySelector('th[data-col="fim_contrato"]').classList.add('sort-asc');
document.getElementById('searchInput').focus();
</script>

</body>
</html>