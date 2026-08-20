-- ============================================================
-- Migração: liga Campanhas ao cadastro de Agências
-- Adiciona campanhas.agencia_id (nullable) apontando pra agencias.id.
-- O campo de texto `agencia` é mantido (compatibilidade com telas/relatórios
-- existentes que já leem/agrupam por ele). Mesmo padrão usado em
-- add_cliente_id_campanhas.sql.
-- ============================================================

ALTER TABLE campanhas
    ADD COLUMN agencia_id INT UNSIGNED DEFAULT NULL AFTER agencia,
    ADD INDEX idx_agencia_id (agencia_id),
    ADD CONSTRAINT fk_campanhas_agencia
        FOREIGN KEY (agencia_id) REFERENCES agencias(id)
        ON DELETE SET NULL;

-- ── Backfill: liga campanhas existentes a agências já cadastradas,
--    casando por nome (case-insensitive, ignorando espaços). Sem efeito
--    até existirem agências cadastradas -- salvar_agencia.php refaz esse
--    casamento a cada nova agência criada, pra pegar campanhas antigas ──
UPDATE campanhas c
JOIN agencias a ON LOWER(TRIM(c.agencia)) = LOWER(TRIM(a.nome))
SET c.agencia_id = a.id
WHERE c.agencia_id IS NULL;
