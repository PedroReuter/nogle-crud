<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Models\Customer;
use App\Models\CustomerAudit;
use App\Models\User;

describe('registro dos eventos', function (): void {
    it('registra o cadastro', function (): void {
        $this->postJson('/api/customers', customerPayload())->assertCreated();

        $audit = CustomerAudit::sole();

        expect($audit->event)->toBe(AuditEvent::Created)
            ->and($audit->customer_id)->toBe(Customer::sole()->id);
    });

    it('registra quem realizou a ação', function (): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/customers', customerPayload())->assertCreated();

        expect(CustomerAudit::sole()->user_id)->toBe($admin->id);
    });

    it('registra a alteração com os campos que mudaram', function (): void {
        $customer = Customer::factory()->withCpf('52998224725')->named('Nome Original')->create();

        $this->putJson("/api/customers/{$customer->id}", customerPayload([
            'cpf' => '529.982.247-25',
            'name' => 'Nome Alterado',
            'city' => 'Campinas',
        ]))->assertOk();

        $audit = CustomerAudit::where('event', AuditEvent::Updated)->sole();

        expect($audit->changed_fields)->toContain('name')
            ->and($audit->changed_fields)->toContain('city');
    });

    it('não registra `updated_at` como campo alterado', function (): void {
        // Ele muda em toda escrita e não diz nada sobre o que o operador fez.
        $customer = Customer::factory()->withCpf('52998224725')->create();

        $this->putJson("/api/customers/{$customer->id}", customerPayload([
            'cpf' => '529.982.247-25',
            'name' => 'Outro Nome Qualquer',
        ]))->assertOk();

        $audit = CustomerAudit::where('event', AuditEvent::Updated)->sole();

        expect($audit->changed_fields)->not->toContain('updated_at');
    });

    it('distingue inativação de alteração comum', function (): void {
        // Inativar altera um único campo; registrar como "alterado"
        // esconderia o que mais importa numa trilha.
        $customer = Customer::factory()->create();

        $this->patchJson("/api/customers/{$customer->id}/deactivate")->assertOk();

        expect(CustomerAudit::where('customer_id', $customer->id)
            ->where('event', AuditEvent::Deactivated)->count())->toBe(1);
    });

    it('distingue reativação', function (): void {
        $customer = Customer::factory()->inactive()->create();

        $this->patchJson("/api/customers/{$customer->id}/reactivate")->assertOk();

        expect(CustomerAudit::where('customer_id', $customer->id)
            ->where('event', AuditEvent::Reactivated)->count())->toBe(1);
    });

    it('não registra evento quando nada muda', function (): void {
        $customer = Customer::factory()->withCpf('52998224725')->create();
        CustomerAudit::query()->delete();

        // Reenvia exatamente os mesmos valores.
        $this->putJson("/api/customers/{$customer->id}", customerPayload([
            'cpf' => $customer->cpf,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'postal_code' => $customer->postal_code,
            'state' => $customer->state->value,
            'city' => $customer->city,
            'neighborhood' => $customer->neighborhood,
            'street' => $customer->street,
            'number' => $customer->number,
            'complement' => $customer->complement,
        ]))->assertOk();

        expect(CustomerAudit::count())->toBe(0);
    });

    it('acumula o histórico em ordem', function (): void {
        $customer = Customer::factory()->withCpf('52998224725')->create();

        $this->putJson("/api/customers/{$customer->id}", customerPayload([
            'cpf' => '529.982.247-25', 'name' => 'Nome Novo',
        ]))->assertOk();
        $this->patchJson("/api/customers/{$customer->id}/deactivate")->assertOk();
        $this->patchJson("/api/customers/{$customer->id}/reactivate")->assertOk();

        $eventos = CustomerAudit::where('customer_id', $customer->id)
            ->orderBy('id')->pluck('event')->map->value->all();

        expect($eventos)->toBe(['created', 'updated', 'deactivated', 'reactivated']);
    });
});

describe('minimização de dados', function (): void {
    it('nunca guarda valores de campos, apenas os nomes', function (): void {
        // Esta é a decisão central do desenho da trilha. Guardar os valores
        // duplicaria CPF, telefone e endereço numa segunda tabela — e
        // inviabilizaria o direito à eliminação, porque apagar o cliente
        // deixaria os dados dele vivos no histórico.
        $customer = Customer::factory()->withCpf('52998224725')->create();

        $this->putJson("/api/customers/{$customer->id}", customerPayload([
            'cpf' => '111.444.777-35',
            'name' => 'Fulano de Tal Silva',
            'phone' => '(11) 97777-6666',
        ]))->assertOk();

        $trilhaInteira = CustomerAudit::all()->toJson();

        expect($trilhaInteira)
            ->not->toContain('11144477735')
            ->not->toContain('Fulano de Tal Silva')
            ->not->toContain('11977776666')
            ->not->toContain('52998224725');
    });

    it('guarda apenas nomes de coluna em changed_fields', function (): void {
        $customer = Customer::factory()->withCpf('52998224725')->create();

        $this->putJson("/api/customers/{$customer->id}", customerPayload([
            'cpf' => '529.982.247-25', 'name' => 'Outro Nome',
        ]))->assertOk();

        $campos = CustomerAudit::where('event', AuditEvent::Updated)->sole()->changed_fields;

        foreach ($campos as $campo) {
            expect(Schema::hasColumn('customers', $campo))->toBeTrue();
        }
    });
});

describe('consulta do histórico', function (): void {
    it('devolve o histórico do cliente', function (): void {
        $customer = Customer::factory()->create();
        $this->patchJson("/api/customers/{$customer->id}/deactivate")->assertOk();

        $this->getJson("/api/customers/{$customer->id}/audits")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.event', 'deactivated');
    });

    it('traz o rótulo do evento em português', function (): void {
        $customer = Customer::factory()->create();

        $this->getJson("/api/customers/{$customer->id}/audits")
            ->assertOk()
            ->assertJsonPath('data.0.event_label', 'Cadastrado');
    });

    it('não expõe o endereço de origem na resposta', function (): void {
        // O IP serve à investigação de acesso indevido, feita com acesso ao
        // banco; devolvê-lo na listagem espalharia dado do operador.
        $customer = Customer::factory()->create();

        $campos = array_keys($this->getJson("/api/customers/{$customer->id}/audits")->json('data.0'));

        expect($campos)->not->toContain('ip_address');
    });

    it('não executa consulta por evento ao buscar o operador', function (): void {
        $customer = Customer::factory()->create();
        foreach (range(1, 4) as $i) {
            $this->patchJson("/api/customers/{$customer->id}/deactivate");
            $this->patchJson("/api/customers/{$customer->id}/reactivate");
        }

        $consultas = [];
        DB::listen(function ($q) use (&$consultas): void {
            if (str_contains($q->sql, 'users')) {
                $consultas[] = $q->sql;
            }
        });

        $this->getJson("/api/customers/{$customer->id}/audits")->assertOk();

        // Uma consulta para todos os operadores, não uma por evento.
        expect(count($consultas))->toBeLessThanOrEqual(2);
    });

    it('o perfil de leitura pode consultar o histórico', function (): void {
        // Consultar não altera nada, e responsabilização perde sentido se só
        // quem pode alterar pode auditar.
        $customer = Customer::factory()->create();

        $this->actingAs(User::factory()->viewer()->create())
            ->getJson("/api/customers/{$customer->id}/audits")
            ->assertOk();
    });

    it('exige sessão', function (): void {
        $customer = Customer::factory()->create();

        auth()->logout();

        $this->getJson("/api/customers/{$customer->id}/audits")->assertUnauthorized();
    });
});
