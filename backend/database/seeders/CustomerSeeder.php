<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

/**
 * Dados fictícios para demonstração.
 *
 * Todos os CPFs são gerados algoritmicamente e todos os nomes vêm do
 * Faker: nenhum dado pessoal real entra no repositório, como exige a
 * seção de segurança do desafio.
 */
final class CustomerSeeder extends Seeder
{
    private const ACTIVE_COUNT = 42;

    private const INACTIVE_COUNT = 8;

    public function run(): void
    {
        // Volume escolhido para que a paginação padrão de 15 registros
        // renda três páginas na demonstração, em vez de uma página só —
        // paginação com uma página não demonstra nada.
        Customer::factory()->count(self::ACTIVE_COUNT)->create();

        // Inativos existem desde o início para que o filtro de situação e
        // a reativação tenham o que exercitar sem precisar inativar alguém
        // durante a demonstração.
        Customer::factory()->count(self::INACTIVE_COUNT)->inactive()->create();

        // Registros com nome e CPF previsíveis, para que o roteiro de
        // demonstração possa buscar um valor conhecido em vez de depender
        // do que o Faker sorteou. Os acentos são deliberados: provam que a
        // busca por "jose avila" encontra "José Ávila".
        Customer::factory()->named('José Ávila Gonçalves')->withCpf('529.982.247-25')->create();
        Customer::factory()->named('Maria Conceição Araújo')->withCpf('111.444.777-35')->create();
    }
}
