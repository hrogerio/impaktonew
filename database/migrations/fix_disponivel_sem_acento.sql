-- Padroniza situacao 'Disponível' (com acento) → 'Disponivel' (sem acento) na tabela pontos
-- O campo usa latin1 e 'Disponivel' sem acento é o padrão do sistema

UPDATE pontos SET situacao = 'Disponivel'
WHERE situacao = 'Disponível'
   OR situacao LIKE 'Dispon_vel';   -- captura qualquer variante de encoding

-- Verificação após rodar:
-- SELECT situacao, COUNT(*) FROM pontos GROUP BY situacao ORDER BY situacao;
