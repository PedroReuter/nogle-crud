<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Models\Customer;
use App\Models\CustomerAudit;
use App\Models\User;

/**
 * Direito à eliminação.
 *
 * Operação distinta da inativação e com propósito distinto. O requisito do
 * desafio manda que inativar não apague — e continua assim. Esta rota existe
 * para quando o titular pede que seus dados sejam eliminados, situação em que
 * inativar não basta, porque CPF, telefone e endereço continuariam
 * armazenados.
 */
describe('eliminação definitiva', function (): void {
    it('remove o registro do banco', function (): void {
        $customer = Customer::factory()->inactive()->create();

        $this->deleteJson("/api/customers/{$customer->id}")->assertNoContent();

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    });

    it('não deixa nenhum dado pessoal para trás', function (): void {
        // É o ponto da operação: inativar preservaria tudo.
        $customer = Customer::factory()
            ->named('Titular Que Pediu Eliminação')
            ->withCpf('52998224725')
            ->inactive()
            ->create(['phone' => '11988887777']);

        $this->deleteJson("/api/customers/{$customer->id}")->assertNoContent();

        expect(DB::table('customers')->where('cpf', '52998224725')->exists())->toBeFalse();

        // Nem a trilha de auditoria pode ter guardado os valores.
        $trilha = CustomerAudit::all()->toJson();
        expect($trilha)
            ->not->toContain('52998224725')
            ->not->toContain('Titular Que Pediu Eliminação')
            ->not->toContain('11988887777');
    });

    it('preserva a trilha, que comprova o atendimento', function (): void {
        $customer = Customer::factory()->inactive()->create();
        $id = $customer->id;

        $this->deleteJson("/api/customers/{$id}")->assertNoContent();

        $eliminacao = CustomerAudit::where('customer_id', $id)
            ->where('event', AuditEvent::Deleted)
            ->sole();

        expect($eliminacao)->not->toBeNull()
            ->and($eliminacao->customer_id)->toBe($id);
    });

    it('registra quem realizou a eliminação', function (): void {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->inactive()->create();

        $this->actingAs($admin)->deleteJson("/api/customers/{$customer->id}")->assertNoContent();

        expect(CustomerAudit::where('event', AuditEvent::Deleted)->sole()->user_id)->toBe($admin->id);
    });

    it('libera o CPF para novo cadastro', function (): void {
        // Consequência esperada: eliminado o titular, o CPF deixa de estar em
        // uso. Se a constraint de unicidade continuasse barrando, o dado não
        // teria sido realmente eliminado.
        $customer = Customer::factory()->inactive()->withCpf('52998224725')->create();

        $this->deleteJson("/api/customers/{$customer->id}")->assertNoContent();

        $this->postJson('/api/customers', customerPayload(['cpf' => '529.982.247-25']))
            ->assertCreated();
    });
});

describe('proteções contra eliminação acidental', function (): void {
    it('recusa eliminar cliente ativo', function (): void {
        // Dois passos deliberados: inativar primeiro dá oportunidade de
        // reverter, e impede que um clique errado apague cadastro em uso.
        $customer = Customer::factory()->create();

        $this->deleteJson("/api/customers/{$customer->id}")->assertForbidden();

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    });

    it('permite após a inativação', function (): void {
        $customer = Customer::factory()->create();

        $this->deleteJson("/api/customers/{$customer->id}")->assertForbidden();

        $this->patchJson("/api/customers/{$customer->id}/deactivate")->assertOk();

        $this->deleteJson("/api/customers/{$customer->id}")->assertNoContent();
    });

    it('devolve 404 para cliente inexistente', function (): void {
        $this->deleteJson('/api/customers/99999')->assertNotFound();
    });
});

describe('autorização', function (): void {
    it('recusa o perfil de leitura', function (): void {
        $customer = Customer::factory()->inactive()->create();

        $this->actingAs(User::factory()->viewer()->create())
            ->deleteJson("/api/customers/{$customer->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    });

    it('recusa quem não tem sessão', function (): void {
        $customer = Customer::factory()->inactive()->create();

        $this->post('/api/logout');
        forgetResolvedGuards();

        $this->deleteJson("/api/customers/{$customer->id}")->assertUnauthorized();

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    });
});

describe('inativação continua não apagando', function (): void {
    it('preserva o registro, como o requisito exige', function (): void {
        // A existência do DELETE não pode ter alterado o comportamento da
        // inativação, que é o fluxo normal do requisito.
        $customer = Customer::factory()->create();

        $this->patchJson("/api/customers/{$customer->id}/deactivate")->assertOk();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'is_active' => false,
        ]);
    });
});
