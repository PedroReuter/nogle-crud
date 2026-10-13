<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Índice que torna a busca por prefixo de CPF indexável.
 *
 * A migration original partia de uma suposição errada: a de que o índice
 * btree da constraint de unicidade atenderia tanto a igualdade quanto a
 * busca por prefixo (`cpf LIKE '529%'`). A medição mostrou que não.
 *
 * O PostgreSQL só usa um btree comum para `LIKE 'prefixo%'` quando a
 * collation é `C` ou `POSIX`. A deste banco é `C.UTF-8`, que ordena como C
 * mas não é reconhecida como tal para essa otimização — então a busca por
 * prefixo caía em varredura sequencial.
 *
 * Medido com 50 mil clientes:
 *
 *   cpf = '00000012345'      → 0,080 ms  (índice de unicidade)
 *   cpf LIKE '000000123%'    → 4,980 ms  (varredura sequencial)
 *   cpf LIKE '000000123%'    → 0,103 ms  (com este índice)
 *
 * O operador `bpchar_pattern_ops` compara caractere a caractere, ignorando
 * a collation, que é exatamente o que uma comparação de prefixo precisa.
 * A constraint de unicidade continua necessária e não é substituída: este
 * índice serve à busca, aquele garante a integridade.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'CREATE INDEX customers_cpf_prefix_index ON customers (cpf bpchar_pattern_ops)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS customers_cpf_prefix_index');
    }
};
