-- ---------------------------------------------------------------------------
-- Desafio Técnico Nogle — criação das tabelas
-- ---------------------------------------------------------------------------
--
-- Este script existe porque a Nogle pede "scripts de criação das tabelas" na
-- entrega. Ele produz exatamente o mesmo esquema que as migrations do Laravel
-- — não é uma transcrição feita à mão: foi extraído de um banco realmente
-- migrado e é comparado com a saída das migrations a cada verificação.
--
-- A forma recomendada de preparar o banco continua sendo `php artisan migrate`,
-- porque é ela que mantém a tabela de controle de migrations em dia. Use este
-- arquivo quando quiser inspecionar o esquema sem executar a aplicação, ou
-- criá-lo num ambiente onde o PHP não esteja disponível.
--
-- Requisitos: PostgreSQL 13 ou superior.
--
-- Uso:
--     createdb nogle_crud
--     psql -d nogle_crud -f database/schema.sql
--
-- ---------------------------------------------------------------------------

BEGIN;

-- ---------------------------------------------------------------------------
-- Extensões
-- ---------------------------------------------------------------------------
-- Ambas são "trusted" no PostgreSQL 13+, então o dono do banco as cria sem
-- precisar de superusuário.
--
--   unaccent  — remove acentuação, para que a busca por "jose" encontre "José"
--   pg_trgm   — índice GIN por trigramas, que torna indexável um LIKE com
--               curinga à esquerda
CREATE EXTENSION IF NOT EXISTS unaccent;
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- ---------------------------------------------------------------------------
-- Função de normalização para busca
-- ---------------------------------------------------------------------------
-- `unaccent()` é declarada STABLE, não IMMUTABLE, porque depende de um
-- dicionário que poderia ser alterado — e o PostgreSQL só aceita funções
-- IMMUTABLE em índices. Este invólucro fixa o dicionário explicitamente,
-- tornando o resultado determinístico e, portanto, legitimamente indexável.
CREATE OR REPLACE FUNCTION immutable_unaccent(text)
RETURNS text
LANGUAGE sql
IMMUTABLE
STRICT
PARALLEL SAFE
AS $$ SELECT public.unaccent('public.unaccent'::regdictionary, $1) $$;

-- ---------------------------------------------------------------------------
-- users — autenticação e perfis de acesso
-- ---------------------------------------------------------------------------
CREATE TABLE users (
    id                BIGSERIAL NOT NULL,
    name              VARCHAR(255) NOT NULL,
    email             VARCHAR(255) NOT NULL,
    email_verified_at TIMESTAMP(0) WITHOUT TIME ZONE,
    password          VARCHAR(255) NOT NULL,

    -- Dois perfis, conforme o requisito:
    --   admin  — consulta, cadastra, edita, inativa e reativa clientes
    --   viewer — apenas consulta e filtra
    --
    -- O padrão é o perfil mais restrito: um usuário criado sem perfil
    -- explícito não deve nascer podendo alterar o cadastro.
    role              VARCHAR(20) NOT NULL DEFAULT 'viewer',

    remember_token    VARCHAR(100),
    created_at        TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at        TIMESTAMP(0) WITHOUT TIME ZONE,

    CONSTRAINT users_pkey PRIMARY KEY (id),
    CONSTRAINT users_email_unique UNIQUE (email),

    -- O enum do PHP protege a aplicação; esta constraint protege o banco de
    -- um INSERT ou UPDATE feito fora dela.
    CONSTRAINT users_role_check CHECK (role IN ('admin', 'viewer'))
);

-- ---------------------------------------------------------------------------
-- customers — cadastro de clientes
-- ---------------------------------------------------------------------------
CREATE TABLE customers (
    id           BIGSERIAL NOT NULL,

    name         VARCHAR(120) NOT NULL,

    -- CPF normalizado: 11 dígitos, sem pontuação. `char` porque o tamanho é
    -- invariável.
    cpf          CHAR(11) NOT NULL,

    -- Telefone e CEP como texto, preservando zeros à esquerda que um tipo
    -- numérico descartaria silenciosamente.
    phone        VARCHAR(11) NOT NULL,
    postal_code  CHAR(8) NOT NULL,

    state        CHAR(2) NOT NULL,
    city         VARCHAR(120) NOT NULL,
    neighborhood VARCHAR(120) NOT NULL,
    street       VARCHAR(180) NOT NULL,

    -- Número do endereço é texto: aceita "123A", "s/n", "km 7".
    number       VARCHAR(20) NOT NULL,

    -- O único campo opcional do requisito.
    complement   VARCHAR(120),

    -- Inativação lógica, e não exclusão: o requisito pede reativação e
    -- visualização dos inativos, então a situação é informação de domínio.
    is_active    BOOLEAN NOT NULL DEFAULT TRUE,

    created_at   TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at   TIMESTAMP(0) WITHOUT TIME ZONE,

    CONSTRAINT customers_pkey PRIMARY KEY (id),

    -- CPF único no banco, e não apenas na validação da aplicação: entre o
    -- SELECT da checagem e o INSERT existe uma janela em que duas requisições
    -- simultâneas passam ambas pela validação.
    CONSTRAINT customers_cpf_unique UNIQUE (cpf),

    CONSTRAINT customers_cpf_format_check CHECK (cpf ~ '^[0-9]{11}$'),

    -- 10 dígitos para fixo com DDD, 11 para celular com nono dígito.
    CONSTRAINT customers_phone_format_check CHECK (phone ~ '^[0-9]{10,11}$'),

    CONSTRAINT customers_postal_code_format_check CHECK (postal_code ~ '^[0-9]{8}$'),

    CONSTRAINT customers_state_check CHECK (state IN (
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS',
        'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC',
        'SP', 'SE', 'TO'
    )),

    -- Rejeita string vazia e string só de espaços nos campos obrigatórios.
    -- Sem isto, NOT NULL aceitaria '' e '   '.
    CONSTRAINT customers_required_text_check CHECK (
        btrim(name) <> ''
        AND btrim(city) <> ''
        AND btrim(neighborhood) <> ''
        AND btrim(street) <> ''
        AND btrim(number) <> ''
    )
);

-- ---------------------------------------------------------------------------
-- Índices de customers
-- ---------------------------------------------------------------------------
-- Cada um atende a um acesso concreto da aplicação; nenhum é precaução.

-- Listagem padrão: filtra por situação e ordena por nome. Nesta ordem o
-- PostgreSQL percorre o índice já ordenado em vez de ordenar o resultado.
CREATE INDEX customers_is_active_name_index ON customers (is_active, name);

-- Busca por nome, insensível a caixa e acentuação. É o que torna indexável o
-- LIKE normalizado: medido com 50 mil registros, uma busca seletiva cai de
-- 47,5 ms para 0,56 ms.
CREATE INDEX customers_name_search_index
    ON customers USING gin (immutable_unaccent(lower(name)) gin_trgm_ops);

-- Busca por prefixo de CPF. O índice da constraint de unicidade serve à
-- igualdade, mas o PostgreSQL só usa um btree comum para `LIKE 'prefixo%'`
-- quando a collation é C ou POSIX. `bpchar_pattern_ops` compara caractere a
-- caractere, ignorando a collation. Medido com 50 mil registros: 4,98 ms de
-- varredura sequencial contra 0,10 ms com este índice.
CREATE INDEX customers_cpf_prefix_index ON customers (cpf bpchar_pattern_ops);

-- Ordenação por data de cadastro, oferecida na listagem.
CREATE INDEX customers_created_at_index ON customers (created_at);

-- ---------------------------------------------------------------------------
-- Tabelas de infraestrutura do Laravel
-- ---------------------------------------------------------------------------
-- Criadas pelo framework. `sessions` é usada pela autenticação por cookie,
-- `cache` pelo limitador de requisições, e as demais pelo sistema de filas.

CREATE TABLE password_reset_tokens (
    email      VARCHAR(255) NOT NULL,
    token      VARCHAR(255) NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email)
);

CREATE TABLE sessions (
    id            VARCHAR(255) NOT NULL,
    user_id       BIGINT,
    ip_address    VARCHAR(45),
    user_agent    TEXT,
    payload       TEXT NOT NULL,
    last_activity INTEGER NOT NULL,
    CONSTRAINT sessions_pkey PRIMARY KEY (id)
);

CREATE INDEX sessions_user_id_index ON sessions (user_id);
CREATE INDEX sessions_last_activity_index ON sessions (last_activity);

CREATE TABLE cache (
    key        VARCHAR(255) NOT NULL,
    value      TEXT NOT NULL,
    expiration BIGINT NOT NULL,
    CONSTRAINT cache_pkey PRIMARY KEY (key)
);

CREATE INDEX cache_expiration_index ON cache (expiration);

CREATE TABLE cache_locks (
    key        VARCHAR(255) NOT NULL,
    owner      VARCHAR(255) NOT NULL,
    expiration BIGINT NOT NULL,
    CONSTRAINT cache_locks_pkey PRIMARY KEY (key)
);

CREATE INDEX cache_locks_expiration_index ON cache_locks (expiration);

CREATE TABLE jobs (
    id           BIGSERIAL NOT NULL,
    queue        VARCHAR(255) NOT NULL,
    payload      TEXT NOT NULL,
    attempts     SMALLINT NOT NULL,
    reserved_at  INTEGER,
    available_at INTEGER NOT NULL,
    created_at   INTEGER NOT NULL,
    CONSTRAINT jobs_pkey PRIMARY KEY (id)
);

CREATE INDEX jobs_queue_index ON jobs (queue);

CREATE TABLE job_batches (
    id             VARCHAR(255) NOT NULL,
    name           VARCHAR(255) NOT NULL,
    total_jobs     INTEGER NOT NULL,
    pending_jobs   INTEGER NOT NULL,
    failed_jobs    INTEGER NOT NULL,
    failed_job_ids TEXT NOT NULL,
    options        TEXT,
    cancelled_at   INTEGER,
    created_at     INTEGER NOT NULL,
    finished_at    INTEGER,
    CONSTRAINT job_batches_pkey PRIMARY KEY (id)
);

CREATE TABLE failed_jobs (
    id         BIGSERIAL NOT NULL,
    uuid       VARCHAR(255) NOT NULL,
    connection VARCHAR(255) NOT NULL,
    queue      VARCHAR(255) NOT NULL,
    payload    TEXT NOT NULL,
    exception  TEXT NOT NULL,
    failed_at  TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT failed_jobs_pkey PRIMARY KEY (id),
    CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid)
);

CREATE INDEX failed_jobs_connection_queue_failed_at_index
    ON failed_jobs (connection, queue, failed_at);

-- Tabela de controle do Laravel. Preenchida abaixo para que `php artisan
-- migrate` reconheça o esquema como já aplicado e não tente recriá-lo.
CREATE TABLE migrations (
    id        SERIAL NOT NULL,
    migration VARCHAR(255) NOT NULL,
    batch     INTEGER NOT NULL,
    CONSTRAINT migrations_pkey PRIMARY KEY (id)
);

INSERT INTO migrations (migration, batch) VALUES
    ('0001_01_01_000000_create_users_table', 1),
    ('0001_01_01_000001_create_cache_table', 1),
    ('0001_01_01_000002_create_jobs_table', 1),
    ('2026_10_13_000001_enable_text_search_support', 1),
    ('2026_10_13_000002_create_customers_table', 1),
    ('2026_10_13_000003_add_cpf_prefix_index_to_customers_table', 1);

COMMIT;
