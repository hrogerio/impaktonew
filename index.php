<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    session_start();
}

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

function auth_required() {
    if (!isset($_SESSION['usuario'])) {
        header("Location: " . BASE . "/?erro=nao_logado");
        exit;
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

    // ── RELATÓRIOS ──────────────────────────────────────────
    case 'gestor/relatorios':
        auth_required();
        require __DIR__ . '/app/Views/gestor/relatorios.php';
        break;

    // ── AUDITORIA ───────────────────────────────────────────
    case 'gestor/auditoria':
        auth_required();
        require __DIR__ . '/app/Views/gestor/auditoria.php';
        break;

    // ── BACKUP DO BANCO ──────────────────────────────────────
    case 'gestor/backup':
        auth_required();
        require __DIR__ . '/app/Views/gestor/backup_bd.php';
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

    // ── 404 ─────────────────────────────────────────────────
    default:
        http_response_code(404);
        require __DIR__ . '/app/Views/errors/404.php';
        break;
}
