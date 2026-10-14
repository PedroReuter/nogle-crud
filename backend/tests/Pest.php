<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\AuthManager;
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
| Sessão padrão dos testes de cliente
|--------------------------------------------------------------------------
|
| Os testes de CRUD verificam cadastro, validação, filtros e paginação — não
| autorização. Autenticar como administrador por padrão evita repetir a mesma
| linha em cada um deles e, sobretudo, evita que a autenticação vire ruído
| que esconde o que o teste de fato afirma.
|
| Autenticação e autorização têm testes próprios, em Feature/Auth, onde a
| sessão é montada explicitamente em cada caso.
|
*/

/*
|--------------------------------------------------------------------------
| Origem das requisições de teste
|--------------------------------------------------------------------------
|
| O Sanctum só trata uma requisição como stateful — isto é, só inicia sessão
| e aplica verificação de CSRF — quando ela vem de uma origem listada em
| SANCTUM_STATEFUL_DOMAINS, e identifica a origem pelos cabeçalhos `Origin`
| ou `Referer`. O cliente de teste do Laravel não envia nenhum dos dois, o
| que faria toda requisição ser considerada stateless e o login falhar com
| "Session store not set on request".
|
| Enviar o `Origin` reproduz o que o navegador de fato envia, em vez de
| contornar o mecanismo: o caminho exercitado pelos testes é o mesmo que a
| aplicação usa em produção.
|
*/

pest()->beforeEach(function (): void {
    $this->withHeader('Origin', 'http://localhost');
})->in('Feature');

/*
|--------------------------------------------------------------------------
| Sessão padrão dos testes de cliente
|--------------------------------------------------------------------------
*/

pest()->beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create());
})->in('Feature/Customers', 'Feature/Api', 'Feature/Privacy');

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

/**
 * Descarta os guards já resolvidos nesta aplicação de teste.
 *
 * Em produção cada requisição HTTP recebe um container novo, então o guard
 * resolve o usuário do zero a partir da sessão. No teste o container é
 * reaproveitado entre as chamadas, e o guard do Sanctum mantém em memória o
 * usuário que resolveu antes — o que faz uma requisição posterior ao logout
 * parecer autenticada mesmo com a sessão já encerrada.
 *
 * Chamar isto entre as requisições reproduz o comportamento real. Não é um
 * contorno do mecanismo: a asserção seguinte passa a verificar a sessão, que
 * é o que de fato autentica, em vez do cache do processo de teste.
 */
function forgetResolvedGuards(): void
{
    /** @var AuthManager $auth */
    $auth = app('auth');
    $auth->forgetGuards();
}
