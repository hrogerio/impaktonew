-- Migração: adiciona coluna status em pre_selecoes
-- Executar uma única vez no banco de dados

ALTER TABLE pre_selecoes
    ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'rascunho'
        COMMENT 'rascunho | enviada | aprovada | recusada'
    AFTER total_pontos;
