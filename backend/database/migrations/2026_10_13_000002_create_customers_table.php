<?php

use App\Enums\BrazilianState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            $table->string('name', 120);

            // CPF normalizado: 11 dígitos, sem pontuação. `char` em vez de
            // `varchar` porque o tamanho é invariável, e único porque o
            // requisito proíbe cadastro duplicado — garantido aqui, e não
            // apenas na validação, que é sujeita a condição de corrida
            // entre duas requisições simultâneas.
            $table->char('cpf', 11)->unique();

            // Telefone e CEP como string, preservando zeros à esquerda que
            // um tipo numérico descartaria silenciosamente.
            $table->string('phone', 11);
            $table->char('postal_code', 8);

            $table->char('state', 2);
            $table->string('city', 120);
            $table->string('neighborhood', 120);
            $table->string('street', 180);

            // Número do endereço é string: aceita "123A", "s/n", "km 7".
            $table->string('number', 20);

            // O único campo opcional do requisito.
            $table->string('complement', 120)->nullable();

            // Inativação lógica, não SoftDeletes: o requisito pede
            // reativação e visualização dos inativos, então o estado é
            // informação de domínio, não exclusão. SoftDeletes esconderia
            // os registros das consultas por padrão e misturaria dois
            // conceitos distintos.
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        $this->addDatabaseConstraints();
        $this->addIndexes();
    }

    /**
     * Integridade garantida também no banco.
     *
     * Toda regra abaixo já é validada pelos Form Requests. A duplicação é
     * deliberada: a validação da aplicação produz boa mensagem de erro, a
     * constraint impede que um script, um seeder mal escrito ou um acesso
     * direto ao banco gravem dado inconsistente.
     */
    private function addDatabaseConstraints(): void
    {
        DB::statement(
            "ALTER TABLE customers ADD CONSTRAINT customers_cpf_format_check
             CHECK (cpf ~ '^[0-9]{11}$')"
        );

        // 10 dígitos para fixo com DDD, 11 para celular com nono dígito.
        DB::statement(
            "ALTER TABLE customers ADD CONSTRAINT customers_phone_format_check
             CHECK (phone ~ '^[0-9]{10,11}$')"
        );

        DB::statement(
            "ALTER TABLE customers ADD CONSTRAINT customers_postal_code_format_check
             CHECK (postal_code ~ '^[0-9]{8}$')"
        );

        $states = implode(', ', array_map(
            static fn (string $uf): string => "'".$uf."'",
            BrazilianState::codes(),
        ));

        DB::statement(
            "ALTER TABLE customers ADD CONSTRAINT customers_state_check
             CHECK (state IN ({$states}))"
        );

        // Rejeita string vazia e string só de espaços nos campos
        // obrigatórios. Sem isso, `NOT NULL` aceitaria '' e '   '.
        DB::statement(
            "ALTER TABLE customers ADD CONSTRAINT customers_required_text_check
             CHECK (
                 btrim(name) <> ''
                 AND btrim(city) <> ''
                 AND btrim(neighborhood) <> ''
                 AND btrim(street) <> ''
                 AND btrim(number) <> ''
             )"
        );
    }

    /**
     * Três índices, cada um atendendo a um acesso concreto da aplicação.
     */
    private function addIndexes(): void
    {
        // Listagem padrão: filtra por situação e ordena por nome. Composto
        // nessa ordem porque `is_active` é sempre igualdade e `name` é a
        // ordenação, o que permite ao PostgreSQL percorrer o índice já
        // ordenado em vez de ordenar o resultado.
        DB::statement('CREATE INDEX customers_is_active_name_index ON customers (is_active, name)');

        // Busca por nome: GIN com pg_trgm sobre a mesma expressão usada na
        // consulta. É o que faz o `LIKE '%termo%'` normalizado ser
        // indexável — sem isso, cada busca varreria a tabela inteira.
        DB::statement(
            'CREATE INDEX customers_name_search_index ON customers
             USING gin (immutable_unaccent(lower(name)) gin_trgm_ops)'
        );

        // Busca por CPF é por prefixo (`LIKE '529%'`), atendida pelo índice
        // btree da constraint de unicidade. Um índice trgm adicional para
        // busca por dígitos no meio do CPF não se justifica: identificador
        // se busca do começo, e o índice extra custaria escrita em troca de
        // um caso de uso que não foi pedido.
        DB::statement('CREATE INDEX customers_created_at_index ON customers (created_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
