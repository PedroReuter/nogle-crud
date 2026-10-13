<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Limite de requisições da API.
 *
 * Protege de algo diferente do limite do login: não da adivinhação de
 * senha, mas da extração em massa do cadastro. A autorização impede o perfil
 * de leitura de alterar clientes; nada o impediria de percorrer a base
 * inteira e levar CPF, telefone e endereço de todos — exceto este limite.
 */
beforeEach(function (): void {
    RateLimiter::clear('1');
    RateLimiter::clear('2');
});

it('limita a quantidade de requisições por minuto', function (): void {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $limite = 120;
    $ultimoStatus = null;

    // Uma a mais que o limite: a última precisa ser recusada.
    for ($i = 1; $i <= $limite + 1; $i++) {
        $ultimoStatus = $this->getJson('/api/customers')->getStatusCode();

        if ($ultimoStatus === 429) {
            break;
        }
    }

    expect($ultimoStatus)->toBe(429)
        ->and($i)->toBeGreaterThan($limite);
});

it('informa o limite e o quanto resta nos cabeçalhos', function (): void {
    // A interface pode usar estes cabeçalhos para se conter antes de ser
    // recusada; sem eles, o cliente só descobre o limite ao esbarrar nele.
    $this->actingAs(User::factory()->admin()->create());

    $this->getJson('/api/customers')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', '120')
        ->assertHeader('X-RateLimit-Remaining', '119');
});

it('conta separadamente para cada usuário', function (): void {
    // Contar por IP faria o consumo de um usuário bloquear os demais atrás
    // do mesmo endereço — numa rede corporativa, todos.
    $primeiro = User::factory()->admin()->create();
    $segundo = User::factory()->admin()->create();

    $this->actingAs($primeiro);
    foreach (range(1, 5) as $ignored) {
        $this->getJson('/api/customers');
    }

    $restanteDoPrimeiro = $this->getJson('/api/customers')->headers->get('X-RateLimit-Remaining');

    $this->actingAs($segundo);
    $restanteDoSegundo = $this->getJson('/api/customers')->headers->get('X-RateLimit-Remaining');

    expect((int) $restanteDoSegundo)->toBeGreaterThan((int) $restanteDoPrimeiro);
});

it('devolve mensagem compreensível ao recusar', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    for ($i = 1; $i <= 121; $i++) {
        $resposta = $this->getJson('/api/customers');

        if ($resposta->getStatusCode() === 429) {
            expect($resposta->json('message'))
                ->toBe('Muitas requisições. Aguarde um instante e tente novamente.');

            return;
        }
    }

    $this->fail('O limite de requisições não foi alcançado.');
});
