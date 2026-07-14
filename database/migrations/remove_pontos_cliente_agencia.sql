-- Remove pontos.cliente e pontos.agencia (redundantes com campanhas.cliente/
-- agencia). Vestígio do modelo anterior à existência da tabela `campanhas`:
-- o código de edição de ponto (salvar_ponto.php) já não escreve nessas
-- colunas há tempo, e todo o COALESCE(c.cliente, p.cliente) espalhado pelas
-- telas (pontos.php, mapa.php, relatorios.php, auditoria.php, index.php) foi
-- trocado por c.cliente puro antes desta migração.
--
-- Antes de rodar: confirme que não sobrou nenhum ponto com campanha ativa
-- órfã de cliente (senão a tela passaria a mostrar em branco algo que hoje
-- só aparece por causa do fallback em pontos).
--
-- PASSO 1 — Diagnóstico (deve retornar 0)
-- -------------------------------------------------------
SELECT COUNT(*) AS pontos_ocupados_sem_campanha_cliente
FROM pontos p
LEFT JOIN campanhas c ON c.ponto_id = p.id AND c.ativo = 1
WHERE LOWER(p.situacao) = 'ocupado'
  AND (c.cliente IS NULL OR TRIM(c.cliente) = '');

-- -------------------------------------------------------
-- PASSO 2 — Remove as colunas
-- -------------------------------------------------------
ALTER TABLE pontos
    DROP COLUMN cliente,
    DROP COLUMN agencia;
