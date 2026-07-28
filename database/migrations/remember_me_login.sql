-- "Manter conectado": login persistente via cookie de longa duração (padrão selector/validator)

CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    selector VARCHAR(24) NOT NULL,
    validator_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_selector (selector),
    KEY idx_admin (admin_id),
    CONSTRAINT fk_remember_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
