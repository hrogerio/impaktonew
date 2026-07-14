-- Corrige um engano da migração limpa_pontos_ocupacao_divergente.sql:
-- os pontos 332, 333, 334 (Lotapar) e 352 (Igarashi HF) foram revertidos
-- para 'Disponivel' por parecerem "Ocupado sem campanha ativa" — mas são
-- PAINÉIS EXCLUSIVOS (exclusivo=1, cliente_exclusivo preenchido).
--
-- Painéis exclusivos são comercializados só pra um cliente específico e não
-- têm período de veiculação — por definição não usam a tabela `campanhas`
-- pra tracking de ocupação. 'Ocupado' + cliente_exclusivo É a forma correta
-- de representar isso, não um bug.
--
-- PASSO 1 — Diagnóstico (confirma que são exclusivos)
-- -------------------------------------------------------
SELECT id, numero, exclusivo, cliente_exclusivo, situacao
FROM pontos
WHERE id IN (332, 333, 334, 352);

-- -------------------------------------------------------
-- PASSO 2 — Reverte situacao para Ocupado
-- -------------------------------------------------------
UPDATE pontos
SET situacao = 'Ocupado'
WHERE id IN (332, 333, 334, 352);
