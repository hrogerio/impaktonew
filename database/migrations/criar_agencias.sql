-- ============================================================
-- Migração: Cadastro de Agências
-- Dados cadastrais das agências de publicidade parceiras, com
-- diretoria e departamento de mídia (cada um com nome + e-mail),
-- já que uma agência costuma ter vários diretores e vários contatos
-- de mídia -- ver agencia_contatos.
-- ============================================================

CREATE TABLE IF NOT EXISTS agencias (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome          VARCHAR(200) NOT NULL,
    endereco      VARCHAR(255) DEFAULT NULL,
    telefone      VARCHAR(30)  DEFAULT NULL,
    logo          VARCHAR(255) DEFAULT NULL,
    observacoes   TEXT         DEFAULT NULL,
    ativo         TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    criado_por    VARCHAR(100) DEFAULT NULL,
    INDEX idx_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agencia_contatos (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agencia_id INT UNSIGNED NOT NULL,
    tipo       ENUM('diretoria','midia') NOT NULL,
    nome       VARCHAR(150) NOT NULL,
    email      VARCHAR(150) DEFAULT NULL,
    ordem      INT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_agencia_contatos_agencia
        FOREIGN KEY (agencia_id) REFERENCES agencias(id)
        ON DELETE CASCADE,
    INDEX idx_agencia_id (agencia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
