-- Corrige divergência entre pontos.situacao/cliente/agencia e o estado real
-- em campanhas. Achados da auditoria:
--
-- 1) Pontos 332, 333, 334, 352: a campanha foi encerrada corretamente
--    (encerrar_campanha.php já reseta pontos.situacao='Disponivel' ao
--    encerrar), mas alguém remarcou 'situacao' manualmente pra 'Ocupado'
--    depois, via tela de editar ponto — sem nenhuma campanha ativa por trás.
-- 2) Ponto 18 (Autosport): nunca teve campanha criada, só tinha contrato
--    direto em pontos (01/01/2026-30/06/2026, já vencido). Cria-se a
--    campanha retroativa (encerrada) pra preservar o histórico antes de
--    liberar o ponto.
-- 3) ~40 pontos já 'Disponivel' mas com cliente/agencia antigos ainda
--    preenchidos (vestígio do modelo pré-campanhas, sem impacto na tela
--    hoje, mas dado morto).
--
-- PASSO 1 — Cria a campanha retroativa do ponto 18 (preserva histórico)
-- -------------------------------------------------------
INSERT INTO campanhas
    (ponto_id, cliente, agencia, campanha, situacao, inicio, fim, contato, observacoes, ativo, encerrado_em, criado_por)
VALUES
    (18, 'Autosport', 'Midia10', NULL, 'Ocupado', '2026-01-01', '2026-06-30', 'Vitor', NULL, 0, '2026-06-30 00:00:00', 'migracao-2026');

-- -------------------------------------------------------
-- PASSO 2 — Corrige situacao dos pontos travados em 'Ocupado' sem campanha ativa
-- -------------------------------------------------------
UPDATE pontos
SET situacao = 'Disponivel'
WHERE id IN (18, 332, 333, 334, 352);

-- -------------------------------------------------------
-- PASSO 3 — Limpa cliente/agencia de todo ponto Disponivel sem campanha ativa
-- (cobre os 4 recém-corrigidos + os ~40 já identificados antes)
-- -------------------------------------------------------
UPDATE pontos p
SET cliente = NULL, agencia = NULL
WHERE p.situacao = 'Disponivel'
  AND p.cliente IS NOT NULL AND p.cliente NOT IN ('-', '')
  AND NOT EXISTS (
      SELECT 1 FROM campanhas c WHERE c.ponto_id = p.id AND c.ativo = 1
  );

-- Verificação após rodar:
-- SELECT id, situacao, cliente, agencia FROM pontos WHERE id IN (18,332,333,334,352);
-- SELECT COUNT(*) FROM pontos p WHERE p.cliente NOT IN ('-','') AND p.cliente IS NOT NULL
--   AND NOT EXISTS (SELECT 1 FROM campanhas c WHERE c.ponto_id=p.id AND c.ativo=1);
