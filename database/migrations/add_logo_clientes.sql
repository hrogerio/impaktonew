-- ============================================================
-- Migração: logomarca do cliente, mesmo padrão já usado em agencias.logo
-- ============================================================

ALTER TABLE clientes
    ADD COLUMN logo VARCHAR(255) DEFAULT NULL AFTER nome_fantasia;
