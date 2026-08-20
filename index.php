<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);

    require_once __DIR__ . '/config/database.php'; // só carrega .env + define getDatabase(), não conecta

    $httpsDetectado = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $sessionSecure = $httpsDetectado || getenv('SESSION_SECURE') === 'true';

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $sessionSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

define('SESSAO_TIMEOUT_MINUTOS', getenv('SESSION_LIFETIME') ? (int)getenv('SESSION_LIFETIME') : 120);

// Detecta se o projeto está num subdiretório (ex: /impaktonew no Laragon)
// Em produção (domínio próprio) basePath fica vazio
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
// Segurança: se o servidor devolver um path de filesystem em vez de URL, limpa
if ($basePath === '/' || $basePath === '\\' ||
    strpos($basePath, '/var/')  !== false ||
    strpos($basePath, '/home/') !== false ||
    strpos($basePath, '/srv/')  !== false) {
    $basePath = '';
}
define('BASE', $basePath); // disponível em todos os includes

// Remove o prefixo do subdiretório da URI para o roteamento
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($basePath !== '' && strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}
$uri = trim($uri, '/');

// "Manter conectado": tenta autenticar via cookie persistente antes de rotear
if (!isset($_SESSION['usuario']) && !empty($_COOKIE['remember_token'])) {
    require_once __DIR__ . '/config/remember.php';
    try {
        remember_tentar_login(getDatabase());
    } catch (Exception $e) {
        error_log('REMEMBER_LOGIN ERRO (index): ' . $e->getMessage());
    }
}

function auth_required() {
    if (!isset($_SESSION['usuario'])) {
        header("Location: " . BASE . "/?erro=nao_logado");
        exit;
    }

    $inatividade = time() - ($_SESSION['last_activity'] ?? time());
    if ($inatividade > SESSAO_TIMEOUT_MINUTOS * 60) {
        $usuarioAnterior = $_SESSION['usuario'];
        $_SESSION = [];
        session_destroy();
        error_log("Sessão expirada por inatividade: {$usuarioAnterior}");
        header("Location: " . BASE . "/?erro=sessao_expirada");
        exit;
    }
    $_SESSION['last_activity'] = time();

    // Regenera o ID da sessão periodicamente para reduzir a janela de sequestro de sessão
    if (($_SESSION['regenerated_at'] ?? 0) < time() - 900) {
        session_regenerate_id(true);
        $_SESSION['regenerated_at'] = time();
    }
}

function require_role($role) {
    auth_required();
    if (($_SESSION['usuario_role'] ?? 'admin') !== $role) {
        http_response_code(403);
        die("Acesso restrito.");
    }
}

// ── URL amigável: /ponto/009 → detalhes público ──────────────
if (preg_match('#^ponto/([a-zA-Z0-9\-]+)$#', $uri, $m)) {
    $slug = $m[1];
    require_once __DIR__ . '/config/database.php';
    $pdo = getDatabase();
    $pontoId = null;
    try {
        $s = $pdo->prepare("SELECT id FROM pontos WHERE numero = ? LIMIT 1");
        $s->execute([$slug]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if ($row) $pontoId = $row['id'];
    } catch (PDOException $e) {}
    if (!$pontoId && is_numeric($slug)) {
        try {
            $s = $pdo->prepare("SELECT id FROM pontos WHERE id = ? LIMIT 1");
            $s->execute([(int)$slug]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if ($row) $pontoId = $row['id'];
        } catch (PDOException $e) {}
    }
    if ($pontoId) {
        header("Location: " . BASE . "/gestor/pontos/detalhes?id={$pontoId}&view=publico");
        exit;
    }
    http_response_code(404);
    die("Ponto não encontrado.");
}

switch ($uri) {

    // ── LOGIN ────────────────────────────────────────────────
    case '':
    case 'login':
        if (isset($_SESSION['usuario'])) {
            header("Location: " . BASE . "/gestor");
            exit;
        }
        require __DIR__ . '/public/index.php';
        break;

    // ── LOGOUT ──────────────────────────────────────────────
    case 'logout':
        if (isset($_SESSION['usuario'])) {
            error_log("Logout: {$_SESSION['usuario']}");
        }
        if (!empty($_COOKIE['remember_token'])) {
            require_once __DIR__ . '/config/remember.php';
            try {
                remember_esquecer(getDatabase());
            } catch (Exception $e) {
                error_log('REMEMBER_LOGOUT ERRO: ' . $e->getMessage());
            }
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        header("Location: " . BASE . "/");
        exit;

    // ── PONTO PÚBLICO ────────────────────────────────────────
    case 'ponto':
        require __DIR__ . '/ponto.php';
        break;

    // ── DASHBOARD ────────────────────────────────────────────
    case 'gestor':
        auth_required();
        require __DIR__ . '/gestor/index.php';
        break;

    // ── LISTA DE PONTOS + PRÉ-SELEÇÃO (unificado) ───────────
    case 'gestor/pontos':
        auth_required();
        require __DIR__ . '/app/Views/gestor/pontos.php';
        break;

    // ── DETALHES DO PONTO (admin + público) ──────────────────
    case 'gestor/pontos/detalhes':
        require __DIR__ . '/app/Views/gestor/ponto_detalhes.php';
        break;

    // ── NOVO PONTO ────────────────────────────────────────────
    case 'gestor/pontos/novo':
        auth_required();
        require __DIR__ . '/app/Views/gestor/form_ponto.php';
        break;

    // ── EDITAR PONTO ──────────────────────────────────────────
    case 'gestor/pontos/editar':
        auth_required();
        require __DIR__ . '/app/Views/gestor/form_ponto.php';
        break;

    // ── SALVAR PONTO (POST: create + update) ─────────────────
    case 'gestor/pontos/salvar':
        auth_required();
        require __DIR__ . '/app/Views/gestor/salvar_ponto.php';
        break;

    // ── DESATIVAR PONTO (POST: soft delete) ──────────────────
    case 'gestor/pontos/excluir':   // compatibilidade com links antigos
    case 'gestor/pontos/desativar':
        auth_required();
        require __DIR__ . '/app/Views/gestor/excluir_ponto.php';
        break;

    // ── REATIVAR PONTO (POST) ─────────────────────────────────
    case 'gestor/pontos/reativar':
        auth_required();
        require __DIR__ . '/app/Views/gestor/reativar_ponto.php';
        break;

    // ── API: FOTOS (AJAX: upload / principal / excluir) ───────
    case 'gestor/pontos/fotos':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/fotos.php';
        break;

    // ── PRÉ-SELEÇÃO ──────────────────────────────────────────
    case 'gestor/pre-selecao':
        auth_required();
        require __DIR__ . '/app/Views/gestor/pre_selecao.php';
        break;

    // ── API: SALVAR PRÉ-SELEÇÃO (POST) ───────────────────────
    case 'gestor/pre-selecao/salvar':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/salvar_pre_selecao.php';
        break;

    // ── API: DADOS DE PRÉ-SELEÇÃO SALVA (AJAX reabrir) ───────
    case 'gestor/pre-selecao/dados':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/pre_selecao_dados.php';
        break;

    // ── PDF DE PRÉ-SELEÇÃO COM FOTOS (download) ───────────────
    case 'gestor/pre-selecao/pdf':
        auth_required();
        require __DIR__ . '/app/Views/gestor/campanhas/pre_selecao_pdf.php';
        break;

    // ── RESERVAS (histórico de pré-seleções) ─────────────────
    case 'gestor/reservas':
        auth_required();
        require __DIR__ . '/app/Views/gestor/reservas.php';
        break;

    // ── VER RESERVA ───────────────────────────────────────────
    case 'gestor/reservas/ver':
        auth_required();
        require __DIR__ . '/app/Views/gestor/reserva_ver.php';
        break;

    // ── API: RESERVAS RECENTES ────────────────────────────────
    case 'gestor/reservas/recentes':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/reservas_recentes.php';
        break;

    // ── API: EXCLUIR RESERVA (POST) ───────────────────────────
    case 'gestor/reservas/excluir':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/excluir_reserva.php';
        break;

    // ── API: ATUALIZAR STATUS DA RESERVA (POST) ───────────────
    case 'gestor/reservas/status':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/atualizar_status_reserva.php';
        break;

    // ── PDF DA RESERVA ────────────────────────────────────────
    case 'gestor/reservas/pdf':
        auth_required();
        require __DIR__ . '/app/Views/gestor/reserva_pdf.php';
        break;

    // ── API: DADOS DOS PONTOS (AJAX pré-seleção) ─────────────
    case 'gestor/pontos/dados':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/pontos_dados.php';
        break;

    // ── PDF DE APRESENTAÇÃO — PAINÉIS EXCLUSIVOS (download) ──
    case 'gestor/pontos/exclusivos/pdf':
        auth_required();
        require __DIR__ . '/app/Views/gestor/pontos_exclusivos_pdf.php';
        break;

    // ── API: LIBERAR/BLOQUEAR PONTO EXCLUSIVO P/ COMERCIALIZAÇÃO (POST) ──
    case 'gestor/pontos/toggle-liberado':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/toggle_liberado_exclusivo.php';
        break;

    // ── RELATÓRIOS ──────────────────────────────────────────
    case 'gestor/relatorios':
        auth_required();
        require __DIR__ . '/app/Views/gestor/relatorios.php';
        break;

    // ── RELATÓRIO MENSAL PDF (download) ──────────────────────
    case 'gestor/relatorios/pdf':
        auth_required();
        require __DIR__ . '/app/Views/gestor/relatorio_mensal_pdf.php';
        break;

    // ── RELATÓRIO DE CONTRATOS PDF (download) ────────────────
    case 'gestor/relatorios/contratos/pdf':
        auth_required();
        require __DIR__ . '/app/Views/gestor/relatorio_contratos_pdf.php';
        break;

    // ── AUDITORIA ───────────────────────────────────────────
    case 'gestor/auditoria':
        auth_required();
        require __DIR__ . '/app/Views/gestor/auditoria.php';
        break;

    // ── BACKUP DO BANCO ──────────────────────────────────────
    case 'gestor/backup':
        require_role('admin');
        require __DIR__ . '/app/Views/gestor/backup_bd.php';
        break;

    // ── CLIENTES ──────────────────────────────────────────────
    case 'gestor/clientes':
        auth_required();
        require __DIR__ . '/app/Views/gestor/clientes.php';
        break;

    case 'gestor/clientes/novo':
    case 'gestor/clientes/editar':
        auth_required();
        require __DIR__ . '/app/Views/gestor/cliente_form.php';
        break;

    case 'gestor/clientes/ficha':
        auth_required();
        require __DIR__ . '/app/Views/gestor/cliente_ficha.php';
        break;

    case 'gestor/clientes/salvar':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/salvar_cliente.php';
        break;

    case 'gestor/clientes/status':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/cliente_status.php';
        break;

    case 'gestor/clientes/excluir':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/excluir_cliente.php';
        break;

    // ── AGÊNCIAS ──────────────────────────────────────────────
    case 'gestor/agencias':
        auth_required();
        require __DIR__ . '/app/Views/gestor/agencias.php';
        break;

    case 'gestor/agencias/novo':
    case 'gestor/agencias/editar':
        auth_required();
        require __DIR__ . '/app/Views/gestor/agencia_form.php';
        break;

    case 'gestor/agencias/ficha':
        auth_required();
        require __DIR__ . '/app/Views/gestor/agencia_ficha.php';
        break;

    case 'gestor/agencias/salvar':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/salvar_agencia.php';
        break;

    case 'gestor/agencias/importar':
        auth_required();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require __DIR__ . '/app/Views/gestor/api/importar_agencias.php';
        } else {
            require __DIR__ . '/app/Views/gestor/agencia_importar.php';
        }
        break;

    case 'gestor/agencias/excluir':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/excluir_agencia.php';
        break;

    // ── USUÁRIOS (admin) ─────────────────────────────────────
    case 'gestor/usuarios':
        require_role('admin');
        require __DIR__ . '/app/Views/gestor/usuarios.php';
        break;

    case 'gestor/usuarios/novo':
    case 'gestor/usuarios/editar':
        require_role('admin');
        require __DIR__ . '/app/Views/gestor/usuario_form.php';
        break;

    case 'gestor/usuarios/salvar':
        require_role('admin');
        require __DIR__ . '/app/Views/gestor/api/salvar_usuario.php';
        break;

    case 'gestor/usuarios/status':
        require_role('admin');
        require __DIR__ . '/app/Views/gestor/api/usuario_status.php';
        break;

    // ── LOG DE ACESSOS (admin) ───────────────────────────────
    case 'gestor/logs-acesso':
        require_role('admin');
        require __DIR__ . '/app/Views/gestor/logs_acesso.php';
        break;

    // ── MAPA DE PONTOS ───────────────────────────────────────
    case 'gestor/mapa':
        auth_required();
        require __DIR__ . '/app/Views/gestor/mapa.php';
        break;

    // ── LISTA DE CAMPANHAS ───────────────────────────────
    case 'gestor/campanhas':
        auth_required();
        require __DIR__ . '/app/Views/gestor/campanhas.php';
        break;

    // ── CAMPANHAS: BUSCAR (AJAX, GET) ─────────────────────
    case 'gestor/campanhas/buscar':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/campanhas_buscar.php';
        break;

    // ── CAMPANHAS: SALVAR (POST) ──────────────────────────
    case 'gestor/campanhas/salvar':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/salvar_campanha.php';
        break;

    // ── CAMPANHAS: ENCERRAR (POST) ────────────────────────
    case 'gestor/campanhas/encerrar':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/encerrar_campanha.php';
        break;

    // ── CAMPANHAS: CHECKING (interface de upload) ────────
    case 'gestor/campanhas/checking':
        auth_required();
        require __DIR__ . '/app/Views/gestor/campanhas/checking.php';
        break;

    // ── CAMPANHAS: CHECKING UPLOAD (API, POST) ───────────
    case 'gestor/campanhas/checking/upload':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/checking_upload.php';
        break;

    // ── CAMPANHAS: CHECKING PDF (download) ───────────────
    case 'gestor/campanhas/checking/pdf':
        auth_required();
        require __DIR__ . '/app/Views/gestor/campanhas/checking_pdf.php';
        break;

    // ── CAMPANHAS: DOCUMENTOS P.I./P.P. UPLOAD (API, POST) ───
    case 'gestor/campanhas/documentos/upload':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/pi_pp_upload.php';
        break;

    // ── CAMPANHAS: ESPELHO DE COLAGEM PDF (download) ─────
    case 'gestor/campanhas/espelho/pdf':
        auth_required();
        require __DIR__ . '/app/Views/gestor/campanhas/espelho_pdf.php';
        break;

    // ── CAMPANHAS: RENOVAR (POST) ────────────────────────
    case 'gestor/campanhas/renovar':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/renovar_campanha.php';
        break;

    // ── CAMPANHAS: PROCESSAR VENCIDOS (POST) ─────────────
    case 'gestor/campanhas/processar-vencidos':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/processar_vencidos.php';
        break;

    // ── MÍDIA KIT (tela de administração) ────────────────
    case 'gestor/midia-kit':
        auth_required();
        require __DIR__ . '/app/Views/gestor/midia_kit.php';
        break;

    // ── MÍDIA KIT: SALVAR (POST) ──────────────────────────
    case 'gestor/midia-kit/salvar':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/midia_kit_salvar.php';
        break;

    // ── MÍDIA KIT: EXCLUIR (POST) ─────────────────────────
    case 'gestor/midia-kit/excluir':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/midia_kit_excluir.php';
        break;

    // ── MÍDIA KIT: MOVER / REORDENAR (POST) ───────────────
    case 'gestor/midia-kit/mover':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/midia_kit_mover.php';
        break;

    // ── MÍDIA KIT: UPLOAD DE FOTO (POST) ──────────────────
    case 'gestor/midia-kit/upload':
        auth_required();
        require __DIR__ . '/app/Views/gestor/api/midia_kit_upload.php';
        break;

    // ── MÍDIA KIT: GERAR PDF (download) ───────────────────
    case 'gestor/midia-kit/pdf':
        auth_required();
        require __DIR__ . '/app/Views/gestor/midia_kit_pdf.php';
        break;

    // ── 404 ─────────────────────────────────────────────────
    default:
        http_response_code(404);
        require __DIR__ . '/app/Views/errors/404.php';
        break;
}
