<?php

declare(strict_types=1);

use App\Models\Customer;

describe('cadastro de cliente', function (): void {
    it('cadastra um cliente com dados válidos', function (): void {
        $response = $this->postJson('/api/customers', customerPayload());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Joana Pereira dos Santos')
            ->assertJsonPath('data.cpf', '52998224725')
            ->assertJsonPath('data.cpf_formatted', '529.982.247-25')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('customers', [
            'cpf' => '52998224725',
            'name' => 'Joana Pereira dos Santos',
            'is_active' => true,
        ]);
    });

    it('persiste CPF, telefone e CEP normalizados, sem pontuação', function (): void {
        $this->postJson('/api/customers', customerPayload([
            'cpf' => '529.982.247-25',
            'phone' => '(11) 98888-7777',
            'postal_code' => '01001-000',
        ]))->assertCreated();

        $customer = Customer::sole();

        expect($customer->cpf)->toBe('52998224725')
            ->and($customer->phone)->toBe('11988887777')
            // O zero à esquerda do CEP precisa sobreviver: é a razão de o
            // campo ser string e não inteiro.
            ->and($customer->postal_code)->toBe('01001000');
    });

    it('cria o cliente como ativo mesmo que a requisição peça o contrário', function (): void {
        // `is_active` está fora do `$fillable`; o requisito manda que todo
        // cliente nasça ativo.
        $this->postJson('/api/customers', customerPayload(['is_active' => false]))
            ->assertCreated()
            ->assertJsonPath('data.is_active', true);

        expect(Customer::sole()->is_active)->toBeTrue();
    });

    it('rejeita a requisição quando falta qualquer campo obrigatório', function (string $campo): void {
        $payload = customerPayload();
        unset($payload[$campo]);

        $this->postJson('/api/customers', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$campo]);

        $this->assertDatabaseCount('customers', 0);
    })->with(['name', 'cpf', 'phone', 'postal_code', 'state', 'city', 'neighborhood', 'street', 'number']);

    it('aponta todos os campos ausentes de uma vez', function (): void {
        // Uma requisição vazia deve devolver o conjunto completo de erros,
        // e não parar no primeiro: o formulário precisa marcar todos os
        // campos de uma vez.
        $this->postJson('/api/customers', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name', 'cpf', 'phone', 'postal_code',
                'state', 'city', 'neighborhood', 'street', 'number',
            ]);
    });

    it('rejeita campo obrigatório preenchido apenas com espaços', function (): void {
        $this->postJson('/api/customers', customerPayload(['name' => '     ']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('aceita cliente sem complemento, o único campo opcional', function (): void {
        $this->postJson('/api/customers', customerPayload(['complement' => null]))
            ->assertCreated()
            ->assertJsonPath('data.complement', null);
    });

    it('grava complemento vazio como nulo', function (): void {
        // Evita que o banco acumule '' e null significando a mesma coisa.
        $this->postJson('/api/customers', customerPayload(['complement' => '   ']))
            ->assertCreated()
            ->assertJsonPath('data.complement', null);
    });

    it('rejeita CPF inválido', function (string $cpf): void {
        $this->postJson('/api/customers', customerPayload(['cpf' => $cpf]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cpf']);

        $this->assertDatabaseCount('customers', 0);
    })->with([
        'dígito verificador errado' => '529.982.247-26',
        'sequência repetida' => '111.111.111-11',
        'zeros' => '000.000.000-00',
        'dígitos de menos' => '5299822472',
        'dígitos de mais' => '529982247255',
        'não numérico' => 'abcdefghijk',
    ]);

    it('rejeita CPF já cadastrado', function (): void {
        Customer::factory()->withCpf('52998224725')->create();

        $this->postJson('/api/customers', customerPayload(['cpf' => '529.982.247-25']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cpf'])
            ->assertJsonPath('errors.cpf.0', 'Já existe um cliente cadastrado com este CPF.');

        $this->assertDatabaseCount('customers', 1);
    });

    it('detecta CPF duplicado ainda que enviado com máscara diferente', function (): void {
        // É o caso que a normalização antes da validação protege: sem ela,
        // a regra `unique` compararia '529.982.247-25' com o valor
        // normalizado no banco e não encontraria a duplicata.
        Customer::factory()->withCpf('52998224725')->create();

        $this->postJson('/api/customers', customerPayload(['cpf' => '52998224725']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cpf']);
    });

    it('rejeita telefone fora do formato brasileiro', function (string $phone): void {
        $this->postJson('/api/customers', customerPayload(['phone' => $phone]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    })->with([
        'curto demais' => '1198888',
        'longo demais' => '119888877779',
        'somente letras' => 'telefone',
    ]);

    it('rejeita CEP que não tenha oito dígitos', function (string $cep): void {
        $this->postJson('/api/customers', customerPayload(['postal_code' => $cep]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['postal_code']);
    })->with(['0100100', '010010000', 'abcdefgh']);

    it('rejeita sigla de estado inexistente', function (string $uf): void {
        $this->postJson('/api/customers', customerPayload(['state' => $uf]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['state']);
    })->with(['XX', 'ZZ', 'São Paulo', 'S']);

    it('aceita sigla de estado em minúsculas, normalizando para maiúsculas', function (): void {
        $this->postJson('/api/customers', customerPayload(['state' => 'sp']))
            ->assertCreated()
            ->assertJsonPath('data.state', 'SP');
    });

    it('rejeita valores que excedam o tamanho máximo das colunas', function (string $campo, int $limite): void {
        // Sem esta validação, o PostgreSQL rejeitaria o INSERT e o cliente
        // receberia 500 em vez de 422.
        $this->postJson('/api/customers', customerPayload([$campo => str_repeat('a', $limite + 1)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$campo]);
    })->with([
        ['name', 120],
        ['city', 120],
        ['neighborhood', 120],
        ['street', 180],
        ['number', 20],
        ['complement', 120],
    ]);

    it('ignora campos que não pertencem ao cliente', function (): void {
        // Proteção contra mass assignment: o `$fillable` barra qualquer
        // chave extra, inclusive uma tentativa de fixar o id.
        $this->postJson('/api/customers', customerPayload([
            'id' => 999,
            'is_active' => false,
            'created_at' => '2000-01-01T00:00:00Z',
        ]))->assertCreated();

        $customer = Customer::sole();

        expect($customer->id)->not->toBe(999)
            ->and($customer->is_active)->toBeTrue()
            ->and($customer->created_at->year)->toBe((int) date('Y'));
    });

    it('colapsa espaços repetidos no nome', function (): void {
        $this->postJson('/api/customers', customerPayload(['name' => '  Joana   Pereira  ']))
            ->assertCreated()
            ->assertJsonPath('data.name', 'Joana Pereira');
    });
});
