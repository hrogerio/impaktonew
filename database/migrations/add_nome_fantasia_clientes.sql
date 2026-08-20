-- ============================================================
-- Migração: adiciona Nome Fantasia ao cadastro de clientes
-- Solicitado pra separar Razão Social (nome legal) de Nome
-- Fantasia (como o cliente é conhecido no dia a dia).
-- ============================================================

ALTER TABLE clientes
    ADD COLUMN nome_fantasia VARCHAR(200) DEFAULT NULL AFTER razao_social;
