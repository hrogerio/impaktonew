<?php
// Helpers compartilhados entre a tela de Campanhas e o endpoint de busca AJAX.

$CORES = [
    'Ocupado'   => '#dc3545', 'Reservado' => '#fd7e14',
    'Permuta'   => '#51086e', 'Bisemana'  => '#0284c7',
    'Vencido'   => '#6c757d',
];
function corSit($s, $cores) { return $cores[$s] ?? '#888'; }
function fmtD($d) {
    if (!$d || $d === '0000-00-00') return null;
    try { return (new DateTime($d))->format('d/m/Y'); } catch(Exception $e) { return null; }
}
function diasR($fim) {
    if (!$fim || $fim === '0000-00-00') return null;
    try {
        $hoje = new DateTime(); $fimDt = new DateTime($fim);
    } catch (Exception $e) {
        return null;
    }
    $diff = (int)$hoje->diff($fimDt)->days;
    return $fimDt >= $hoje ? $diff : -$diff;
}

/**
 * Nome do cliente pra exibição no card: usa o cadastro (clientes.razao_social)
 * quando disponível; se o "Nome" da campanha (ex: "Alto da Passira") já
 * aparece entre parênteses no final do razão social, remove essa parte —
 * já que essa informação é mostrada separadamente no card.
 */
function clienteParaExibicao(?string $cadastro, string $textoLivre, ?string $nomeProjeto): string {
    $base = $cadastro ?: $textoLivre;
    if ($nomeProjeto) {
        $limpo = preg_replace('/\s*\(\s*' . preg_quote($nomeProjeto, '/') . '\s*\)\s*$/iu', '', $base);
        $limpo = trim($limpo);
        if ($limpo !== '') return $limpo;
    }
    return $base;
}

/**
 * Busca campanhas agrupadas (cliente + campanha + situacao + periodo) filtrando
 * por situacao já no SQL, pra nunca puxar a tabela inteira sem necessidade.
 * $situacaoFiltro: '' (ativas), 'Encerradas', 'Vencidas'
 */
function campanhasBuscarGrupos(PDO $pdo, string $situacaoFiltro): array {
    $hoje = date('Y-m-d');

    $sql = "
        SELECT
            c.id, c.ponto_id, c.cliente, c.cliente_id, c.agencia, c.agencia_id, c.campanha, c.nome AS nome_projeto,
            c.situacao, c.inicio, c.fim, c.ativo, c.encerrado_em, c.criado_em,
            p.numero, p.logradouro, p.cidade, p.regiao,
            cl.razao_social AS cliente_cadastro
        FROM campanhas c
        JOIN pontos p ON p.id = c.ponto_id AND (p.ativo = 1 OR p.ativo IS NULL)
        LEFT JOIN clientes cl ON cl.id = c.cliente_id
        WHERE c.situacao != 'Reservado'
    ";
    if ($situacaoFiltro === 'Encerradas') {
        $sql .= " AND c.ativo = 0";
    } elseif ($situacaoFiltro === 'Vencidas') {
        // CAST pra CHAR evita que o MySQL tente interpretar o literal '0000-00-00'
        // como DATE em modo estrito (erro 1525 "Incorrect DATE value" em producao).
        $sql .= " AND c.ativo = 1 AND c.fim IS NOT NULL AND CAST(c.fim AS CHAR) <> '0000-00-00' AND c.fim < :hoje";
    } else {
        $sql .= " AND c.ativo = 1";
    }
    $sql .= "
        ORDER BY
            c.ativo DESC,
            COALESCE(NULLIF(TRIM(c.cliente),''), 'ZZZZ') ASC,
            c.criado_em DESC
    ";
    $stmt = $pdo->prepare($sql);
    if ($situacaoFiltro === 'Vencidas') $stmt->bindValue(':hoje', $hoje);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Documentos financeiros (P.I./P.P.) — vinculados ao contrato (cliente+agência+campanha+período)
    $docKey = fn($cliente, $agencia, $campanha, $inicio, $fim) => md5(
        trim($cliente) . '|' . trim($agencia) . '|' . trim($campanha) . '|' . ($inicio ?? '') . '|' . ($fim ?? '')
    );
    $documentosPorGrupo = [];
    $docsRows = $pdo->query("SELECT * FROM campanha_documentos ORDER BY criado_em DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($docsRows as $d) {
        $dk = $docKey($d['cliente'], $d['agencia'], $d['campanha'], $d['inicio'], $d['fim']);
        $documentosPorGrupo[$dk][] = $d;
    }

    // Agrupar: Cliente → CampanhaKey → dados + painéis
    $grupos = [];
    foreach ($rows as $r) {
        $cli  = trim($r['cliente']  ?? '') ?: '— Sem cliente —';
        $camp = trim($r['campanha'] ?? '') ?: '—';
        $nomeProjeto = trim($r['nome_projeto'] ?? '');
        $campKey = md5($cli . '|' . $camp . '|' . $nomeProjeto . '|' . $r['situacao'] . '|' . ($r['inicio'] ?? '') . '|' . ($r['fim'] ?? '') . '|' . $r['ativo']);

        if (!isset($grupos[$campKey])) {
            // Título de exibição da campanha: usa o Nome (do projeto) quando existe,
            // senão cai pro Motivo — mesma lógica usada no destaque do card.
            $titulo = $nomeProjeto !== '' ? $nomeProjeto : ($camp !== '—' ? $camp : 'Sem nome');

            $grupos[$campKey] = [
                'cliente'          => $cli,
                'cliente_id'       => $r['cliente_id'] ? (int)$r['cliente_id'] : null,
                'cliente_cadastro' => $r['cliente_cadastro'] ? trim($r['cliente_cadastro']) : null,
                'agencia'          => trim($r['agencia'] ?? ''),
                'agencia_id'       => $r['agencia_id'] ? (int)$r['agencia_id'] : null,
                'nome'             => $camp,
                'nome_projeto'     => $nomeProjeto,
                'titulo'           => $titulo,
                'situacao'         => $r['situacao'],
                'ativo'            => (int)$r['ativo'],
                'inicio'           => $r['inicio'],
                'fim'              => $r['fim'],
                'rows'             => [],
                'documentos'       => $documentosPorGrupo[$docKey($cli, $r['agencia'] ?? '', $camp, $r['inicio'], $r['fim'])] ?? [],
            ];
        }
        $grupos[$campKey]['rows'][] = $r;
    }

    usort($grupos, function($a, $b) {
        if ($a['ativo'] !== $b['ativo']) return $b['ativo'] - $a['ativo'];
        return strcasecmp($a['titulo'], $b['titulo']);
    });

    return $grupos;
}

/** String de busca (cliente, agência, campanha, situação, pontos) usada pro filtro textual */
function campanhaBuscaStr(array $g): string {
    return strtolower(
        $g['cliente'] . ' ' . ($g['cliente_cadastro'] ?? '') . ' ' . $g['agencia'] . ' '
        . $g['nome'] . ' ' . $g['nome_projeto'] . ' ' . $g['situacao']
        . ' ' . implode(' ', array_column($g['rows'], 'numero'))
        . ' ' . implode(' ', array_column($g['rows'], 'logradouro'))
        . ' ' . implode(' ', array_column($g['rows'], 'cidade'))
    );
}

/** Cor do status da campanha: Ativa = verde, Vencida = laranja, Encerrada = vermelho */
function corStatusCampanha(bool $ativo, bool $vencida): string {
    if (!$ativo)  return '#dc2626';
    if ($vencida) return '#ea580c';
    return '#16a34a';
}

/** Renderiza o HTML de um card de campanha */
function renderCampanhaCard(array $g, array $CORES, string $hoje): string {
    $ini     = fmtD($g['inicio']);
    $fim     = fmtD($g['fim']);
    $dias    = $g['fim'] ? diasR($g['fim']) : null;
    $nPain   = count($g['rows']);
    $buscaStr = campanhaBuscaStr($g);

    $campIds  = array_column($g['rows'], 'id');
    $pontoIds = array_column($g['rows'], 'ponto_id');
    $isVencida = $g['ativo'] && $g['fim'] && substr($g['fim'], 0, 10) < $hoje;
    $statusCor = corStatusCampanha((bool)$g['ativo'], (bool)$isVencida);
    $dataCard = htmlspecialchars(json_encode([
        'campIds'      => $campIds,
        'pontoIds'     => $pontoIds,
        'cliente'      => $g['cliente'],
        'agencia'      => $g['agencia'],
        'nome'         => $g['nome'],
        'nome_projeto' => $g['nome_projeto'],
        'situacao'     => $g['situacao'],
        'inicio'     => $g['inicio'] ? substr($g['inicio'], 0, 10) : '',
        'fim'        => $g['fim']    ? substr($g['fim'],    0, 10) : '',
        'isVencida'  => (bool)$isVencida,
        'documentos' => array_map(fn($d) => [
            'id'            => (int)$d['id'],
            'tipo'          => $d['tipo'],
            'caminho'       => $d['caminho'],
            'nome_original' => $d['nome_original'],
            'criado_em'     => $d['criado_em'],
        ], $g['documentos']),
    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

    ob_start();
    ?>
    <div class="cp-card <?= !$g['ativo'] ? 'encerrada' : ($isVencida ? 'vencida' : '') ?>"
         data-key="<?= htmlspecialchars(implode('_', $campIds)) ?>"
         data-busca="<?= htmlspecialchars($buscaStr) ?>"
         data-situacao="<?= htmlspecialchars($g['situacao']) ?>"
         data-status="<?= $g['ativo'] ?>"
         data-vencida="<?= $isVencida ? 1 : 0 ?>"
         data-cliente="<?= htmlspecialchars(strtolower($g['cliente'])) ?>"
         data-campanha="<?= $dataCard ?>">

        <div class="cp-card-faixa" style="background:<?= $statusCor ?>"></div>

        <div class="cp-card-head">
            <div class="cp-card-top">
                <span class="sit-dot" style="background:<?= $statusCor ?>"
                      title="<?= !$g['ativo'] ? 'Encerrada' : ($isVencida ? 'Vencida' : htmlspecialchars($g['situacao'])) ?>"></span>
                <?php
                    $mostraMotivo = $g['nome_projeto'] !== '' && $g['nome'] !== '—';
                    $clienteExibicao = clienteParaExibicao($g['cliente_cadastro'], $g['cliente'], $g['nome_projeto'] ?: null);
                ?>
                <span class="cp-card-nome"><?= htmlspecialchars($g['titulo']) ?></span>
                <?php if ($mostraMotivo): ?>
                <span class="cp-card-motivo">(<?= htmlspecialchars($g['nome']) ?>)</span>
                <?php endif; ?>
            </div>
            <div class="cp-card-cliente">
                <?php if ($g['cliente_id']): ?>
                <a href="/gestor/clientes/ficha?id=<?= (int)$g['cliente_id'] ?>" class="cp-card-cliente-link" title="Ver ficha do cliente"><?= htmlspecialchars($clienteExibicao) ?></a>
                <?php else: ?>
                <?= htmlspecialchars($clienteExibicao) ?>
                <?php endif; ?>
            </div>
            <?php if ($g['agencia'] && strtolower($g['agencia']) === 'direto'): ?>
            <div class="cp-card-agencia"><span class="cp-card-direto">Direto</span></div>
            <?php elseif ($g['agencia']): ?>
            <div class="cp-card-agencia">Agência:
                <?php if ($g['agencia_id']): ?>
                <a href="/gestor/agencias/ficha?id=<?= (int)$g['agencia_id'] ?>" class="cp-card-cliente-link" title="Ver ficha da agência"><?= htmlspecialchars($g['agencia']) ?></a>
                <?php else: ?>
                <?= htmlspecialchars($g['agencia']) ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="cp-card-meta">
                <?php if ($ini || $fim): ?>
                <span class="cp-card-periodo">
                    Período: <?= $ini ?? '?' ?> → <?= $fim ?? '?' ?>
                </span>
                <?php endif; ?>
                <?php if ($g['ativo'] && $dias !== null && $dias >= 0 && $dias <= 30):
                    $cls = $dias <= 7 ? 'prazo-urg' : 'prazo-ale'; ?>
                <span class="<?= $cls ?>"><?= $dias ?>d</span>
                <?php endif; ?>
                <?php if (!$g['ativo']): ?>
                <span class="status-encerrada">Encerrada</span>
                <?php elseif ($isVencida): ?>
                <span class="status-vencida">Vencida</span>
                <?php else: ?>
                <span class="status-ativa">Ativa</span>
                <?php endif; ?>
            </div>
        </div>

        <button type="button" class="cp-acordeon-toggle" onclick="toggleAcordeon(this)">
            <span class="cp-acordeon-toggle-label">📍 <?= $nPain ?> ponto<?= $nPain > 1 ? 's' : '' ?></span>
            <span class="cp-acordeon-seta">▾</span>
        </button>
        <div class="cp-card-paineis fechado">
            <?php foreach ($g['rows'] as $r): ?>
            <div class="cp-painel-row">
                <span class="cp-painel-num"><?= str_pad($r['numero'], 3, '0', STR_PAD_LEFT) ?></span>
                <div class="cp-painel-end">
                    <div class="cp-painel-log" title="<?= htmlspecialchars($r['logradouro']) ?>"><?= htmlspecialchars($r['logradouro']) ?></div>
                    <div class="cp-painel-cid"><?= htmlspecialchars(implode(' · ', array_filter([$r['cidade'] ?? '', $r['regiao'] ?? '']))) ?></div>
                </div>
                <a href="/gestor/pontos/detalhes?id=<?= $r['ponto_id'] ?>" class="cp-painel-link" title="Ver ponto">→</a>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="cp-card-footer">
            <div class="cp-acoes">
            <?php
                $ckQ = http_build_query([
                    'cliente'      => $g['cliente'],
                    'agencia'      => $g['agencia'],
                    'campanha'     => $g['nome'],
                    'nome_projeto' => $g['nome_projeto'],
                    'situacao'     => $g['situacao'],
                    'inicio'       => $g['inicio'] ? substr($g['inicio'], 0, 10) : '',
                    'fim'          => $g['fim']    ? substr($g['fim'],    0, 10) : '',
                ]);
                foreach ($pontoIds as $pid) { $ckQ .= '&pontoIds[]=' . (int)$pid; }
                $checkUrl   = '/gestor/campanhas/checking?' . $ckQ;
                $espelhoUrl = '/gestor/campanhas/espelho/pdf?' . $ckQ;
            ?>
                <a href="<?= htmlspecialchars($checkUrl) ?>"
                   class="cp-btn cp-btn-checking"
                   title="Checking fotográfico desta campanha">📸 Checking</a>
                <a href="<?= htmlspecialchars($espelhoUrl) ?>"
                   target="_blank"
                   class="cp-btn cp-btn-espelho"
                   title="Gerar PDF Espelho de Colagem">🗂️ Espelho</a>
                <button class="cp-btn cp-btn-docs"
                        onclick="abrirDocumentos(this.closest('.cp-card'))"
                        title="Documentos financeiros (P.I. / P.P.)">📎 Docs (<?= count($g['documentos']) ?>)</button>
                <button class="cp-btn cp-btn-editar"
                        onclick="abrirEdicao(this.closest('.cp-card'))"
                        title="Editar datas e dados da campanha">✏️ Editar</button>
            <?php if ($g['ativo']): ?>
                <?php if ($isVencida): ?>
                <button class="cp-btn cp-btn-renovar"
                        onclick="abrirRenovacao(this.closest('.cp-card'))"
                        title="Renovar contrato com novas datas">🔄 Renovar</button>
                <?php endif; ?>
                <button class="cp-btn cp-btn-encerrar"
                        onclick="encerrarGrupo(this.closest('.cp-card'), this)"
                        title="Encerrar campanha e liberar pontos">🔒 Encerrar</button>
            <?php else: ?>
                <button class="cp-btn cp-btn-renovar"
                        onclick="abrirRenovacao(this.closest('.cp-card'))"
                        title="Criar nova campanha com estes dados">🔄 Renovar</button>
            <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
