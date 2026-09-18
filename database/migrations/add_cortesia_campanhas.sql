-- ============================================================
-- Migração: sinaliza campanhas de cortesia (sem contrato/PI/PP)
-- Raras, mas precisam constar no sistema sem cair no alerta de
-- "Sem Documentos" do financeiro.
-- ============================================================

ALTER TABLE campanhas
    ADD COLUMN cortesia TINYINT(1) NOT NULL DEFAULT 0 AFTER situacao;
