-- Adiciona pre_selecao_id na tabela campanhas
-- Permite rastrear qual reserva/pré-seleção originou a campanha
ALTER TABLE campanhas
    ADD COLUMN pre_selecao_id INT UNSIGNED NULL DEFAULT NULL AFTER criado_por,
    ADD INDEX idx_pre_selecao (pre_selecao_id);
