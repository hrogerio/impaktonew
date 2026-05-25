-- ============================================================
-- Migração: Sistema de Campanhas
-- Cria tabela campanhas e migra dados existentes de pontos
-- Executar em: local e produção
-- ============================================================

CREATE TABLE IF NOT EXISTS campanhas (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    ponto_id    INT NOT NULL,
    cliente     VARCHAR(200) DEFAULT NULL,
    agencia     VARCHAR(200) DEFAULT NULL,
    campanha    VARCHAR(200) DEFAULT NULL   COMMENT 'Nome da campanha: Institucional, Dia das Mães...',
    situacao    VARCHAR(50)  NOT NULL DEFAULT 'Ocupado',
    inicio      DATE         DEFAULT NULL,
    fim         DATE         DEFAULT NULL,
    contato     VARCHAR(200) DEFAULT NULL,
    observacoes TEXT         DEFAULT NULL,
    ativo       TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=vigente, 0=encerrada',
    encerrado_em DATETIME    DEFAULT NULL,
    criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    criado_por  VARCHAR(100) DEFAULT NULL,
    CONSTRAINT fk_camp_ponto FOREIGN KEY (ponto_id) REFERENCES pontos(id) ON DELETE CASCADE,
    INDEX idx_ponto_ativo (ponto_id, ativo),
    INDEX idx_cliente     (cliente(60)),
    INDEX idx_fim         (fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Migrar dados existentes de pontos → campanhas ────────────
-- Só migra pontos com cliente preenchido
INSERT INTO campanhas
    (ponto_id, cliente, agencia, campanha, situacao, inicio, fim, contato, observacoes, ativo, criado_por)
SELECT
    id,
    NULLIF(TRIM(COALESCE(cliente,'')), ''),
    NULLIF(TRIM(COALESCE(agencia,'')), ''),
    NULL,   -- nome da campanha: não havia antes
    CASE
        WHEN situacao IN ('Disponivel','Disponível','') OR situacao IS NULL THEN 'Ocupado'
        ELSE situacao
    END,
    CASE WHEN inicio_contrato IS NULL OR inicio_contrato = '0000-00-00' THEN NULL ELSE inicio_contrato END,
    CASE WHEN fim_contrato    IS NULL OR fim_contrato    = '0000-00-00' THEN NULL ELSE fim_contrato    END,
    NULLIF(TRIM(COALESCE(contato,'')), ''),
    NULLIF(TRIM(COALESCE(observacoes,'')), ''),
    1,
    'migração'
FROM pontos
WHERE (ativo = 1 OR ativo IS NULL)
  AND cliente IS NOT NULL AND TRIM(cliente) != '';
