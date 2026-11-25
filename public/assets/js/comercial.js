/* Central Comercial — /gestor/comercial */
(function () {
    var CHAVE_ABA = 'comercial_aba';

    function empresaAtiva() {
        var ativa = document.querySelector('.com-aba.ativa');
        return ativa ? ativa.dataset.empresa : 'midia';
    }

    window.comTrocarAba = function (empresa) {
        document.querySelectorAll('.com-aba').forEach(function (b) {
            b.classList.toggle('ativa', b.dataset.empresa === empresa);
        });
        document.querySelectorAll('.com-painel').forEach(function (p) {
            p.hidden = p.dataset.empresa !== empresa;
        });
        try { sessionStorage.setItem(CHAVE_ABA, empresa); } catch (e) {}
        var url = new URL(location.href);
        url.searchParams.set('empresa', empresa);
        url.searchParams.delete('msg');
        url.searchParams.delete('erro');
        history.replaceState(null, '', url);
        comFiltrar();
    };

    window.comFiltrar = function () {
        var termo = (document.getElementById('comBusca').value || '').trim().toLowerCase();
        var painel = document.querySelector('.com-painel:not([hidden])');
        if (!painel) return;
        var visiveis = 0;
        painel.querySelectorAll('.com-filtravel').forEach(function (el) {
            var ok = !termo || (el.dataset.busca || '').indexOf(termo) !== -1;
            el.hidden = !ok;
            if (ok) visiveis++;
        });
        painel.querySelectorAll('.com-grupo').forEach(function (g) {
            g.hidden = !!termo && !g.querySelector('.com-filtravel:not([hidden])');
        });
        var aviso = painel.querySelector('.com-sem-resultado');
        if (aviso) aviso.hidden = !termo || visiveis > 0;
    };

    function toast(msg) {
        var t = document.getElementById('comToast');
        t.textContent = msg;
        t.hidden = false;
        clearTimeout(t._timer);
        t._timer = setTimeout(function () { t.hidden = true; }, 2200);
    }

    window.comCopiarLink = function (href) {
        var abs = /^https?:\/\//i.test(href) ? href : location.origin + href;
        var feito = function () { toast('🔗 Link copiado'); };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(abs).then(feito, function () { fallback(abs); feito(); });
        } else {
            fallback(abs); feito();
        }
    };
    function fallback(txt) {
        var ta = document.createElement('textarea');
        ta.value = txt; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        ta.remove();
    }

    window.comAcao = function (acao, id, empresa, titulo) {
        if (acao === 'excluir' && !confirm('Excluir "' + titulo + '"?\nO arquivo será removido do servidor. Essa ação não pode ser desfeita.')) return;
        var f = document.getElementById('comFormAcao');
        // elements.namedItem: f.action / f.id colidem com propriedades nativas do <form>
        f.elements.namedItem('action').value = acao;
        f.elements.namedItem('id').value = id;
        f.elements.namedItem('empresa').value = empresa;
        f.submit();
    };

    window.comAtualizarTipo = function () {
        var link = document.querySelector('#comForm input[name="tipo"]:checked').value === 'link';
        document.getElementById('comCampoArquivo').hidden = link;
        document.getElementById('comCampoLink').hidden = !link;
        document.getElementById('comUrl').required = link;
    };

    window.comAbrirModal = function (d) {
        var f = document.getElementById('comForm');
        f.reset();
        d = d || {};
        document.getElementById('comModalTitulo').textContent = d.id ? 'Editar item' : 'Adicionar item';
        document.getElementById('comId').value = d.id || '';
        var empresa = d.empresa || empresaAtiva();
        f.querySelector('input[name="empresa"][value="' + empresa + '"]').checked = true;
        f.querySelector('input[name="tipo"][value="' + (d.tipo || 'arquivo') + '"]').checked = true;
        document.getElementById('comCategoria').value = d.categoria || '';
        document.getElementById('comTitulo').value = d.titulo || '';
        document.getElementById('comDescricao').value = d.descricao || '';
        document.getElementById('comUrl').value = d.url || '';
        document.getElementById('comDestaque').checked = !!d.destaque;
        document.getElementById('comArquivoAtual').textContent =
            d.id && d.tipo === 'arquivo' ? '(atual: ' + (d.nome_original || 'arquivo') + ' — envie outro só para substituir)' : '';
        comAtualizarTipo();
        document.getElementById('comModal').hidden = false;
        setTimeout(function () { document.getElementById(d.id ? 'comTitulo' : 'comCategoria').focus(); }, 30);
    };

    window.comFecharModal = function () {
        document.getElementById('comModal').hidden = true;
    };

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') comFecharModal();
        if (e.key === '/' && document.activeElement.tagName !== 'INPUT') {
            e.preventDefault();
            document.getElementById('comBusca').focus();
        }
    });

    document.getElementById('comForm').addEventListener('submit', function () {
        var btn = document.getElementById('comSalvar');
        btn.disabled = true;
        btn.textContent = 'Enviando...';
    });

    // Sem ?empresa= na URL, volta para a última aba usada
    if (!new URL(location.href).searchParams.get('empresa')) {
        var salva = null;
        try { salva = sessionStorage.getItem(CHAVE_ABA); } catch (e) {}
        if (salva === 'led' || salva === 'midia') comTrocarAba(salva);
    }
})();
