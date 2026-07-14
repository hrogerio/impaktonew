-- Adiciona chaves estrangeiras reais onde hoje só existem índices soltos.
-- Hoje só ponto_fotos->pontos e pre_selecao_pontos->pre_selecoes têm FK de
-- verdade; campanhas.ponto_id, checking_fotos.ponto_id e
-- campanhas.pre_selecao_id são só índices, sem garantia de integridade.
--
-- Isso já causou dado órfão real: as 4 campanhas abaixo apontam para uma
-- pre_selecao_id (12) que não existe mais.
--
-- PASSO 1 — Diagnóstico (rode antes e confira)
-- -------------------------------------------------------
SELECT id, ponto_id, pre_selecao_id
FROM campanhas
WHERE pre_selecao_id IS NOT NULL
  AND pre_selecao_id NOT IN (SELECT id FROM pre_selecoes);

-- -------------------------------------------------------
-- PASSO 2 — Limpa os órfãos (necessário antes do ALTER, senão a FK falha)
-- -------------------------------------------------------
UPDATE campanhas
SET pre_selecao_id = NULL
WHERE pre_selecao_id IS NOT NULL
  AND pre_selecao_id NOT IN (SELECT id FROM pre_selecoes);

-- -------------------------------------------------------
-- PASSO 3 — Adiciona as constraints
-- -------------------------------------------------------
ALTER TABLE campanhas
    ADD CONSTRAINT fk_campanhas_ponto
        FOREIGN KEY (ponto_id) REFERENCES pontos(id)
        ON DELETE RESTRICT ON UPDATE CASCADE;

-- pre_selecao_id estava como INT UNSIGNED, mas pre_selecoes.id é INT (mesmo
-- tipo usado em pre_selecao_pontos.pre_selecao_id) — precisa igualar antes
-- de criar a FK, senão o MySQL recusa (erro 3780).
ALTER TABLE campanhas
    MODIFY COLUMN pre_selecao_id INT NULL DEFAULT NULL;

ALTER TABLE campanhas
    ADD CONSTRAINT fk_campanhas_pre_selecao
        FOREIGN KEY (pre_selecao_id) REFERENCES pre_selecoes(id)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE checking_fotos
    ADD CONSTRAINT fk_checking_fotos_ponto
        FOREIGN KEY (ponto_id) REFERENCES pontos(id)
        ON DELETE CASCADE ON UPDATE CASCADE;

-- Observação sobre ON DELETE:
--   - campanhas.ponto_id usa RESTRICT: histórico de contrato não deve
--     sumir se alguém apagar um ponto (pontos hoje são soft-delete via
--     `ativo`, então isso não deve travar o fluxo normal).
--   - checking_fotos.ponto_id usa CASCADE, igual ao padrão já usado em
--     ponto_fotos->pontos.
