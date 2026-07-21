-- Documentos financeiros (P.I. / P.P.) arquivados por contrato/campanha
-- Rodar no phpMyAdmin ou mysql client: banco ipk2024 (produção) / impaktomidia (local)

CREATE TABLE IF NOT EXISTS campanha_documentos (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente       VARCHAR(200) NOT NULL DEFAULT '',
    agencia       VARCHAR(200) NOT NULL DEFAULT '',
    campanha      VARCHAR(200) NOT NULL DEFAULT '',
    inicio        DATE DEFAULT NULL,
    fim           DATE DEFAULT NULL,
    tipo          ENUM('PI','PP') NOT NULL,
    caminho       VARCHAR(500) NOT NULL,
    nome_original VARCHAR(255) DEFAULT NULL,
    criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    criado_por    VARCHAR(100) DEFAULT NULL,
    INDEX idx_grupo (cliente(60), campanha(60), inicio, fim),
    INDEX idx_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
