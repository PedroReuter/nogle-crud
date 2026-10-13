<?php

declare(strict_types=1);

use App\Models\Customer;

describe('inativação de cliente', function (): void {
    it('inativa um cliente ativo', function (): void {
        $customer = Customer::factory()->create();

        $this->patchJson("/api/customers/{$customer->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonPath('data.is_active', false);

        expect($customer->refresh()->is_active)->toBeFalse();
    });

    it('não apaga o registro ao inativar', function (): void {
        // Requisito explícito: a inativação não pode apagar fisicamente o
        // registro. É também a razão de o projeto usar `is_active` em vez
        // de SoftDeletes.
        $customer = Customer::factory()->create();

        $this->patchJson("/api/customers/{$customer->id}/deactivate")->assertOk();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseCount('customers', 1);
    });

    it('preserva todos os demais dados do cliente', function (): void {
        $customer = Customer::factory()->create();
        $antes = $customer->only(['name', 'cpf', 'phone', 'postal_code', 'city', 'street']);

        $this->patchJson("/api/customers/{$customer->id}/deactivate")->assertOk();

        expect($customer->refresh()->only(array_keys($antes)))->toEqual($antes);
    });

    it('é idempotente: inativar um cliente já inativo devolve 200', function (): void {
        // O resultado pretendido pela chamada já é o estado vigente;
        // responder com erro obrigaria a interface a tratar um caso que não
        // representa falha alguma.
        $customer = Customer::factory()->inactive()->create();

        $this->patchJson("/api/customers/{$customer->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    });

    it('remove o cliente da listagem padrão após inativar', function (): void {
        $customer = Customer::factory()->create();

        expect($this->getJson('/api/customers')->json('meta.total'))->toBe(1);

        $this->patchJson("/api/customers/{$customer->id}/deactivate")->assertOk();

        expect($this->getJson('/api/customers')->json('meta.total'))->toBe(0)
            ->and($this->getJson('/api/customers?status=inactive')->json('meta.total'))->toBe(1);
    });

    it('devolve 404 ao inativar cliente inexistente', function (): void {
        $this->patchJson('/api/customers/99999/deactivate')
            ->assertNotFound()
            ->assertJsonPath('message', 'Registro não encontrado.');
    });
});

describe('reativação de cliente', function (): void {
    it('reativa um cliente inativo', function (): void {
        $customer = Customer::factory()->inactive()->create();

        $this->patchJson("/api/customers/{$customer->id}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        expect($customer->refresh()->is_active)->toBeTrue();
    });

    it('é idempotente: reativar um cliente já ativo devolve 200', function (): void {
        $customer = Customer::factory()->create();

        $this->patchJson("/api/customers/{$customer->id}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    });

    it('devolve o cliente à listagem padrão', function (): void {
        $customer = Customer::factory()->inactive()->create();

        $this->patchJson("/api/customers/{$customer->id}/reactivate")->assertOk();

        expect($this->getJson('/api/customers')->json('meta.total'))->toBe(1);
    });

    it('percorre o ciclo completo de inativar e reativar', function (): void {
        $customer = Customer::factory()->create();

        $this->patchJson("/api/customers/{$customer->id}/deactivate")->assertOk();
        expect($customer->refresh()->is_active)->toBeFalse();

        $this->patchJson("/api/customers/{$customer->id}/reactivate")->assertOk();
        expect($customer->refresh()->is_active)->toBeTrue();

        $this->assertDatabaseCount('customers', 1);
    });

    it('devolve 404 ao reativar cliente inexistente', function (): void {
        $this->patchJson('/api/customers/99999/reactivate')->assertNotFound();
    });
});

describe('exclusão física', function (): void {
    it('não oferece endpoint de exclusão', function (): void {
        // A rota `destroy` não é registrada de propósito: o requisito pede
        // inativação, e expor DELETE significaria oferecer uma operação que
        // a aplicação não deve ter.
        $customer = Customer::factory()->create();

        $this->deleteJson("/api/customers/{$customer->id}")
            ->assertStatus(405);

        $this->assertDatabaseCount('customers', 1);
    });
});
