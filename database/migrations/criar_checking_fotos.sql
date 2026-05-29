-- Checking Fotográfico de Campanhas
-- Rodar no phpMyAdmin: banco ipk2024

CREATE TABLE IF NOT EXISTS checking_fotos (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente    VARCHAR(200) NOT NULL DEFAULT '',
    agencia    VARCHAR(200) NOT NULL DEFAULT '',
    campanha   VARCHAR(200) NOT NULL DEFAULT '',
    situacao   VARCHAR(50)  NOT NULL DEFAULT 'Ocupado',
    inicio     DATE         DEFAULT NULL,
    fim        DATE         DEFAULT NULL,
    ponto_id   INT UNSIGNED NOT NULL,
    caminho    VARCHAR(500) NOT NULL,
    legenda    VARCHAR(300) DEFAULT NULL,
    ordem      SMALLINT     NOT NULL DEFAULT 0,
    criado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    criado_por VARCHAR(100) DEFAULT NULL,
    INDEX idx_grupo  (cliente(60), campanha(60), inicio, fim),
    INDEX idx_ponto  (ponto_id),
    INDEX idx_sit    (situacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
