-- Adiciona FK real em pontos_log.ponto_id (hoje só tem índice solto).
-- Já existem 3 registros órfãos apontando pro ponto 364 (deletado),
-- todos do mesmo evento de alteração em 2026-05-15.
--
-- PASSO 1 — Diagnóstico (rode antes e confira)
-- -------------------------------------------------------
SELECT id, ponto_id, campo, alterado_em, alterado_por
FROM pontos_log
WHERE ponto_id NOT IN (SELECT id FROM pontos);

-- -------------------------------------------------------
-- PASSO 2 — Remove os órfãos (coluna é NOT NULL, não dá pra usar SET NULL)
-- -------------------------------------------------------
DELETE FROM pontos_log
WHERE ponto_id NOT IN (SELECT id FROM pontos);

-- -------------------------------------------------------
-- PASSO 3 — Adiciona a constraint
-- -------------------------------------------------------
ALTER TABLE pontos_log
    ADD CONSTRAINT fk_pontos_log_ponto
        FOREIGN KEY (ponto_id) REFERENCES pontos(id)
        ON DELETE CASCADE ON UPDATE CASCADE;

-- Observação: CASCADE aqui segue o mesmo padrão de ponto_fotos->pontos —
-- se o ponto for apagado de verdade (raro, hoje é soft-delete via `ativo`),
-- o histórico de log dele some junto em vez de virar órfão de novo.
