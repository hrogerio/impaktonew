-- ============================================================
-- Atualização pontual: dados de contato de clientes a partir da
-- planilha "DADOS CLIENTES_SUP.xlsx" (CNPJ, e-mail, telefone,
-- responsável). Só preenche/corrige onde a planilha tinha um valor
-- real e diferente do já cadastrado -- rodar uma vez em produção.
-- Casa por razao_social (case-insensitive) em vez de id, pra não
-- depender dos ids baterem entre local e produção.
-- ============================================================

UPDATE clientes SET telefone = '81 8535-3738'
WHERE LOWER(TRIM(razao_social)) = LOWER('ASS MATERNIDADE');

UPDATE clientes SET telefone = '81 9197-6371', contato = 'FELIPE PADILHA'
WHERE LOWER(TRIM(razao_social)) = LOWER('Correta Loteamentos SPE ( Alto da Passira)');

UPDATE clientes SET telefone = '81 9197-6371', contato = 'FELIPE PADILHA'
WHERE LOWER(TRIM(razao_social)) = LOWER('Correta Empreendimentos (alto da Vitoria)');

UPDATE clientes SET cnpj = '13.596.165/0001-10', email = 'financeiro@ressegdistribuidora.com.br',
       telefone = '+55 81 8748-4079', contato = 'rodrgio'
WHERE LOWER(TRIM(razao_social)) = LOWER('Resseg');

UPDATE clientes SET telefone = '81 9205-4510'
WHERE LOWER(TRIM(razao_social)) = LOWER('Sal & CIA');

-- Corrige typo no nome (Exmius -> Eximius) + completa CNPJ/e-mail
UPDATE clientes SET razao_social = 'Colegio Eximius', cnpj = '08.342.755/0001-86',
       email = 'financeiro@colegioeximius.com.br'
WHERE LOWER(TRIM(razao_social)) = LOWER('Colegio Exmius');

-- "Moda Center" na planilha = "Condominio Moda Center" já cadastrado
UPDATE clientes SET telefone = '81 9197-6371', contato = 'FELIPE PADILHA'
WHERE LOWER(TRIM(razao_social)) = LOWER('Condominio Moda Center');
