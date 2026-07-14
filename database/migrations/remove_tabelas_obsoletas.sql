-- Remove tabelas confirmadas como mortas (sem nenhuma referência em app/,
-- gestor/, public/ — só aparecem em dumps de backup antigos).
--
-- `usuarios`: autenticação real usa `admins` (ver app/Models/UsuarioModel.php,
--             que apesar do nome aponta para a tabela `admins`).
-- `pontos_backup`: dump manual antigo, sem chave primária, não usado em
--             nenhuma query da aplicação.
--
-- ATENÇÃO: operação destrutiva e irreversível. Faça um dump de segurança
-- antes de rodar, por exemplo:
--   mysqldump -u root impaktomidia usuarios pontos_backup > backup_tabelas_obsoletas.sql

DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS pontos_backup;
