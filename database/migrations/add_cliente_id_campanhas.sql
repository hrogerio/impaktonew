-- ============================================================
-- Migração: liga Campanhas ao cadastro de Clientes
-- Adiciona campanhas.cliente_id (nullable) apontando pra clientes.id.
-- O campo de texto `cliente` é mantido (compatibilidade com telas/relatórios
-- existentes que já leem/agrupam por ele).
-- ============================================================

ALTER TABLE campanhas
    ADD COLUMN cliente_id INT UNSIGNED DEFAULT NULL AFTER cliente,
    ADD INDEX idx_cliente_id (cliente_id),
    ADD CONSTRAINT fk_campanhas_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON DELETE SET NULL;

-- ── Backfill: liga campanhas existentes a clientes já cadastrados,
--    casando por razão social (case-insensitive, ignorando espaços) ──
UPDATE campanhas c
JOIN clientes cl ON LOWER(TRIM(c.cliente)) = LOWER(TRIM(cl.razao_social))
SET c.cliente_id = cl.id
WHERE c.cliente_id IS NULL;
