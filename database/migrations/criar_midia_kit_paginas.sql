-- Páginas do Mídia Kit (cases e divisores de seção)
-- Rodar no phpMyAdmin: banco ipk2024

CREATE TABLE IF NOT EXISTS midia_kit_paginas (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo         ENUM('case','divisor') NOT NULL DEFAULT 'case',
    ponto_id     INT UNSIGNED DEFAULT NULL,
    titulo       VARCHAR(200) NOT NULL DEFAULT '',
    subtitulo    VARCHAR(200) DEFAULT NULL,
    localizacao  VARCHAR(200) DEFAULT NULL,
    foto         VARCHAR(500) DEFAULT NULL,
    logo         VARCHAR(500) DEFAULT NULL,
    ordem        SMALLINT     NOT NULL DEFAULT 0,
    ativo        TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    criado_por   VARCHAR(100) DEFAULT NULL,
    INDEX idx_ordem (ordem),
    INDEX idx_ponto (ponto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
