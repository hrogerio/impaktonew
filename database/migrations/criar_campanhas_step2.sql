-- ── ETAPA 2: Migrar dados existentes de pontos → campanhas ──
-- Só migra pontos com cliente preenchido e não vazio
INSERT INTO campanhas
    (ponto_id, cliente, agencia, campanha, situacao, inicio, fim, contato, observacoes, ativo, criado_por)
SELECT
    id,
    NULLIF(TRIM(COALESCE(cliente, '')), ''),
    NULLIF(TRIM(COALESCE(agencia, '')), ''),
    NULL,
    CASE
        WHEN situacao IN ('Disponivel', 'Disponivel', '') OR situacao IS NULL THEN 'Ocupado'
        ELSE situacao
    END,
    CASE WHEN inicio_contrato IS NULL OR inicio_contrato = '0000-00-00' THEN NULL ELSE inicio_contrato END,
    CASE WHEN fim_contrato    IS NULL OR fim_contrato    = '0000-00-00' THEN NULL ELSE fim_contrato    END,
    NULLIF(TRIM(COALESCE(contato, '')), ''),
    NULLIF(TRIM(COALESCE(observacoes, '')), ''),
    1,
    'migracao'
FROM pontos
WHERE (ativo = 1 OR ativo IS NULL)
  AND cliente IS NOT NULL
  AND TRIM(cliente) != ''
  AND TRIM(cliente) != '-';
