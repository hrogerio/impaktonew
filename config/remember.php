<?php
// "Manter conectado": login persistente via cookie (padrão selector/validator).
// Compartilhado entre index.php (raiz) e public/index.php, já que public/index.php
// pode ser acessado diretamente pelo Apache (RewriteRule ^public/ - [L]).

const REMEMBER_COOKIE_NOME = 'remember_token';
const REMEMBER_COOKIE_DIAS = 30;

function remember_cookie_secure(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || getenv('SESSION_SECURE') === 'true';
}

// Cria um novo token, grava no banco e envia o cookie. Chamar após login bem-sucedido
// (com "manter conectado" marcado) ou ao rotacionar um token já validado.
function remember_emitir(PDO $pdo, int $adminId): void {
    $selector  = bin2hex(random_bytes(9));
    $validator = bin2hex(random_bytes(32));
    $expiraEm  = time() + 60 * 60 * 24 * REMEMBER_COOKIE_DIAS;

    $pdo->prepare("INSERT INTO remember_tokens (admin_id, selector, validator_hash, expires_at) VALUES (?, ?, ?, ?)")
        ->execute([$adminId, $selector, hash('sha256', $validator), date('Y-m-d H:i:s', $expiraEm)]);

    setcookie(REMEMBER_COOKIE_NOME, $selector . ':' . $validator, [
        'expires'  => $expiraEm,
        'path'     => '/',
        'secure'   => remember_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Remove o cookie do navegador e, se houver PDO, apaga o token correspondente no banco.
function remember_esquecer(?PDO $pdo = null): void {
    if (!empty($_COOKIE[REMEMBER_COOKIE_NOME]) && $pdo) {
        $partes = explode(':', $_COOKIE[REMEMBER_COOKIE_NOME], 2);
        if (count($partes) === 2) {
            $pdo->prepare("DELETE FROM remember_tokens WHERE selector = ?")->execute([$partes[0]]);
        }
    }
    setcookie(REMEMBER_COOKIE_NOME, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => remember_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Se não há sessão ativa mas existe cookie válido, autentica e rotaciona o token.
// Retorna true se conseguiu logar o usuário.
function remember_tentar_login(PDO $pdo): bool {
    if (isset($_SESSION['usuario']) || empty($_COOKIE[REMEMBER_COOKIE_NOME])) {
        return false;
    }

    $partes = explode(':', $_COOKIE[REMEMBER_COOKIE_NOME], 2);
    if (count($partes) !== 2) {
        remember_esquecer();
        return false;
    }
    [$selector, $validator] = $partes;

    try {
        $stmt = $pdo->prepare("SELECT rt.id, rt.admin_id, rt.validator_hash, a.usuario, a.role, a.ativo
                                FROM remember_tokens rt
                                JOIN admins a ON a.id = rt.admin_id
                                WHERE rt.selector = ? AND rt.expires_at > NOW() LIMIT 1");
        $stmt->execute([$selector]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !hash_equals($row['validator_hash'], hash('sha256', $validator))) {
            // Selector existe mas validator não bate: possível cookie roubado/reaproveitado.
            // Revoga o token por segurança.
            if ($row) {
                $pdo->prepare("DELETE FROM remember_tokens WHERE id = ?")->execute([$row['id']]);
            }
            remember_esquecer();
            return false;
        }

        $ativo = $row['ativo'] == 1 || $row['ativo'] === '1';
        if (!$ativo) {
            $pdo->prepare("DELETE FROM remember_tokens WHERE id = ?")->execute([$row['id']]);
            remember_esquecer();
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['usuario']        = $row['usuario'];
        $_SESSION['usuario_id']     = (int)$row['admin_id'];
        $_SESSION['usuario_role']   = $row['role'] ?? 'admin';
        $_SESSION['last_activity']  = time();
        $_SESSION['regenerated_at'] = time();

        // Rotaciona o token a cada uso (mitiga replay caso o cookie vaze)
        $pdo->prepare("DELETE FROM remember_tokens WHERE id = ?")->execute([$row['id']]);
        remember_emitir($pdo, (int)$row['admin_id']);

        $pdo->prepare("UPDATE admins SET ultimo_login = NOW() WHERE id = ?")->execute([$row['admin_id']]);

        return true;
    } catch (Exception $e) {
        error_log('REMEMBER_LOGIN ERRO: ' . $e->getMessage());
        return false;
    }
}
