<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Caso de teste base
|--------------------------------------------------------------------------
|
| RefreshDatabase envolve cada teste numa transação que é desfeita ao final.
| É o que garante a independência exigida pelo requisito: a ordem de
| execução não altera o resultado, e nenhum teste enxerga dado deixado por
| outro. Também é mais rápido do que recriar o schema a cada teste.
|
| Aplicado apenas em Feature: os testes de Unit exercitam lógica pura (a
| validação de CPF, a normalização de dígitos) e não devem pagar o custo de
| abrir conexão com o banco.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Auxiliares
|--------------------------------------------------------------------------
*/

/**
 * CPFs válidos fixos.
 *
 * Valores fixos, e não `Cpf::random()`, nos testes que afirmam sobre um CPF
 * específico: um teste que falha precisa falhar sempre com a mesma entrada,
 * senão não há como reproduzi-lo. O `Cpf::random()` continua sendo usado
 * pelo factory, onde o valor concreto é irrelevante.
 *
 * @return array<string, string>
 */
function validCpfs(): array
{
    return [
        'a' => '52998224725',
        'b' => '11144477735',
        'c' => '76670069030',
        'd' => '01295310902',   // começa com zero: guarda contra normalização numérica
        'e' => '53749133603',
        'f' => '47374285744',
    ];
}

/**
 * Payload completo e válido de cliente.
 *
 * Centralizado para que cada teste declare apenas o que muda em relação ao
 * caso válido. Um teste que sobrescreve `['cpf' => '111.111.111-11']` deixa
 * explícito que o CPF é a única variável em jogo.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function customerPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Joana Pereira dos Santos',
        'cpf' => '529.982.247-25',
        'phone' => '(11) 98888-7777',
        'postal_code' => '01001-000',
        'state' => 'SP',
        'city' => 'São Paulo',
        'neighborhood' => 'Sé',
        'street' => 'Praça da Sé',
        'number' => '100',
        'complement' => 'Apto 12',
    ], $overrides);
}
