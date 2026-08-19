-- ============================================================
-- Migração: separa "Nome" (do projeto/campanha, ex: "Alto da Passira")
-- do campo "campanha" existente, que passa a ser tratado como "Motivo"
-- (ex: "Obras Avançadas"). Cliente passa a ser exibido a partir do
-- cadastro (clientes.razao_social via cliente_id) quando disponível.
-- ============================================================

ALTER TABLE campanhas
    ADD COLUMN nome VARCHAR(200) DEFAULT NULL AFTER campanha;

-- Backfill: quando a campanha já está ligada a um cliente cujo razão
-- social tem algo entre parênteses (ex: "Correta Loteamentos SPE ( Alto
-- da Passira)"), usa esse trecho como Nome inicial da campanha.
-- Só roda quando `nome` ainda está vazio, então é seguro rodar mais de uma vez.
UPDATE campanhas c
JOIN clientes cl ON cl.id = c.cliente_id
SET c.nome = TRIM(
    SUBSTRING(
        cl.razao_social,
        LOCATE('(', cl.razao_social) + 1,
        LOCATE(')', cl.razao_social) - LOCATE('(', cl.razao_social) - 1
    )
)
WHERE (c.nome IS NULL OR c.nome = '')
  AND cl.razao_social LIKE '%(%)%';
