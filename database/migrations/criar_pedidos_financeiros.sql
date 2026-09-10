-- ============================================================
-- Migração: Pedidos Financeiros (Pedido de Inserção / Pedido de Produção)
-- Módulo independente de campanhas: gera P.I. e P.P. em PDF a partir
-- de dados digitados na hora (cliente, itens, valores, parcelas), com
-- numeração por data de emissão + sequência do dia (ex: "PI 260910-A").
-- ============================================================

CREATE TABLE IF NOT EXISTS pedidos_financeiros (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo                  ENUM('PI','PP') NOT NULL,
    numero_data           DATE NOT NULL,                   -- data usada na numeração (= data de emissão no momento da criação)
    numero_seq            TINYINT UNSIGNED NOT NULL,        -- sequência do dia: 1=A, 2=B, 3=C...
    status                ENUM('rascunho','emitido') NOT NULL DEFAULT 'rascunho',

    data_emissao          DATE         DEFAULT NULL,
    periodo_inicio        DATE         DEFAULT NULL,        -- 1º dia do mês de início (select mês/ano no form)
    periodo_fim           DATE         DEFAULT NULL,        -- 1º dia do mês de fim — nº de meses é calculado a partir dos dois
    nome_campanha         VARCHAR(200) DEFAULT NULL,

    cliente_razao_social  VARCHAR(200) NOT NULL,
    cliente_cnpj          VARCHAR(20)  DEFAULT NULL,
    cliente_ie            VARCHAR(30)  DEFAULT NULL,
    cliente_endereco      VARCHAR(255) DEFAULT NULL,
    cliente_cidade        VARCHAR(100) DEFAULT NULL,
    cliente_cep           VARCHAR(20)  DEFAULT NULL,
    cliente_telefone      VARCHAR(30)  DEFAULT NULL,
    cliente_email         VARCHAR(150) DEFAULT NULL,

    observacoes           TEXT         DEFAULT NULL,
    qtd_parcelas          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    valor_total           DECIMAL(12,2) NOT NULL DEFAULT 0,  -- soma dos itens (= valor mensal quando qtd_parcelas > 1)
    valor_bruto           DECIMAL(12,2) NOT NULL DEFAULT 0,  -- valor total do contrato (valor_total × qtd_parcelas, editável)

    criado_por            VARCHAR(100) DEFAULT NULL,
    criado_em             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em         DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_tipo_data_seq (tipo, numero_data, numero_seq),
    INDEX idx_tipo_status (tipo, status),
    INDEX idx_cliente (cliente_razao_social)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pedidos_financeiros_itens (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pedido_id    INT UNSIGNED NOT NULL,
    ordem        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    campo1       VARCHAR(200) DEFAULT NULL,   -- P.I.: Mídia · P.P.: Mat/Serviço
    campo2       VARCHAR(200) DEFAULT NULL,   -- P.I.: Praça · P.P.: Descrição
    quantidade   DECIMAL(10,2) DEFAULT NULL,
    valor_unitario DECIMAL(12,2) DEFAULT NULL,
    valor_total  DECIMAL(12,2) NOT NULL DEFAULT 0,

    CONSTRAINT fk_pedidos_itens_pedido FOREIGN KEY (pedido_id)
        REFERENCES pedidos_financeiros (id) ON DELETE CASCADE,
    INDEX idx_pedido (pedido_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pedidos_financeiros_parcelas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pedido_id       INT UNSIGNED NOT NULL,
    numero          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    data_vencimento DATE NOT NULL,
    valor           DECIMAL(12,2) DEFAULT NULL,

    CONSTRAINT fk_pedidos_parcelas_pedido FOREIGN KEY (pedido_id)
        REFERENCES pedidos_financeiros (id) ON DELETE CASCADE,
    INDEX idx_pedido (pedido_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
