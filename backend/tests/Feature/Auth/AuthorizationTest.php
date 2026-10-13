<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\User;

/**
 * Matriz de permissões dos dois perfis do requisito.
 *
 * Administrador — consulta, cadastra, edita, inativa e reativa.
 * Leitura       — apenas consulta e filtra.
 *
 * As requisições abaixo vão direto à API, sem passar pela interface. É o que
 * torna o teste significativo: esconder o botão no frontend não protege
 * nada, e a verificação precisa existir aqui.
 */
describe('acesso sem autenticação', function (): void {
    it('recusa qualquer operação de cliente', function (string $metodo, string $rota): void {
        $customer = Customer::factory()->create();
        $rota = str_replace('{id}', (string) $customer->id, $rota);

        $this->json($metodo, $rota, customerPayload())->assertUnauthorized();
    })->with([
        'listar' => ['GET', '/api/customers'],
        'consultar' => ['GET', '/api/customers/{id}'],
        'cadastrar' => ['POST', '/api/customers'],
        'editar' => ['PUT', '/api/customers/{id}'],
        'inativar' => ['PATCH', '/api/customers/{id}/deactivate'],
        'reativar' => ['PATCH', '/api/customers/{id}/reactivate'],
    ]);

    it('não altera dado algum ao recusar', function (): void {
        $customer = Customer::factory()->create();

        $this->postJson('/api/customers', customerPayload())->assertUnauthorized();
        $this->patchJson("/api/customers/{$customer->id}/deactivate")->assertUnauthorized();

        expect(Customer::count())->toBe(1)
            ->and($customer->refresh()->is_active)->toBeTrue();
    });

    it('responde 401 e não 302, por ser uma API', function (): void {
        // Um redirecionamento para a tela de login quebraria o cliente
        // JavaScript, que espera JSON e não sabe seguir redirecionamento de
        // formulário.
        $resposta = $this->getJson('/api/customers');

        $resposta->assertUnauthorized();
        expect($resposta->headers->get('content-type'))->toContain('application/json');
    });
});

describe('perfil de leitura', function (): void {
    beforeEach(function (): void {
        $this->viewer = User::factory()->viewer()->create();
        $this->actingAs($this->viewer);
    });

    it('pode listar clientes', function (): void {
        Customer::factory()->count(3)->create();

        $this->getJson('/api/customers')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('pode usar os filtros', function (): void {
        Customer::factory()->named('Joana Pereira')->create();
        Customer::factory()->named('Carlos Mendes')->create();

        expect($this->getJson('/api/customers?name=Joana')->json('meta.total'))->toBe(1);
    });

    it('pode consultar um cliente', function (): void {
        $customer = Customer::factory()->create();

        $this->getJson("/api/customers/{$customer->id}")->assertOk();
    });

    it('não pode cadastrar', function (): void {
        $this->postJson('/api/customers', customerPayload())->assertForbidden();

        $this->assertDatabaseCount('customers', 0);
    });

    it('não pode editar', function (): void {
        $customer = Customer::factory()->named('Nome Original')->create();

        $this->putJson("/api/customers/{$customer->id}", customerPayload())
            ->assertForbidden();

        expect($customer->refresh()->name)->toBe('Nome Original');
    });

    it('não pode inativar', function (): void {
        $customer = Customer::factory()->create();

        $this->patchJson("/api/customers/{$customer->id}/deactivate")->assertForbidden();

        expect($customer->refresh()->is_active)->toBeTrue();
    });

    it('não pode reativar', function (): void {
        $customer = Customer::factory()->inactive()->create();

        $this->patchJson("/api/customers/{$customer->id}/reactivate")->assertForbidden();

        expect($customer->refresh()->is_active)->toBeFalse();
    });

    it('recebe 403 antes de a validação ser avaliada', function (): void {
        // A autorização precede a validação: devolver 422 para quem não tem
        // permissão revelaria as regras do formulário a quem não pode usá-lo.
        $this->postJson('/api/customers', [])->assertForbidden();
    });
});

describe('perfil administrador', function (): void {
    beforeEach(function (): void {
        $this->actingAs(User::factory()->admin()->create());
    });

    it('pode cadastrar', function (): void {
        $this->postJson('/api/customers', customerPayload())->assertCreated();
    });

    it('pode editar', function (): void {
        $customer = Customer::factory()->withCpf('52998224725')->create();

        $this->putJson("/api/customers/{$customer->id}", customerPayload())->assertOk();
    });

    it('pode inativar e reativar', function (): void {
        $customer = Customer::factory()->create();

        $this->patchJson("/api/customers/{$customer->id}/deactivate")->assertOk();
        expect($customer->refresh()->is_active)->toBeFalse();

        $this->patchJson("/api/customers/{$customer->id}/reactivate")->assertOk();
        expect($customer->refresh()->is_active)->toBeTrue();
    });

    it('pode listar e consultar', function (): void {
        $customer = Customer::factory()->create();

        $this->getJson('/api/customers')->assertOk();
        $this->getJson("/api/customers/{$customer->id}")->assertOk();
    });
});

describe('escalonamento de privilégio', function (): void {
    it('não permite que o cadastro de cliente altere o perfil do usuário', function (): void {
        // `role` está fora do `$fillable` do User, e nenhum endpoint o
        // aceita; este teste fixa que um campo extra na requisição não
        // encontra caminho até lá.
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)
            ->postJson('/api/customers', customerPayload(['role' => 'admin']))
            ->assertForbidden();

        expect($viewer->refresh()->role->value)->toBe('viewer');
    });

    it('mantém a permissão vinculada ao usuário da sessão, não ao enviado', function (): void {
        $viewer = User::factory()->viewer()->create();
        User::factory()->admin()->create();

        // Tentar se passar por outro usuário informando o id na requisição
        // não muda a autorização, que lê o usuário da sessão.
        $this->actingAs($viewer)
            ->postJson('/api/customers', customerPayload(['user_id' => 999]))
            ->assertForbidden();
    });
});

describe('mensagens de erro', function (): void {
    it('devolve a recusa de permissão em português', function (): void {
        // A interface só usa mensagem própria quando o backend não envia
        // nenhuma; o padrão do Laravel é em inglês e chegaria ao usuário no
        // meio de uma aplicação em português.
        $this->actingAs(User::factory()->viewer()->create())
            ->postJson('/api/customers', customerPayload())
            ->assertForbidden()
            ->assertJsonPath('message', 'Você não tem permissão para realizar esta operação.');
    });

    it('devolve a exigência de autenticação em português', function (): void {
        $this->getJson('/api/customers')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Autenticação necessária. Entre para continuar.');
    });
});
