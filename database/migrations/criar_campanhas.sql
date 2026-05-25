-- ============================================================
-- Migração: Sistema de Campanhas
-- RODAR EM 2 ETAPAS NO phpMyAdmin (cole e execute um por vez)
-- ============================================================

-- ── ETAPA 1: Criar tabela ─────────────────────────────────
CREATE TABLE IF NOT EXISTS campanhas (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ponto_id     INT UNSIGNED NOT NULL,
    cliente      VARCHAR(200) DEFAULT NULL,
    agencia      VARCHAR(200) DEFAULT NULL,
    campanha     VARCHAR(200) DEFAULT NULL,
    situacao     VARCHAR(50)  NOT NULL DEFAULT 'Ocupado',
    inicio       DATE         DEFAULT NULL,
    fim          DATE         DEFAULT NULL,
    contato      VARCHAR(200) DEFAULT NULL,
    observacoes  TEXT         DEFAULT NULL,
    ativo        TINYINT(1)   NOT NULL DEFAULT 1,
    encerrado_em DATETIME     DEFAULT NULL,
    criado_em    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    criado_por   VARCHAR(100) DEFAULT NULL,
    INDEX idx_ponto_ativo (ponto_id, ativo),
    INDEX idx_cliente     (cliente(60)),
    INDEX idx_fim         (fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
