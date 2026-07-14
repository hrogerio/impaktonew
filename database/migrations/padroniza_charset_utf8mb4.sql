-- Padroniza charset/collation das tabelas antigas (latin1_swedish_ci) para
-- utf8mb4_unicode_ci, igualando às tabelas novas (campanhas, checking_fotos,
-- pre_selecoes, pre_selecao_pontos).
--
-- Motivação: dentro da própria tabela `pontos` havia colunas mistas
-- (cidade/tipo/cliente/agencia em utf8mb3, regiao/situacao/sentido/etc em
-- latin1), o que já causa mojibake em produção (ex.: "Sert�o", "Jaboat�o").
--
-- IMPORTANTE: este ALTER só converte o charset de armazenamento. Ele NÃO
-- corrige textos já corrompidos (mojibake existente continua do jeito que
-- está — ver fix_mojibake_pontos.sql para isso). Faça backup antes de rodar.

ALTER TABLE pontos
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE ponto_fotos
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE pontos_log
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE admins
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Verificação após rodar:
-- SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES
-- WHERE TABLE_SCHEMA = DATABASE();
