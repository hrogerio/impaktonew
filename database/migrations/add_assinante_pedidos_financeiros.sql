-- ============================================================
-- Migração: assinatura do responsável pela Impakto no P.I./P.P.
-- Os pedidos só podem ser assinados por um dos 3 sócios/responsáveis
-- cadastrados; a imagem da assinatura escolhida entra no PDF.
-- ============================================================

ALTER TABLE pedidos_financeiros
    ADD COLUMN assinante VARCHAR(50) DEFAULT NULL AFTER valor_bruto;
