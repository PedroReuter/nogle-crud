<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trilha de auditoria do cadastro de clientes.
 *
 * Atende ao princípio de responsabilização da LGPD: permitir demonstrar quem
 * acessou ou alterou dado pessoal, e quando.
 *
 * A decisão mais importante aqui é o que a tabela NÃO guarda.
 *
 * Uma trilha que registrasse os valores antigos e novos de cada campo
 * duplicaria CPF, telefone e endereço numa segunda tabela — e, pior,
 * inviabilizaria o direito à eliminação: apagar o cliente deixaria os dados
 * dele vivos no histórico. Por isso são registrados apenas os NOMES dos
 * campos alterados, nunca os valores.
 *
 * O que se perde: não dá para reconstruir o valor anterior a partir da
 * trilha. O que se ganha: a trilha não é, ela própria, um repositório de
 * dado pessoal. Para o propósito de responsabilização — saber que fulano
 * alterou o telefone deste cliente naquele momento — o nome do campo basta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_audits', function (Blueprint $table) {
            $table->id();

            // Sem chave estrangeira, de propósito.
            //
            // A trilha precisa sobreviver à eliminação do cliente — é
            // justamente ela que comprova que a eliminação aconteceu. Uma
            // FK com cascade apagaria a prova junto com o dado.
            $table->unsignedBigInteger('customer_id');

            // Quem agiu. Nulo quando a ação não parte de uma sessão, como no
            // seeder de demonstração.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('event', 20);

            // Apenas nomes de campos, nunca valores. Nulo para eventos que
            // não alteram campos, como a consulta ou a eliminação.
            $table->jsonb('changed_fields')->nullable();

            // Endereço de origem, para investigar acesso indevido. É dado
            // pessoal do operador, não do cliente, e tem finalidade de
            // segurança — o que a LGPD admite.
            $table->string('ip_address', 45)->nullable();

            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement(
            "ALTER TABLE customer_audits ADD CONSTRAINT customer_audits_event_check
             CHECK (event IN ('created', 'updated', 'deactivated', 'reactivated', 'deleted'))"
        );

        // Consulta típica: o histórico de um cliente, do mais recente ao mais
        // antigo.
        DB::statement(
            'CREATE INDEX customer_audits_customer_id_created_at_index
             ON customer_audits (customer_id, created_at DESC)'
        );

        // Consulta de investigação: o que determinado operador fez no período.
        DB::statement(
            'CREATE INDEX customer_audits_user_id_created_at_index
             ON customer_audits (user_id, created_at DESC)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_audits');
    }
};
