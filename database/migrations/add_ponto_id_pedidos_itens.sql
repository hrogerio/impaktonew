-- ============================================================
-- Migração: liga cada item de Pedido de Inserção a um ponto
-- cadastrado (tabela `pontos`), substituindo o campo livre de
-- Praça + Qtd por um único ponto por linha (qtd implícita = 1).
-- ============================================================

ALTER TABLE pedidos_financeiros_itens
    ADD COLUMN ponto_id INT UNSIGNED DEFAULT NULL AFTER campo2,
    ADD INDEX idx_ponto (ponto_id),
    ADD CONSTRAINT fk_pedidos_itens_ponto FOREIGN KEY (ponto_id)
        REFERENCES pontos (id) ON DELETE SET NULL;
