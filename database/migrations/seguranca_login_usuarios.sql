-- Segurança de login: bloqueio por tentativas, papéis de acesso e log de auditoria

ALTER TABLE admins
    ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER senha,
    ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'admin' AFTER ativo,
    ADD COLUMN failed_attempts INT NOT NULL DEFAULT 0 AFTER role,
    ADD COLUMN locked_until DATETIME NULL DEFAULT NULL AFTER failed_attempts,
    ADD COLUMN ultimo_login DATETIME NULL DEFAULT NULL AFTER locked_until;

CREATE TABLE IF NOT EXISTS login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL,
    sucesso TINYINT(1) NOT NULL,
    motivo VARCHAR(50) DEFAULT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario_criado (usuario, criado_em),
    INDEX idx_ip_criado (ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
