-- Adiciona o tipo CONTRATO ao enum de campanha_documentos
-- Rodar no phpMyAdmin ou mysql client: banco ipk2024 (produção) / impaktomidia (local)

ALTER TABLE campanha_documentos
    MODIFY COLUMN tipo ENUM('CONTRATO','PI','PP') NOT NULL;
