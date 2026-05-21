-- Migração: Registro de Pré-Seleções
-- Executar uma única vez no banco de dados

CREATE TABLE IF NOT EXISTS pre_selecoes (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    cliente       VARCHAR(200)  NOT NULL,
    agencia       VARCHAR(200)  DEFAULT NULL,
    periodo_ini   DATE          DEFAULT NULL,
    periodo_fim   DATE          DEFAULT NULL,
    sem_periodo   TINYINT(1)    NOT NULL DEFAULT 0,
    total_pontos  INT           NOT NULL DEFAULT 0,
    criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    criado_por    VARCHAR(100)  DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pre_selecao_pontos (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    pre_selecao_id   INT NOT NULL,
    ponto_id         INT NOT NULL,
    ordem            INT NOT NULL DEFAULT 0,
    FOREIGN KEY (pre_selecao_id) REFERENCES pre_selecoes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
