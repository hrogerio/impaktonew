-- ============================================================
-- Migração: Cadastro de Clientes
-- Solicitado pelo financeiro: dados cadastrais dos clientes,
-- independente de possuírem campanha ativa ou não.
-- ============================================================

CREATE TABLE IF NOT EXISTS clientes (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    razao_social  VARCHAR(200) NOT NULL,
    cnpj          VARCHAR(20)  DEFAULT NULL,
    endereco      VARCHAR(255) DEFAULT NULL,
    email         VARCHAR(150) DEFAULT NULL,
    telefone      VARCHAR(30)  DEFAULT NULL,
    contato       VARCHAR(150) DEFAULT NULL,
    observacoes   TEXT         DEFAULT NULL,
    ativo         TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    criado_por    VARCHAR(100) DEFAULT NULL,
    INDEX idx_razao_social (razao_social),
    INDEX idx_cnpj         (cnpj)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
