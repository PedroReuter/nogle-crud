<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Suporte a busca de nome insensível a caixa e a acentuação.
 *
 * O requisito pede "tratamento consistente de maiúsculas, minúsculas e
 * acentuação em pesquisas". Resolver isso no PHP exigiria carregar as
 * linhas para comparar, o que inviabiliza paginação server-side; resolver
 * com `ILIKE` cobre a caixa, mas não o acento — "jose" não acharia "José".
 *
 * A solução é normalizar no banco com `unaccent()`. O obstáculo é que
 * `unaccent()` é declarada STABLE, não IMMUTABLE, porque depende de um
 * dicionário que poderia ser alterado — e o PostgreSQL só aceita funções
 * IMMUTABLE em índices. A forma documentada de contornar isso é um wrapper
 * que fixa o dicionário explicitamente, tornando o resultado determinístico
 * e, portanto, legitimamente indexável.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ambas são extensões "trusted" no PostgreSQL 13+, então o dono do
        // banco as cria sem precisar de superusuário.
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION immutable_unaccent(text)
            RETURNS text
            LANGUAGE sql
            IMMUTABLE
            STRICT
            PARALLEL SAFE
            AS $$ SELECT public.unaccent('public.unaccent'::regdictionary, $1) $$
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS immutable_unaccent(text)');

        // As extensões não são removidas: podem estar em uso por outros
        // objetos do banco e derrubá-las causaria dano fora do escopo
        // desta migration.
    }
};
