<?php

declare(strict_types=1);

use App\Models\Customer;

describe('edição de cliente', function (): void {
    it('edita um cliente com dados válidos', function (): void {
        $customer = Customer::factory()->withCpf('52998224725')->create();

        $this->putJson("/api/customers/{$customer->id}", customerPayload([
            'name' => 'Joana Pereira Atualizada',
            'city' => 'Campinas',
        ]))
            ->assertOk()
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonPath('data.name', 'Joana Pereira Atualizada')
            ->assertJsonPath('data.city', 'Campinas');

        expect($customer->refresh()->name)->toBe('Joana Pereira Atualizada');
    });

    it('aceita que o cliente mantenha o próprio CPF', function (): void {
        // Requisito explícito: salvar o formulário sem mexer no CPF não
        // pode acusar duplicata contra o próprio registro.
        $customer = Customer::factory()->withCpf('52998224725')->create();

        $this->putJson("/api/customers/{$customer->id}", customerPayload([
            'cpf' => '529.982.247-25',
            'name' => 'Nome Novo da Pessoa',
        ]))
            ->assertOk()
            ->assertJsonPath('data.cpf', '52998224725');
    });

    it('aceita o próprio CPF ainda que reenviado sem máscara', function (): void {
        $customer = Customer::factory()->withCpf('52998224725')->create();

        $this->putJson("/api/customers/{$customer->id}", customerPayload(['cpf' => '52998224725']))
            ->assertOk();
    });

    it('permite trocar o CPF por outro ainda não utilizado', function (): void {
        $customer = Customer::factory()->withCpf('52998224725')->create();

        $this->putJson("/api/customers/{$customer->id}", customerPayload(['cpf' => '111.444.777-35']))
            ->assertOk()
            ->assertJsonPath('data.cpf', '11144477735');
    });

    it('rejeita CPF que já pertence a outro cliente', function (): void {
        $outro = Customer::factory()->withCpf('11144477735')->create();
        $customer = Customer::factory()->withCpf('52998224725')->create();

        $this->putJson("/api/customers/{$customer->id}", customerPayload(['cpf' => '111.444.777-35']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cpf']);

        // O CPF do outro cliente permanece intocado.
        expect($outro->refresh()->cpf)->toBe('11144477735')
            ->and($customer->refresh()->cpf)->toBe('52998224725');
    });

    it('rejeita edição com dados inválidos', function (string $campo, mixed $valor): void {
        $customer = Customer::factory()->create();
        $original = $customer->only(['name', 'cpf', 'phone', 'postal_code', 'state']);

        $this->putJson("/api/customers/{$customer->id}", customerPayload([$campo => $valor]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$campo]);

        // Nada foi gravado parcialmente.
        expect($customer->refresh()->only(array_keys($original)))->toEqual($original);
    })->with([
        'CPF inválido' => ['cpf', '111.111.111-11'],
        'nome vazio' => ['name', ''],
        'nome só com espaços' => ['name', '   '],
        'telefone curto' => ['phone', '119'],
        'CEP curto' => ['postal_code', '0100'],
        'UF inexistente' => ['state', 'XX'],
        'cidade vazia' => ['city', ''],
    ]);

    it('aceita PATCH, como prevê a tabela de endpoints', function (): void {
        $customer = Customer::factory()->withCpf('52998224725')->create();

        $this->patchJson("/api/customers/{$customer->id}", customerPayload([
            'name' => 'Alterado Por Patch',
        ]))->assertOk()->assertJsonPath('data.name', 'Alterado Por Patch');
    });

    it('não permite alterar a situação do cliente pela edição', function (): void {
        // A situação muda só pelos endpoints de inativar e reativar, que na
        // Etapa 3 passarão por autorização própria. Se `is_active` fosse
        // atribuível em massa, um PUT contornaria essa verificação.
        $customer = Customer::factory()->inactive()->withCpf('52998224725')->create();

        $this->putJson("/api/customers/{$customer->id}", customerPayload(['is_active' => true]))
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        expect($customer->refresh()->is_active)->toBeFalse();
    });

    it('não permite alterar o id do cliente', function (): void {
        $customer = Customer::factory()->withCpf('52998224725')->create();

        $this->putJson("/api/customers/{$customer->id}", customerPayload(['id' => 9999]))
            ->assertOk()
            ->assertJsonPath('data.id', $customer->id);

        $this->assertDatabaseMissing('customers', ['id' => 9999]);
    });

    it('devolve 404 para cliente inexistente, sem vazar o nome da classe', function (): void {
        $response = $this->putJson('/api/customers/99999', customerPayload());

        $response->assertNotFound()
            ->assertJsonPath('message', 'Registro não encontrado.');

        // O padrão do Laravel exporia "App\Models\Customer" na mensagem.
        expect($response->json('message'))->not->toContain('App\\Models');
    });
});

describe('consulta de cliente', function (): void {
    it('devolve um cliente pelo id', function (): void {
        $customer = Customer::factory()->named('Pessoa Consultada')->create();

        $this->getJson("/api/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonPath('data.name', 'Pessoa Consultada');
    });

    it('devolve 404 para id inexistente', function (): void {
        $this->getJson('/api/customers/99999')->assertNotFound();
    });

    it('monta o endereço completo exigido pela listagem', function (): void {
        $customer = Customer::factory()->create([
            'street' => 'Praça da Sé',
            'number' => '100',
            'complement' => 'Apto 12',
            'neighborhood' => 'Sé',
            'city' => 'São Paulo',
            'state' => 'SP',
            'postal_code' => '01001000',
        ]);

        $this->getJson("/api/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath(
                'data.full_address',
                'Praça da Sé, 100 - Apto 12 - Sé, São Paulo/SP - CEP 01001-000',
            );
    });

    it('omite o complemento do endereço quando não existe', function (): void {
        $customer = Customer::factory()->create([
            'street' => 'Rua das Flores',
            'number' => '42',
            'complement' => null,
            'neighborhood' => 'Centro',
            'city' => 'Curitiba',
            'state' => 'PR',
            'postal_code' => '80010000',
        ]);

        $this->getJson("/api/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath(
                'data.full_address',
                'Rua das Flores, 42 - Centro, Curitiba/PR - CEP 80010-000',
            );
    });

    it('devolve telefone formatado conforme ser fixo ou celular', function (string $phone, string $esperado): void {
        $customer = Customer::factory()->create(['phone' => $phone]);

        $this->getJson("/api/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.phone_formatted', $esperado);
    })->with([
        'celular' => ['11988887777', '(11) 98888-7777'],
        'fixo' => ['1133334444', '(11) 3333-4444'],
    ]);

    it('não expõe colunas além das declaradas no contrato', function (): void {
        $customer = Customer::factory()->create();

        $campos = array_keys($this->getJson("/api/customers/{$customer->id}")->json('data'));

        expect($campos)->toEqualCanonicalizing([
            'id', 'name',
            'cpf', 'cpf_formatted',
            'phone', 'phone_formatted',
            'postal_code', 'postal_code_formatted',
            'street', 'number', 'complement', 'neighborhood', 'city', 'state',
            'full_address', 'is_active',
            'created_at', 'updated_at',
        ]);
    });
});
