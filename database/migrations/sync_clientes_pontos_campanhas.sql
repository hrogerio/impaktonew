-- ── Migração: sincroniza pontos com cliente no campo antigo mas sem campanha ativa ──
-- Execute no phpMyAdmin APÓS verificar o SELECT de diagnóstico abaixo.
--
-- PASSO 1 — Diagnóstico: ver quais pontos serão afetados
-- -------------------------------------------------------
SELECT
    p.id,
    p.numero,
    p.cliente,
    p.agencia,
    p.situacao,
    CASE WHEN p.inicio_contrato IS NULL OR p.inicio_contrato = '0000-00-00' THEN NULL ELSE p.inicio_contrato END AS inicio,
    CASE WHEN p.fim_contrato    IS NULL OR p.fim_contrato    = '0000-00-00' THEN NULL ELSE p.fim_contrato    END AS fim
FROM pontos p
LEFT JOIN campanhas c ON c.ponto_id = p.id AND c.ativo = 1
WHERE (p.ativo = 1 OR p.ativo IS NULL)
  AND c.id IS NULL
  AND p.cliente IS NOT NULL
  AND TRIM(p.cliente) != ''
  AND TRIM(p.cliente) != '-';

-- -------------------------------------------------------
-- PASSO 2 — Inserir campanhas faltantes (rode só após confirmar o SELECT acima)
-- -------------------------------------------------------
INSERT INTO campanhas
    (ponto_id, cliente, agencia, campanha, situacao, inicio, fim, contato, observacoes, ativo, criado_por)
SELECT
    p.id,
    NULLIF(TRIM(p.cliente), ''),
    NULLIF(TRIM(COALESCE(p.agencia, '')), ''),
    NULL,
    CASE
        WHEN p.situacao IN ('Disponivel', 'Disponível', '', '-') OR p.situacao IS NULL THEN 'Ocupado'
        ELSE p.situacao
    END,
    CASE WHEN p.inicio_contrato IS NULL OR p.inicio_contrato = '0000-00-00' THEN NULL ELSE p.inicio_contrato END,
    CASE WHEN p.fim_contrato    IS NULL OR p.fim_contrato    = '0000-00-00' THEN NULL ELSE p.fim_contrato    END,
    NULLIF(TRIM(COALESCE(p.contato, '')), ''),
    NULLIF(TRIM(COALESCE(p.observacoes, '')), ''),
    1,
    'sync-2026'
FROM pontos p
LEFT JOIN campanhas c ON c.ponto_id = p.id AND c.ativo = 1
WHERE (p.ativo = 1 OR p.ativo IS NULL)
  AND c.id IS NULL
  AND p.cliente IS NOT NULL
  AND TRIM(p.cliente) != ''
  AND TRIM(p.cliente) != '-';
