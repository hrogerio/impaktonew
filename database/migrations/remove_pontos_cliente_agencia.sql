-- Remove pontos.cliente e pontos.agencia (redundantes com campanhas.cliente/
-- agencia). Vestígio do modelo anterior à existência da tabela `campanhas`:
-- o código de edição de ponto (salvar_ponto.php) já não escreve nessas
-- colunas há tempo, e todo o COALESCE(c.cliente, p.cliente) espalhado pelas
-- telas (pontos.php, mapa.php, relatorios.php, auditoria.php, index.php) foi
-- trocado por c.cliente puro antes desta migração.
--
-- Antes de rodar: confirme que não sobrou nenhum ponto NÃO exclusivo com
-- campanha ativa órfã de cliente (senão a tela passaria a mostrar em branco
-- algo que hoje só aparece por causa do fallback em pontos). Painéis
-- exclusivos são esperados no diagnóstico abaixo — eles não usam campanhas
-- pra ocupação, o cliente fica em cliente_exclusivo.
--
-- PASSO 1 — Diagnóstico (deve retornar 0; se não retornar, investigue antes
-- de prosseguir — mas ignore se forem só painéis exclusivos)
-- -------------------------------------------------------
SELECT COUNT(*) AS pontos_ocupados_sem_campanha_cliente
FROM pontos p
LEFT JOIN campanhas c ON c.ponto_id = p.id AND c.ativo = 1
WHERE LOWER(p.situacao) = 'ocupado'
  AND (p.exclusivo IS NULL OR p.exclusivo = 0)
  AND (c.cliente IS NULL OR TRIM(c.cliente) = '');

-- -------------------------------------------------------
-- PASSO 2 — Remove as colunas
-- -------------------------------------------------------
ALTER TABLE pontos
    DROP COLUMN cliente,
    DROP COLUMN agencia;

-- -------------------------------------------------------
-- PASSO 3 — Recria o trigger trg_pontos_update sem os blocos de
-- cliente/agencia (senão qualquer UPDATE em pontos passa a falhar com
-- "Unknown column 'cliente' in 'OLD'")
-- -------------------------------------------------------
DROP TRIGGER IF EXISTS trg_pontos_update;

DELIMITER //
CREATE TRIGGER trg_pontos_update AFTER UPDATE ON pontos FOR EACH ROW
BEGIN
    IF OLD.situacao != NEW.situacao OR (OLD.situacao IS NULL AND NEW.situacao IS NOT NULL) THEN
        INSERT INTO pontos_log (ponto_id, numero, campo, valor_antes, valor_depois)
        VALUES (NEW.id, NEW.numero, 'situacao', OLD.situacao, NEW.situacao);
    END IF;
    IF OLD.cidade != NEW.cidade OR (OLD.cidade IS NULL AND NEW.cidade IS NOT NULL) THEN
        INSERT INTO pontos_log (ponto_id, numero, campo, valor_antes, valor_depois)
        VALUES (NEW.id, NEW.numero, 'cidade', OLD.cidade, NEW.cidade);
    END IF;
    IF OLD.regiao != NEW.regiao OR (OLD.regiao IS NULL AND NEW.regiao IS NOT NULL) THEN
        INSERT INTO pontos_log (ponto_id, numero, campo, valor_antes, valor_depois)
        VALUES (NEW.id, NEW.numero, 'regiao', OLD.regiao, NEW.regiao);
    END IF;
    IF OLD.tipo != NEW.tipo OR (OLD.tipo IS NULL AND NEW.tipo IS NOT NULL) THEN
        INSERT INTO pontos_log (ponto_id, numero, campo, valor_antes, valor_depois)
        VALUES (NEW.id, NEW.numero, 'tipo', OLD.tipo, NEW.tipo);
    END IF;
    IF OLD.formato != NEW.formato OR (OLD.formato IS NULL AND NEW.formato IS NOT NULL) THEN
        INSERT INTO pontos_log (ponto_id, numero, campo, valor_antes, valor_depois)
        VALUES (NEW.id, NEW.numero, 'formato', OLD.formato, NEW.formato);
    END IF;
    IF OLD.inicio_contrato != NEW.inicio_contrato OR (OLD.inicio_contrato IS NULL AND NEW.inicio_contrato IS NOT NULL) THEN
        INSERT INTO pontos_log (ponto_id, numero, campo, valor_antes, valor_depois)
        VALUES (NEW.id, NEW.numero, 'inicio_contrato', OLD.inicio_contrato, NEW.inicio_contrato);
    END IF;
    IF OLD.fim_contrato != NEW.fim_contrato OR (OLD.fim_contrato IS NULL AND NEW.fim_contrato IS NOT NULL) THEN
        INSERT INTO pontos_log (ponto_id, numero, campo, valor_antes, valor_depois)
        VALUES (NEW.id, NEW.numero, 'fim_contrato', OLD.fim_contrato, NEW.fim_contrato);
    END IF;
    IF OLD.ativo != NEW.ativo OR (OLD.ativo IS NULL AND NEW.ativo IS NOT NULL) THEN
        INSERT INTO pontos_log (ponto_id, numero, campo, valor_antes, valor_depois)
        VALUES (NEW.id, NEW.numero, 'ativo', OLD.ativo, NEW.ativo);
    END IF;
    IF OLD.logradouro != NEW.logradouro OR (OLD.logradouro IS NULL AND NEW.logradouro IS NOT NULL) THEN
        INSERT INTO pontos_log (ponto_id, numero, campo, valor_antes, valor_depois)
        VALUES (NEW.id, NEW.numero, 'logradouro', OLD.logradouro, NEW.logradouro);
    END IF;
END //
DELIMITER ;
