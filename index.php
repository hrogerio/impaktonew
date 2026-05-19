<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    session_start();
}

// Detecta se o projeto está num subdiretório (ex: /impaktonew no Laragon)
// Em produção (domínio próprio) basePath fica vazio
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
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

    // ── LISTA DE PONTOS ─────────────────────────────────────
    case 'gestor/pontos':
        auth_required();
        require __DIR__ . '/app/Views/gestor/listar_ponto.php';
        break;

    // ── DETALHES DO PONTO (admin + público) ──────────────────
    case 'gestor/pontos/detalhes':
        require __DIR__ . '/app/Views/gestor/detalhes_ponto.php';
        break;

    // ── PRÉ-SELEÇÃO ─────────────────────────────────────────
    case 'gestor/pre-selecao':
        auth_required();
        require __DIR__ . '/app/Views/gestor/relatorios/pre_selecao.php';
        break;

    // ── RELATÓRIOS ──────────────────────────────────────────
    case 'gestor/relatorios':
        auth_required();
        require __DIR__ . '/app/Views/gestor/relatorios.php';
        break;

    // ── AUDITORIA ───────────────────────────────────────────
    case 'gestor/auditoria':
        auth_required();
        require __DIR__ . '/backup.php';
        break;

    // ── 404 ─────────────────────────────────────────────────
    default:
        http_response_code(404);
        require __DIR__ . '/app/Views/errors/404.php';
        break;
}
