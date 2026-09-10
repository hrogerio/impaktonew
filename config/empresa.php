<?php
/**
 * Dados cadastrais e bancários fixos da Impakto Mídia, usados na emissão
 * de Pedidos de Inserção (P.I.) e Pedidos de Produção (P.P.).
 * Alterar aqui reflete em todo PDF gerado a partir de agora (não afeta
 * pedidos já emitidos, que guardam apenas o texto do cliente).
 */
return [
    'razao_social' => 'IMPAKTO MÍDIA EXTERIOR LTDA',
    'endereco'     => 'Rua Gen. Joaquim Inácio, 830 - Ilha do Leite',
    'cidade'       => 'Recife/PE',
    'cep'          => '50.070-495',
    'cnpj'         => '45.931.463/0001-05',
    'email'        => 'financeiro@impaktomidia.com.br',

    'banco'        => 'Sicredi',
    'agencia'      => '2203',
    'conta'        => '72602-8',
    'chave_pix'    => 'mcaupani@gmail.com',
];
