-- Central Comercial: documentos e links comerciais da Impakto Mídia e Impakto Led
-- Rodar no phpMyAdmin ou mysql client: banco ipk2024 (produção) / impaktomidia (local)
-- Arquivos ficam em fotos/comercial/ (pasta ignorada pelo deploy, não é sobrescrita)

CREATE TABLE IF NOT EXISTS comercial_itens (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa       ENUM('midia','led') NOT NULL DEFAULT 'midia',
    categoria     VARCHAR(80)  NOT NULL DEFAULT 'Outros',
    titulo        VARCHAR(200) NOT NULL,
    descricao     VARCHAR(500) NOT NULL DEFAULT '',
    tipo          ENUM('arquivo','link') NOT NULL DEFAULT 'arquivo',
    caminho       VARCHAR(500) DEFAULT NULL,
    nome_original VARCHAR(255) DEFAULT NULL,
    tamanho       INT UNSIGNED DEFAULT NULL,
    url           VARCHAR(1000) DEFAULT NULL,
    destaque      TINYINT(1) NOT NULL DEFAULT 0,
    criado_por    VARCHAR(100) DEFAULT NULL,
    criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT NULL,
    INDEX idx_empresa_cat (empresa, categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
