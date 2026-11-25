-- Padroniza nomes de contato (responsável comercial) com grafias divergentes
-- em campanhas e pontos: espaços sobrando, "Antônio" e "Cauapani".
UPDATE campanhas SET contato = TRIM(contato) WHERE contato <> TRIM(contato);
UPDATE pontos    SET contato = TRIM(contato) WHERE contato <> TRIM(contato);

UPDATE campanhas SET contato = 'Antonio' WHERE LOWER(TRIM(contato)) IN ('antônio', 'antonio');
UPDATE pontos    SET contato = 'Antonio' WHERE LOWER(TRIM(contato)) IN ('antônio', 'antonio');

UPDATE campanhas SET contato = 'Caupani' WHERE LOWER(TRIM(contato)) IN ('cauapani', 'caupani');
UPDATE pontos    SET contato = 'Caupani' WHERE LOWER(TRIM(contato)) IN ('cauapani', 'caupani');
