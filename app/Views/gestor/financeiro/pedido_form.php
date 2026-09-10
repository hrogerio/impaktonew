<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}
$paginaAtual = 'financeiro';

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/_helpers.php';
$pdo = getDatabase();

$id = (int)($_GET['id'] ?? 0);
$pedido   = null;
$itens    = [];
$parcelas = [];

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM pedidos_financeiros WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pedido) { http_response_code(404); die('Pedido não encontrado.'); }

    $stmtI = $pdo->prepare("SELECT * FROM pedidos_financeiros_itens WHERE pedido_id = ? ORDER BY ordem ASC, id ASC");
    $stmtI->execute([$id]);
    $itens = $stmtI->fetchAll(PDO::FETCH_ASSOC);

    $stmtP = $pdo->prepare("SELECT * FROM pedidos_financeiros_parcelas WHERE pedido_id = ? ORDER BY numero ASC, id ASC");
    $stmtP->execute([$id]);
    $parcelas = $stmtP->fetchAll(PDO::FETCH_ASSOC);

    $tipo = $pedido['tipo'];
} else {
    $tipo = in_array($_GET['tipo'] ?? '', ['PI', 'PP'], true) ? $_GET['tipo'] : null;
    if (!$tipo) { http_response_code(400); die('Informe o tipo do pedido (PI ou PP).'); }
}

$tituloTipo = $tipo === 'PI' ? 'Pedido de Inserção' : 'Pedido de Produção';

// Cadastro de clientes, pra autopreencher o formulário ao digitar
$clientesCadastro = $pdo->query("
    SELECT razao_social, cnpj, endereco, email, telefone
    FROM clientes WHERE ativo = 1 ORDER BY razao_social ASC
")->fetchAll(PDO::FETCH_ASSOC);

function v($val) { return htmlspecialchars((string)($val ?? '')); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= v($tituloTipo) ?> · Impakto</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css?v=2">
    <style>
        .pf-page { max-width:1100px; margin:0 auto; padding:1.5rem 1.5rem 4rem; }
        .pf-section { margin-bottom:1.5rem; }
        .pf-section h2 {
            font-size:0.78rem; font-weight:800; text-transform:uppercase; letter-spacing:0.4px;
            color:var(--color-text-muted); margin:0 0 0.75rem; padding-bottom:0.4rem;
            border-bottom:1px solid var(--color-border);
        }
        .pf-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:0.9rem; }
        .pf-grid .full { grid-column:1 / -1; }
        .pf-itens-table, .pf-parcelas-table { width:100%; border-collapse:collapse; margin-bottom:0.75rem; }
        .pf-itens-table th, .pf-parcelas-table th {
            text-align:left; font-size:0.68rem; text-transform:uppercase; letter-spacing:0.3px;
            color:var(--color-text-muted); padding:0.4rem 0.4rem; border-bottom:1px solid var(--color-border);
        }
        .pf-itens-table td, .pf-parcelas-table td { padding:0.3rem; vertical-align:top; }
        .pf-itens-table input, .pf-itens-table select, .pf-parcelas-table input {
            width:100%; box-sizing:border-box; padding:0.4rem 0.5rem; border:1px solid var(--color-border);
            border-radius:6px; font-family:'Montserrat',sans-serif; font-size:0.82rem; background:white;
        }
        .pf-itens-table input[readonly] { background:#f8f9fb; color:var(--color-text-muted); }
        .pf-rm-item { background:none; border:none; color:#dc2626; font-weight:800; cursor:pointer; font-size:1rem; padding:0.3rem 0.5rem; }
        .pf-total-row td { font-weight:800; padding-top:0.6rem; }
        .pf-actions { display:flex; gap:0.6rem; margin-top:1.5rem; }
        .pf-hint { font-size:0.72rem; color:var(--color-text-muted); margin-top:0.25rem; }
        .pf-inline-btn { display:flex; gap:0.5rem; align-items:flex-end; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../../partials/env_banner.php'; ?>
<?php require __DIR__ . '/../../layouts/_nav.php'; ?>

<div class="pf-page">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.75rem">
        <h1 style="font-size:1.3rem;font-weight:800;color:var(--color-text-dark);margin:0">
            <?= $tipo === 'PI' ? '📄' : '🛠️' ?> <?= v($tituloTipo) ?>
            <?php if ($pedido): ?>
                <span style="color:var(--color-text-muted);font-weight:700"> · <?= v(numeroPedidoFmt($pedido['tipo'], $pedido['numero_data'], (int)$pedido['numero_seq'])) ?></span>
            <?php endif; ?>
        </h1>
        <a href="/gestor/financeiro/pedidos" class="btn btn-reset">← Voltar</a>
    </div>

    <div class="card" style="padding:1.5rem">
        <form id="formPedido">
            <input type="hidden" id="f_id" value="<?= (int)($pedido['id'] ?? 0) ?>">
            <input type="hidden" id="f_tipo" value="<?= v($tipo) ?>">

            <div class="pf-section">
                <h2>Dados do Pedido</h2>
                <div class="pf-grid">
                    <div class="form-group">
                        <label>Data de emissão</label>
                        <input type="date" id="f_data_emissao" value="<?= v($pedido['data_emissao'] ?? date('Y-m-d')) ?>">
                        <?php if ($pedido): ?><div class="pf-hint">Alterar não muda a numeração já emitida.</div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Período — início</label>
                        <input type="month" id="f_periodo_inicio" value="<?= v(substr($pedido['periodo_inicio'] ?? '', 0, 7)) ?>">
                    </div>
                    <div class="form-group">
                        <label>Período — fim</label>
                        <input type="month" id="f_periodo_fim" value="<?= v(substr($pedido['periodo_fim'] ?? '', 0, 7)) ?>">
                        <div class="pf-hint" id="periodoResumo">&nbsp;</div>
                    </div>
                    <div class="form-group full">
                        <label>Nome da campanha</label>
                        <input type="text" id="f_nome_campanha" value="<?= v($pedido['nome_campanha'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="pf-section">
                <h2>Dados para Faturamento (Cliente)</h2>
                <div class="pf-grid">
                    <div class="form-group full">
                        <label>Razão social *</label>
                        <input type="text" id="f_cliente_razao_social" list="dlClientes" required value="<?= v($pedido['cliente_razao_social'] ?? '') ?>">
                        <datalist id="dlClientes">
                            <?php foreach ($clientesCadastro as $c): ?>
                                <option value="<?= v($c['razao_social']) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label>CNPJ</label>
                        <input type="text" id="f_cliente_cnpj" value="<?= v($pedido['cliente_cnpj'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>I.E.</label>
                        <input type="text" id="f_cliente_ie" placeholder="ISENTO" value="<?= v($pedido['cliente_ie'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" id="f_cliente_telefone" value="<?= v($pedido['cliente_telefone'] ?? '') ?>">
                    </div>
                    <div class="form-group full">
                        <label>Endereço</label>
                        <input type="text" id="f_cliente_endereco" value="<?= v($pedido['cliente_endereco'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Cidade</label>
                        <input type="text" id="f_cliente_cidade" value="<?= v($pedido['cliente_cidade'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>CEP</label>
                        <input type="text" id="f_cliente_cep" value="<?= v($pedido['cliente_cep'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>E-mail</label>
                        <input type="email" id="f_cliente_email" value="<?= v($pedido['cliente_email'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="pf-section">
                <h2>Itens</h2>
                <table class="pf-itens-table" id="tabelaItens">
                    <thead>
                        <tr id="cabecalhoItens"></tr>
                    </thead>
                    <tbody id="corpoItens"></tbody>
                    <tfoot>
                        <tr class="pf-total-row" id="rodapeItens"></tr>
                    </tfoot>
                </table>
                <button type="button" class="btn btn-outline" onclick="adicionarItem()">+ Adicionar item</button>
            </div>

            <div class="pf-section">
                <h2>Forma de Pagamento</h2>
                <div class="pf-grid">
                    <div class="form-group">
                        <label>Quantidade de parcelas</label>
                        <input type="number" min="1" step="1" id="f_qtd_parcelas" value="<?= (int)($pedido['qtd_parcelas'] ?? 1) ?>">
                        <div class="pf-hint">1 = pagamento único · &gt;1 = valor mensal recorrente</div>
                    </div>
                    <div class="form-group">
                        <label>Valor bruto (total do contrato)</label>
                        <div class="pf-inline-btn">
                            <input type="number" step="0.01" id="f_valor_bruto" value="<?= v($pedido['valor_bruto'] ?? '') ?>" style="flex:1">
                            <button type="button" class="btn btn-outline" onclick="sugerirValorBruto()" title="Valor mensal × quantidade de parcelas">Sugerir</button>
                        </div>
                    </div>
                </div>

                <table class="pf-parcelas-table" id="tabelaParcelas" style="margin-top:1rem">
                    <thead>
                        <tr><th style="width:60px">Parcela</th><th>Vencimento</th><th>Valor</th><th></th></tr>
                    </thead>
                    <tbody id="corpoParcelas"></tbody>
                </table>
                <div style="display:flex;gap:0.6rem;flex-wrap:wrap;align-items:flex-end">
                    <div class="form-group" style="margin:0">
                        <label style="font-size:0.72rem">1º vencimento</label>
                        <input type="date" id="f_primeiro_vencimento" value="<?= v($parcelas[0]['data_vencimento'] ?? date('Y-m-d')) ?>">
                    </div>
                    <button type="button" class="btn btn-outline" onclick="gerarParcelas()">↻ Gerar parcelas (mensal)</button>
                    <button type="button" class="btn btn-outline" onclick="adicionarParcela()">+ Adicionar parcela</button>
                </div>
            </div>

            <div class="pf-section">
                <h2>Observações</h2>
                <div class="form-group full">
                    <textarea id="f_observacoes" rows="3"><?= v($pedido['observacoes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="pf-section">
                <label style="display:flex;align-items:center;gap:0.5rem;font-weight:700;font-size:0.85rem">
                    <input type="checkbox" id="f_status" style="width:auto" <?= (($pedido['status'] ?? '') === 'emitido') ? 'checked' : '' ?>>
                    Marcar como emitido (fecha o pedido pra edição de valores pela equipe)
                </label>
            </div>

            <div class="pf-actions">
                <button type="submit" class="btn btn-primary">Salvar</button>
                <button type="button" class="btn btn-secondary" onclick="salvarEGerarPdf()">Salvar e gerar PDF</button>
                <a href="/gestor/financeiro/pedidos" class="btn btn-reset">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
const TIPO = document.getElementById('f_tipo').value;
const ITENS_INICIAIS = <?= json_encode($itens, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const PARCELAS_INICIAIS = <?= json_encode($parcelas, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const CLIENTES = <?= json_encode($clientesCadastro, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

// Colunas por tipo: PI = Mídia/Praça/Qtd/Valor unit./Valor total · PP = Mat-Serviço/Descrição/Valor unit./Valor total
const MIDIAS_PI = ['Outdoor Papel', 'Outdoor Lonado', 'Painel 9x3', 'Painel 9x6', 'Painel 9x4', 'Painel 18x3', 'Painel 3x9', 'Frontlight', 'Projeto Especial', 'Outros'];

const COLUNAS = TIPO === 'PI'
    ? [{k:'campo1', label:'Mídia', options: MIDIAS_PI}, {k:'campo2', label:'Praça'}, {k:'quantidade', label:'Qtd'}, {k:'valor_unitario', label:'Valor Unitário'}, {k:'valor_total', label:'Valor Total'}]
    : [{k:'campo1', label:'Mat/Serviço'}, {k:'campo2', label:'Descrição'}, {k:'valor_unitario', label:'Valor Unitário'}, {k:'valor_total', label:'Valor Total'}];

function montarCabecalho() {
    const tr = document.getElementById('cabecalhoItens');
    tr.innerHTML = COLUNAS.map(c => `<th>${c.label}</th>`).join('') + '<th></th>';

    const rodape = document.getElementById('rodapeItens');
    rodape.innerHTML =
        `<td colspan="${COLUNAS.length - 1}" style="text-align:right"><span id="labelValorMensal">Total:</span></td>` +
        `<td><span id="valorTotalExibido">R$ 0,00</span></td>` +
        `<td></td>`;
}

function linhaHtml(item) {
    item = item || {};
    const campos = COLUNAS.map(c => {
        const val = item[c.k] ?? '';
        if (c.options) {
            const listaOpcoes = (val && !c.options.includes(val)) ? [...c.options, val] : c.options;
            const opts = ['<option value=""></option>'].concat(
                listaOpcoes.map(o => `<option value="${o}" ${o === val ? 'selected' : ''}>${o}</option>`)
            ).join('');
            return `<td><select data-campo="${c.k}" onchange="itemAlterado(this)">${opts}</select></td>`;
        }
        const tipoInput = (c.k === 'quantidade' || c.k === 'valor_unitario' || c.k === 'valor_total') ? 'number' : 'text';
        const step = tipoInput === 'number' ? 'step="0.01"' : '';
        return `<td><input type="${tipoInput}" ${step} data-campo="${c.k}" value="${val}" oninput="itemAlterado(this)"></td>`;
    }).join('');
    return `<tr>${campos}<td><button type="button" class="pf-rm-item" onclick="this.closest('tr').remove(); recalcularTotal();" title="Remover">✕</button></td></tr>`;
}

function adicionarItem(item) {
    document.getElementById('corpoItens').insertAdjacentHTML('beforeend', linhaHtml(item));
}

// Ao editar qtd/valor unitário, sugere o valor total (sem travar edição manual do total)
function itemAlterado(inputEl) {
    const tr = inputEl.closest('tr');
    if (inputEl.dataset.campo === 'quantidade' || inputEl.dataset.campo === 'valor_unitario') {
        const qtdEl = tr.querySelector('[data-campo="quantidade"]');
        const vUnitEl = tr.querySelector('[data-campo="valor_unitario"]');
        const vTotalEl = tr.querySelector('[data-campo="valor_total"]');
        const qtd = qtdEl ? parseFloat(qtdEl.value || 0) : 0;
        const vUnit = vUnitEl ? parseFloat(vUnitEl.value || 0) : 0;
        if (vTotalEl && qtd > 0 && vUnit > 0) vTotalEl.value = (qtd * vUnit).toFixed(2);
    }
    recalcularTotal();
}

function recalcularTotal() {
    let total = 0;
    document.querySelectorAll('#corpoItens tr').forEach(tr => {
        const vt = tr.querySelector('[data-campo="valor_total"]');
        if (vt) total += parseFloat(vt.value || 0);
    });
    document.getElementById('valorTotalExibido').textContent =
        'R$ ' + total.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const qtdParcelas = parseInt(document.getElementById('f_qtd_parcelas').value || '1', 10);
    document.getElementById('labelValorMensal').textContent = qtdParcelas > 1 ? 'Valor mensal:' : 'Total:';
    return total;
}

function coletarItens() {
    const linhas = [];
    document.querySelectorAll('#corpoItens tr').forEach(tr => {
        const item = {};
        tr.querySelectorAll('[data-campo]').forEach(inp => { item[inp.dataset.campo] = inp.value; });
        linhas.push(item);
    });
    return linhas;
}

// ── Parcelas ────────────────────────────────────────────────────────────────
function parcelaLinhaHtml(numero, data, valor) {
    return `<tr>
        <td><input type="text" value="${numero}" readonly></td>
        <td><input type="date" data-campo="data_vencimento" value="${data || ''}"></td>
        <td><input type="number" step="0.01" data-campo="valor" value="${valor ?? ''}"></td>
        <td><button type="button" class="pf-rm-item" onclick="this.closest('tr').remove(); renumerarParcelas();" title="Remover">✕</button></td>
    </tr>`;
}

function renumerarParcelas() {
    document.querySelectorAll('#corpoParcelas tr').forEach((tr, i) => {
        tr.querySelector('td:first-child input').value = i + 1;
    });
}

function adicionarParcela(data, valor) {
    const numero = document.querySelectorAll('#corpoParcelas tr').length + 1;
    document.getElementById('corpoParcelas').insertAdjacentHTML('beforeend', parcelaLinhaHtml(numero, data, valor));
}

// Gera N parcelas mensais a partir do 1º vencimento informado, dividindo o valor bruto
function gerarParcelas() {
    const qtd = Math.max(1, parseInt(document.getElementById('f_qtd_parcelas').value || '1', 10));
    const inicio = document.getElementById('f_primeiro_vencimento').value;
    if (!inicio) { alert('Informe o 1º vencimento antes de gerar as parcelas.'); return; }
    const valorBruto = parseFloat(document.getElementById('f_valor_bruto').value || '0');
    const valorParcela = qtd > 0 && valorBruto > 0 ? (valorBruto / qtd) : null;

    document.getElementById('corpoParcelas').innerHTML = '';
    const [ano, mes, dia] = inicio.split('-').map(Number);
    for (let i = 0; i < qtd; i++) {
        const d = new Date(ano, (mes - 1) + i, dia);
        const dataStr = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        adicionarParcela(dataStr, valorParcela !== null ? valorParcela.toFixed(2) : '');
    }
}

function sugerirValorBruto() {
    const valorMensal = recalcularTotal();
    const qtd = Math.max(1, parseInt(document.getElementById('f_qtd_parcelas').value || '1', 10));
    document.getElementById('f_valor_bruto').value = (valorMensal * qtd).toFixed(2);
}

function coletarParcelas() {
    const linhas = [];
    document.querySelectorAll('#corpoParcelas tr').forEach(tr => {
        const p = {};
        tr.querySelectorAll('[data-campo]').forEach(inp => { p[inp.dataset.campo] = inp.value; });
        linhas.push(p);
    });
    return linhas;
}

document.getElementById('f_qtd_parcelas').addEventListener('input', recalcularTotal);

// ── Período: calcula automaticamente a quantidade de meses entre início e fim ──
const MESES_ABREV = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
function mesesEntreSelects(inicio, fim) {
    if (!inicio || !fim) return null;
    const [ai, mi] = inicio.split('-').map(Number);
    const [af, mf] = fim.split('-').map(Number);
    return Math.max(1, (af - ai) * 12 + (mf - mi) + 1);
}
function atualizarPeriodo(autoQtd) {
    const inicio = document.getElementById('f_periodo_inicio').value;
    const fim = document.getElementById('f_periodo_fim').value;
    const qtd = mesesEntreSelects(inicio, fim);
    const resumo = document.getElementById('periodoResumo');
    if (qtd === null) { resumo.innerHTML = '&nbsp;'; return; }
    const [, mi] = inicio.split('-').map(Number);
    const [, mf] = fim.split('-').map(Number);
    resumo.textContent = `${MESES_ABREV[mi - 1]}/${inicio.split('-')[0]} a ${MESES_ABREV[mf - 1]}/${fim.split('-')[0]} · ${qtd} ${qtd === 1 ? 'mês' : 'meses'}`;
    if (autoQtd) {
        document.getElementById('f_qtd_parcelas').value = qtd;
        recalcularTotal();
    }
}
document.getElementById('f_periodo_inicio').addEventListener('change', () => atualizarPeriodo(true));
document.getElementById('f_periodo_fim').addEventListener('change', () => atualizarPeriodo(true));

// Autopreenche dados do cliente ao digitar uma razão social já cadastrada
document.getElementById('f_cliente_razao_social').addEventListener('change', function() {
    const match = CLIENTES.find(c => c.razao_social === this.value);
    if (!match) return;
    if (!document.getElementById('f_cliente_cnpj').value)     document.getElementById('f_cliente_cnpj').value = match.cnpj || '';
    if (!document.getElementById('f_cliente_endereco').value) document.getElementById('f_cliente_endereco').value = match.endereco || '';
    if (!document.getElementById('f_cliente_email').value)    document.getElementById('f_cliente_email').value = match.email || '';
    if (!document.getElementById('f_cliente_telefone').value) document.getElementById('f_cliente_telefone').value = match.telefone || '';
});

montarCabecalho();
if (ITENS_INICIAIS.length) {
    ITENS_INICIAIS.forEach(adicionarItem);
} else {
    adicionarItem();
}
if (PARCELAS_INICIAIS.length) {
    PARCELAS_INICIAIS.forEach(p => adicionarParcela(p.data_vencimento, p.valor));
}
recalcularTotal();
atualizarPeriodo(false);

function montarPayload() {
    return {
        id: parseInt(document.getElementById('f_id').value || '0', 10),
        tipo: TIPO,
        status: document.getElementById('f_status').checked ? 'emitido' : 'rascunho',
        data_emissao: document.getElementById('f_data_emissao').value,
        periodo_inicio: document.getElementById('f_periodo_inicio').value,
        periodo_fim: document.getElementById('f_periodo_fim').value,
        nome_campanha: document.getElementById('f_nome_campanha').value,
        cliente_razao_social: document.getElementById('f_cliente_razao_social').value,
        cliente_cnpj: document.getElementById('f_cliente_cnpj').value,
        cliente_ie: document.getElementById('f_cliente_ie').value,
        cliente_endereco: document.getElementById('f_cliente_endereco').value,
        cliente_cidade: document.getElementById('f_cliente_cidade').value,
        cliente_cep: document.getElementById('f_cliente_cep').value,
        cliente_telefone: document.getElementById('f_cliente_telefone').value,
        cliente_email: document.getElementById('f_cliente_email').value,
        observacoes: document.getElementById('f_observacoes').value,
        qtd_parcelas: parseInt(document.getElementById('f_qtd_parcelas').value || '1', 10),
        valor_bruto: document.getElementById('f_valor_bruto').value,
        itens: coletarItens(),
        parcelas: coletarParcelas(),
    };
}

function salvar(callback) {
    fetch('/gestor/financeiro/pedidos/salvar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(montarPayload())
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) { callback(d); }
        else { alert('Erro ao salvar: ' + (d.erro || 'desconhecido')); }
    })
    .catch(() => alert('Erro de conexão ao salvar.'));
}

document.getElementById('formPedido').addEventListener('submit', function(e) {
    e.preventDefault();
    salvar(() => { window.location.href = '/gestor/financeiro/pedidos'; });
});

function salvarEGerarPdf() {
    salvar((d) => { window.location.href = '/gestor/financeiro/pedidos/pdf?id=' + d.id; });
}
</script>
</body>
</html>
