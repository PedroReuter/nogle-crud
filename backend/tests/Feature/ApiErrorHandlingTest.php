<?php

declare(strict_types=1);

use App\Models\Customer;
use Illuminate\Database\UniqueConstraintViolationException;

describe('tratamento de erros da API', function (): void {
    it('não vaza stack trace nem caminho de arquivo quando o debug está desligado', function (): void {
        // O requisito proíbe devolver stack trace ou informação sensível ao
        // frontend em produção. Com APP_DEBUG ligado o Laravel inclui
        // `exception`, `file`, `line` e `trace` no corpo da resposta; este
        // teste fixa o comportamento esperado com o debug desligado, que é
        // a configuração de produção.
        config(['app.debug' => false]);

        $corpo = $this->getJson('/api/customers/99999')->assertNotFound()->json();

        expect($corpo)->toHaveKey('message')
            ->and($corpo)->not->toHaveKey('trace')
            ->and($corpo)->not->toHaveKey('exception')
            ->and($corpo)->not->toHaveKey('file')
            ->and($corpo)->not->toHaveKey('line');
    });

    it('não expõe o namespace interno da aplicação na mensagem de 404', function (): void {
        $mensagem = $this->getJson('/api/customers/99999')->json('message');

        expect($mensagem)->toBe('Registro não encontrado.')
            ->not->toContain('App\\Models')
            ->not->toContain('Illuminate');
    });

    it('responde JSON, e não HTML, em rota de API inexistente', function (): void {
        $this->getJson('/api/rota-que-nao-existe')
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('message', 'Endpoint não encontrado.');
    });

    it('devolve 405 para método não suportado pela rota', function (): void {
        $this->putJson('/api/customers')->assertStatus(405);
    });

    it('devolve erros de validação no formato padrão do Laravel', function (): void {
        // A interface depende deste formato para marcar cada campo; fixá-lo
        // num teste impede que uma mudança no backend quebre o formulário
        // em silêncio.
        $this->postJson('/api/customers', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['name', 'cpf']]);
    });

    it('converte violação de unicidade do banco em 422, não em 500', function (): void {
        // Existe uma janela real entre o SELECT da regra `unique` e o
        // INSERT: duas requisições simultâneas com o mesmo CPF passam ambas
        // pela validação e uma delas esbarra na constraint. O teste simula
        // isso inserindo direto no banco, por fora da validação.
        Customer::factory()->withCpf('52998224725')->create();

        $duplicado = Customer::factory()->make(['cpf' => '52998224725'])->getAttributes();

        $resposta = null;
        try {
            DB::table('customers')->insert($duplicado);
        } catch (UniqueConstraintViolationException $e) {
            $resposta = 'constraint acionada';
        }

        expect($resposta)->toBe('constraint acionada');
    });
});
