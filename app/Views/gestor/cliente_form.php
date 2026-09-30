<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/?erro=nao_logado");
    exit;
}

$paginaAtual = 'clientes';

try {
    require_once __DIR__ . '/../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("Erro na conexão com o banco de dados.");
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editando = $id > 0;

// Pra onde voltar depois de salvar: de onde o usuário veio (ex: Relatórios > Clientes),
// restrito a páginas internas do próprio gestor por segurança.
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$refPath = parse_url($referer, PHP_URL_PATH) ?: '';
$voltarUrl = str_starts_with($refPath, '/gestor/') ? $referer : '/gestor/clientes';

$dados = ['id' => 0, 'razao_social' => '', 'nome_fantasia' => '', 'logo' => '', 'cnpj' => '', 'endereco' => '', 'email' => '', 'telefone' => '', 'contato' => '', 'observacoes' => ''];
if ($editando) {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        header("Location: /gestor/clientes");
        exit;
    }
    $dados = $row;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $editando ? 'Editar' : 'Novo' ?> Cliente · Impakto</title>
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/gestor.css?v=2">
    <style>
        .cl-logo-preview { width:80px; height:80px; border-radius:10px; object-fit:contain; background:#f6f7fb; border:1px solid var(--color-border); margin-bottom:0.6rem; display:block; }
        .cl-cnpj-msg { font-size:0.8rem; margin-top:0.4rem; line-height:1.4; }
        .cl-cnpj-msg.ok   { color:#15803d; }
        .cl-cnpj-msg.aviso { color:#b45309; }
        .cl-cnpj-msg.erro { color:#b91c1c; }
        .cl-cnpj-msg a { color:inherit; font-weight:600; }
        .cl-preenchido { background:#f0fdf4 !important; transition:background 1.5s; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../partials/env_banner.php'; ?>


<?php require __DIR__ . '/../layouts/_nav.php'; ?>

<div class="container" style="padding-bottom:2rem; max-width:640px;">

    <div class="welcome" style="margin-bottom:1.5rem;">
        <h2><?= $editando ? '✏️ Editar Cliente' : '➕ Novo Cliente' ?></h2>
    </div>

    <?php if (!empty($_GET['erro'])): ?>
        <div class="alerta alerta-err">❌ <?= htmlspecialchars($_GET['erro']) ?></div>
    <?php endif; ?>

    <form method="POST" action="/gestor/clientes/salvar" enctype="multipart/form-data" class="table-container" style="padding:1.5rem;">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="id" value="<?= (int)$dados['id'] ?>">
        <input type="hidden" name="voltar" value="<?= htmlspecialchars($voltarUrl) ?>">

        <div class="form-group" style="margin-bottom:1rem;">
            <label>Logomarca</label><br>
            <?php if (!empty($dados['logo'])): ?>
            <img src="/<?= htmlspecialchars($dados['logo']) ?>" class="cl-logo-preview" alt="Logo atual">
            <?php endif; ?>
            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml">
            <div style="font-size:0.75rem; color:var(--color-text-muted); margin-top:0.3rem;">PNG, JPG, WEBP ou SVG — até 2 MB.</div>
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label>CNPJ</label><br>
            <div style="display:flex; gap:0.5rem;">
                <input type="text" name="cnpj" id="cl-cnpj" maxlength="20"
                       value="<?= htmlspecialchars($dados['cnpj'] ?? '') ?>"
                       placeholder="00.000.000/0000-00"
                       style="flex:1; min-width:0; padding:0.6rem; border:1px solid var(--color-border); border-radius:8px;">
                <button type="button" id="cl-cnpj-buscar" class="btn-backup" style="white-space:nowrap;">🔍 Buscar dados</button>
            </div>
            <div id="cl-cnpj-msg" class="cl-cnpj-msg" hidden></div>
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label>Razão Social *</label><br>
            <input type="text" name="razao_social" required maxlength="200"
                   value="<?= htmlspecialchars($dados['razao_social']) ?>"
                   style="width:100%; padding:0.6rem; border:1px solid var(--color-border); border-radius:8px;">
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label>Nome Fantasia</label><br>
            <input type="text" name="nome_fantasia" maxlength="200"
                   value="<?= htmlspecialchars($dados['nome_fantasia'] ?? '') ?>"
                   style="width:100%; padding:0.6rem; border:1px solid var(--color-border); border-radius:8px;">
        </div>


        <div class="form-group" style="margin-bottom:1rem;">
            <label>Endereço</label><br>
            <input type="text" name="endereco" maxlength="255"
                   value="<?= htmlspecialchars($dados['endereco'] ?? '') ?>"
                   style="width:100%; padding:0.6rem; border:1px solid var(--color-border); border-radius:8px;">
        </div>

        <div style="display:flex; gap:1rem; flex-wrap:wrap;">
            <div class="form-group" style="margin-bottom:1rem; flex:1; min-width:220px;">
                <label>E-mail</label><br>
                <input type="email" name="email" maxlength="150"
                       value="<?= htmlspecialchars($dados['email'] ?? '') ?>"
                       style="width:100%; padding:0.6rem; border:1px solid var(--color-border); border-radius:8px;">
            </div>

            <div class="form-group" style="margin-bottom:1rem; flex:1; min-width:220px;">
                <label>Telefone</label><br>
                <input type="text" name="telefone" maxlength="30"
                       value="<?= htmlspecialchars($dados['telefone'] ?? '') ?>"
                       style="width:100%; padding:0.6rem; border:1px solid var(--color-border); border-radius:8px;">
            </div>
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label>Contato (nome do responsável)</label><br>
            <input type="text" name="contato" maxlength="150"
                   value="<?= htmlspecialchars($dados['contato'] ?? '') ?>"
                   style="width:100%; padding:0.6rem; border:1px solid var(--color-border); border-radius:8px;">
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label>Observações</label><br>
            <textarea name="observacoes" rows="3"
                      style="width:100%; padding:0.6rem; border:1px solid var(--color-border); border-radius:8px;"><?= htmlspecialchars($dados['observacoes'] ?? '') ?></textarea>
        </div>

        <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
            <button type="submit" class="btn-backup btn-baixar">💾 Salvar</button>
            <a href="<?= htmlspecialchars($voltarUrl) ?>" class="btn-backup" style="background:#f3f4f6; color:var(--color-text-dark);">Cancelar</a>
        </div>
    </form>

</div>

<script>
// Busca os dados do CNPJ na Receita (via BrasilAPI) e preenche só os campos vazios,
// pra não sobrescrever o que já foi digitado/editado.
(function () {
    const form   = document.querySelector('form[action="/gestor/clientes/salvar"]');
    const campo  = document.getElementById('cl-cnpj');
    const botao  = document.getElementById('cl-cnpj-buscar');
    const msg    = document.getElementById('cl-cnpj-msg');
    const idAtual = form.querySelector('input[name="id"]').value;
    const rotulos = { razao_social: 'Razão Social', nome_fantasia: 'Nome Fantasia', endereco: 'Endereço', email: 'E-mail', telefone: 'Telefone' };
    let ultimaConsulta = '';

    function limpar(v) { return v.toUpperCase().replace(/[^A-Z0-9]/g, ''); }

    function mostrar(tipo, html) {
        msg.className = 'cl-cnpj-msg ' + tipo;
        msg.innerHTML = html;
        msg.hidden = false;
    }

    function esc(s) {
        return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    async function buscar() {
        const cnpj = limpar(campo.value);
        if (cnpj.length !== 14) {
            mostrar('erro', 'Digite os 14 caracteres do CNPJ.');
            return;
        }
        ultimaConsulta = cnpj;
        botao.disabled = true;
        botao.textContent = '⏳ Buscando...';
        try {
            const resp = await fetch('/gestor/clientes/consultar-cnpj?cnpj=' + encodeURIComponent(cnpj) + '&id=' + encodeURIComponent(idAtual));
            const json = await resp.json();
            const avisoDup = json.duplicado
                ? '<br>⚠️ Este CNPJ já está cadastrado: <a href="/gestor/clientes/editar?id=' + json.duplicado.id + '">' + esc(json.duplicado.razao_social) + '</a>.'
                : '';

            if (!json.ok) {
                mostrar('erro', esc(json.erro) + avisoDup);
                return;
            }

            campo.value = json.dados.cnpj;
            const preenchidos = [], mantidos = [];
            for (const nome in rotulos) {
                const valor = json.dados[nome];
                const input = form.querySelector('[name="' + nome + '"]');
                if (!valor || !input) continue;
                if (input.value.trim() === '') {
                    input.value = valor;
                    input.classList.add('cl-preenchido');
                    setTimeout(() => input.classList.remove('cl-preenchido'), 1500);
                    preenchidos.push(rotulos[nome]);
                } else if (input.value.trim() !== valor) {
                    mantidos.push(rotulos[nome]);
                }
            }

            let texto = preenchidos.length
                ? '✅ Preenchido: ' + preenchidos.join(', ') + '.'
                : '✅ CNPJ encontrado. Nenhum campo vazio para preencher.';
            if (mantidos.length) texto += '<br>Mantidos como estavam (diferentes da Receita): ' + mantidos.join(', ') + '.';
            if (json.situacao && json.situacao !== 'ATIVA') texto += '<br>⚠️ Situação cadastral na Receita: <strong>' + esc(json.situacao) + '</strong>.';
            texto += avisoDup;

            const temAviso = json.duplicado || (json.situacao && json.situacao !== 'ATIVA');
            mostrar(temAviso ? 'aviso' : 'ok', texto);
        } catch (e) {
            mostrar('erro', 'Não foi possível consultar o CNPJ agora. Tente novamente em instantes.');
        } finally {
            botao.disabled = false;
            botao.textContent = '🔍 Buscar dados';
        }
    }

    botao.addEventListener('click', buscar);
    campo.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); buscar(); }
    });
    // Busca sozinho quando o CNPJ fica completo num cadastro novo.
    campo.addEventListener('input', () => {
        const cnpj = limpar(campo.value);
        if (idAtual === '0' && cnpj.length === 14 && cnpj !== ultimaConsulta) buscar();
    });
})();
</script>

</body>
</html>
