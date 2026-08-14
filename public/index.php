<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['usuario'])) {
    header("Location: " . (defined('BASE') ? BASE : '') . "/gestor");
    exit;
}

const LOGIN_MAX_TENTATIVAS_CONTA = 5;
const LOGIN_BLOQUEIO_MINUTOS     = 15;
const LOGIN_MAX_TENTATIVAS_IP    = 15;
const LOGIN_JANELA_IP_MINUTOS    = 15;

function login_registrar(PDO $pdo, string $usuario, bool $sucesso, string $motivo, string $ip, string $ua): void {
    try {
        $pdo->prepare("INSERT INTO login_logs (usuario, sucesso, motivo, ip, user_agent) VALUES (?, ?, ?, ?, ?)")
            ->execute([$usuario, $sucesso ? 1 : 0, $motivo, $ip, $ua]);
    } catch (Exception $e) {
        error_log('LOGIN_LOG ERRO: ' . $e->getMessage());
    }
}

$erro = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha   = $_POST['senha'] ?? '';
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua      = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    if (empty($_POST['_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['_token'])) {
        $erro = "Requisição inválida. Tente novamente.";
    } elseif (empty($usuario) || empty($senha)) {
        $erro = "Usuário e senha são obrigatórios";
    } else {
        try {
            require_once __DIR__ . '/../config/database.php';
            $pdo = getDatabase();

            // Throttle por IP: barra força-bruta mesmo contra usuários inexistentes
            $stIp = $pdo->prepare("SELECT COUNT(*) FROM login_logs WHERE ip = ? AND sucesso = 0 AND criado_em > (NOW() - INTERVAL ? MINUTE)");
            $stIp->execute([$ip, LOGIN_JANELA_IP_MINUTOS]);
            $tentativasIp = (int)$stIp->fetchColumn();

            if ($tentativasIp >= LOGIN_MAX_TENTATIVAS_IP) {
                $erro = "Muitas tentativas de login. Tente novamente em alguns minutos.";
                login_registrar($pdo, $usuario, false, 'ip_bloqueado', $ip, $ua);
            } else {
                $stmt = $pdo->prepare("SELECT *, (locked_until IS NOT NULL AND locked_until > NOW()) AS bloqueado FROM admins WHERE usuario = ? LIMIT 1");
                $stmt->execute([$usuario]);
                $user = $stmt->fetch();

                $isAtivo = !isset($user['ativo']) || $user['ativo'] == 1 || $user['ativo'] === '1';
                $contaBloqueada = $user && (bool)$user['bloqueado'];

                if ($user && $contaBloqueada) {
                    $erro = "Conta temporariamente bloqueada por excesso de tentativas. Tente novamente mais tarde.";
                    login_registrar($pdo, $usuario, false, 'conta_bloqueada', $ip, $ua);
                } elseif ($user && $isAtivo && password_verify($senha, $user['senha'])) {
                    session_regenerate_id(true);
                    $_SESSION['usuario']       = $user['usuario'];
                    $_SESSION['usuario_id']    = $user['id'];
                    $_SESSION['usuario_role']  = $user['role'] ?? 'admin';
                    $_SESSION['last_activity'] = time();
                    $_SESSION['regenerated_at'] = time();

                    $pdo->prepare("UPDATE admins SET failed_attempts = 0, locked_until = NULL, ultimo_login = NOW() WHERE id = ?")
                        ->execute([$user['id']]);
                    login_registrar($pdo, $usuario, true, 'ok', $ip, $ua);

                    if (!empty($_POST['lembrar'])) {
                        require_once __DIR__ . '/../config/remember.php';
                        remember_emitir($pdo, (int)$user['id']);
                    }

                    header("Location: " . BASE . "/gestor?logado=1");
                    exit;
                } else {
                    if ($user) {
                        $tentativas = (int)$user['failed_attempts'] + 1;
                        if ($tentativas >= LOGIN_MAX_TENTATIVAS_CONTA) {
                            $pdo->prepare("UPDATE admins SET failed_attempts = 0, locked_until = (NOW() + INTERVAL ? MINUTE) WHERE id = ?")
                                ->execute([LOGIN_BLOQUEIO_MINUTOS, $user['id']]);
                        } else {
                            $pdo->prepare("UPDATE admins SET failed_attempts = ? WHERE id = ?")
                                ->execute([$tentativas, $user['id']]);
                        }
                        login_registrar($pdo, $usuario, false, $isAtivo ? 'senha_invalida' : 'inativo', $ip, $ua);
                    } else {
                        login_registrar($pdo, $usuario, false, 'usuario_invalido', $ip, $ua);
                    }
                    $erro = "Usuário ou senha incorretos";
                }
            }
        } catch (Exception $e) {
            error_log('LOGIN ERRO: ' . $e->getMessage());
            $erro = "Erro interno. Tente novamente.";
        }
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Imagem/vídeo do painel direito: roda entre os banners disponíveis, trocando uma vez por dia
$bannerDir = __DIR__ . '/assets/img/login-banners';
$banners   = glob($bannerDir . '/*.{svg,png,jpg,jpeg,webp,gif,mp4,webm}', GLOB_BRACE) ?: [];
sort($banners);
$bannerUrl   = null;
$bannerVideo = false;
if ($banners) {
    $indice      = (int)date('z') % count($banners);
    $arquivo     = basename($banners[$indice]);
    $bannerUrl   = '/public/assets/img/login-banners/' . $arquivo;
    $bannerVideo = in_array(strtolower(pathinfo($arquivo, PATHINFO_EXTENSION)), ['mp4', 'webm']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Login · Impakto</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/public/assets/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/login.css?v=<?= @filemtime(__DIR__ . '/assets/css/login.css') ?>">
</head>
<body>

<div class="login-left">
    <div class="login-container">
        <img src="/public/assets/img/logo.png" alt="Impakto Mídia OOH" class="logo-form">

        <?php if ($erro): ?>
            <div class="erro">
                <span>⚠️</span>
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="form-group">
                <svg class="icone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z" opacity="0"/><path d="M3 6l9 7 9-7"/><rect x="3" y="5" width="18" height="14" rx="2"/></svg>
                <input type="text"
                       name="usuario"
                       placeholder="Usuário"
                       required
                       autocomplete="username"
                       value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>" />
            </div>

            <div class="form-group">
                <svg class="icone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                <input type="password"
                       name="senha"
                       placeholder="Senha"
                       required
                       autocomplete="current-password" />
            </div>

            <label class="lembrar">
                <input type="checkbox" name="lembrar" value="1" <?= !empty($_POST['lembrar']) ? 'checked' : '' ?>>
                Manter conectado neste dispositivo
            </label>

            <button type="submit">Entrar</button>
        </form>

        <a href="mailto:master@impaktomidia.com.br" class="esqueci">Esqueci minha senha</a>

        <div class="rodape">&copy; <?= date('Y') ?> Impakto Mídia OOH · Todos os direitos reservados</div>
    </div>
</div>

<div class="login-right">

    <?php if ($bannerUrl && $bannerVideo): ?>
        <video class="banner-img" src="<?= htmlspecialchars($bannerUrl) ?>" autoplay muted loop playsinline></video>
    <?php elseif ($bannerUrl): ?>
        <img src="<?= htmlspecialchars($bannerUrl) ?>" alt="" class="banner-img">
    <?php endif; ?>
</div>

</body>
</html>
