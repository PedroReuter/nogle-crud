<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    // O limitador guarda estado no cache entre testes; sem limpar, um teste
    // que esgota as tentativas faria o seguinte falhar por bloqueio.
    RateLimiter::clear('admin@nogle.test|127.0.0.1');
});

describe('login', function (): void {
    it('autentica com credenciais válidas', function (): void {
        $user = User::factory()->admin()->create([
            'email' => 'admin@nogle.test',
            'password' => 'senha-valida',
        ]);

        $this->postJson('/api/login', [
            'email' => 'admin@nogle.test',
            'password' => 'senha-valida',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'admin@nogle.test')
            ->assertJsonPath('data.role', 'admin');

        $this->assertAuthenticatedAs($user);
    });

    it('devolve as permissões do administrador, já resolvidas', function (): void {
        // A interface não deve inferir permissões a partir do nome do papel:
        // se um terceiro perfil surgir, ela não precisa aprender a regra.
        User::factory()->admin()->create(['email' => 'admin@nogle.test', 'password' => 'senha123']);

        $this->postJson('/api/login', ['email' => 'admin@nogle.test', 'password' => 'senha123'])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.permissions.manage_customers', true);
    });

    it('devolve as permissões do perfil de leitura, já resolvidas', function (): void {
        User::factory()->viewer()->create(['email' => 'leitura@nogle.test', 'password' => 'senha123']);

        $this->postJson('/api/login', ['email' => 'leitura@nogle.test', 'password' => 'senha123'])
            ->assertOk()
            ->assertJsonPath('data.role', 'viewer')
            ->assertJsonPath('data.permissions.manage_customers', false);
    });

    it('rejeita senha incorreta', function (): void {
        User::factory()->create(['email' => 'admin@nogle.test', 'password' => 'senha-correta']);

        $this->postJson('/api/login', [
            'email' => 'admin@nogle.test',
            'password' => 'senha-errada',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertGuest();
    });

    it('usa a mesma mensagem para e-mail inexistente e senha errada', function (): void {
        // Mensagens distintas transformariam o formulário num oráculo de
        // quais e-mails estão cadastrados.
        User::factory()->create(['email' => 'existe@nogle.test', 'password' => 'senha-correta']);

        $senhaErrada = $this->postJson('/api/login', [
            'email' => 'existe@nogle.test',
            'password' => 'errada',
        ])->json('errors.email.0');

        $emailInexistente = $this->postJson('/api/login', [
            'email' => 'naoexiste@nogle.test',
            'password' => 'qualquer',
        ])->json('errors.email.0');

        expect($senhaErrada)->toBe($emailInexistente)
            ->and($senhaErrada)->toBe('As credenciais informadas não conferem.');
    });

    it('exige e-mail e senha', function (): void {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    });

    it('rejeita e-mail em formato inválido', function (): void {
        $this->postJson('/api/login', ['email' => 'nao-e-um-email', 'password' => 'senha123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('não expõe a senha na resposta', function (): void {
        User::factory()->create(['email' => 'admin@nogle.test', 'password' => 'senha123']);

        $corpo = $this->postJson('/api/login', [
            'email' => 'admin@nogle.test',
            'password' => 'senha123',
        ])->json('data');

        expect($corpo)->not->toHaveKey('password')
            ->and($corpo)->not->toHaveKey('remember_token');
    });

    it('troca o identificador da sessão ao autenticar', function (): void {
        // Defesa contra fixação de sessão: um identificador plantado no
        // navegador da vítima antes do login não pode continuar válido
        // depois dele.
        User::factory()->create(['email' => 'admin@nogle.test', 'password' => 'senha123']);

        $this->get('/api/login');
        $antes = session()->getId();

        $this->postJson('/api/login', ['email' => 'admin@nogle.test', 'password' => 'senha123']);

        expect(session()->getId())->not->toBe($antes);
    });
});

describe('limite de tentativas', function (): void {
    it('bloqueia após cinco tentativas malsucedidas', function (): void {
        User::factory()->create(['email' => 'admin@nogle.test', 'password' => 'senha-correta']);

        for ($tentativa = 1; $tentativa <= 5; $tentativa++) {
            $this->postJson('/api/login', [
                'email' => 'admin@nogle.test',
                'password' => 'errada',
            ])->assertUnprocessable();
        }

        $bloqueado = $this->postJson('/api/login', [
            'email' => 'admin@nogle.test',
            'password' => 'errada',
        ]);

        expect($bloqueado->json('errors.email.0'))->toContain('Muitas tentativas');
    });

    it('bloqueia mesmo quando a senha correta é informada depois', function (): void {
        // Caso contrário o limite não protegeria nada: bastaria continuar
        // tentando até acertar.
        User::factory()->create(['email' => 'admin@nogle.test', 'password' => 'senha-correta']);

        for ($tentativa = 1; $tentativa <= 5; $tentativa++) {
            $this->postJson('/api/login', ['email' => 'admin@nogle.test', 'password' => 'errada']);
        }

        $this->postJson('/api/login', [
            'email' => 'admin@nogle.test',
            'password' => 'senha-correta',
        ])->assertUnprocessable();

        $this->assertGuest();
    });

    it('zera a contagem após um login bem-sucedido', function (): void {
        User::factory()->create(['email' => 'admin@nogle.test', 'password' => 'senha-correta']);

        foreach (range(1, 3) as $ignored) {
            $this->postJson('/api/login', ['email' => 'admin@nogle.test', 'password' => 'errada']);
        }

        $this->postJson('/api/login', [
            'email' => 'admin@nogle.test',
            'password' => 'senha-correta',
        ])->assertOk();

        expect(RateLimiter::attempts('admin@nogle.test|127.0.0.1'))->toBe(0);
    });
});

describe('sessão', function (): void {
    it('devolve o usuário autenticado em /api/me', function (): void {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role_label', UserRole::Admin->label());
    });

    it('recusa /api/me sem sessão', function (): void {
        $this->getJson('/api/me')->assertUnauthorized();
    });

    it('encerra a sessão no logout', function (): void {
        User::factory()->create(['email' => 'admin@nogle.test', 'password' => 'senha123']);
        $this->postJson('/api/login', ['email' => 'admin@nogle.test', 'password' => 'senha123'])
            ->assertOk();

        $this->postJson('/api/logout')->assertNoContent();

        forgetResolvedGuards();

        $this->assertGuest();
    });

    it('recusa logout sem sessão', function (): void {
        $this->postJson('/api/logout')->assertUnauthorized();
    });

    it('impede o acesso aos clientes após o logout', function (): void {
        // Faz o login pela rota, e não com `actingAs`, para que exista uma
        // sessão real a ser encerrada — é a sessão, e não o estado em
        // memória, que precisa deixar de autenticar.
        User::factory()->admin()->create(['email' => 'admin@nogle.test', 'password' => 'senha123']);
        $this->postJson('/api/login', ['email' => 'admin@nogle.test', 'password' => 'senha123']);

        $this->getJson('/api/customers')->assertOk();

        $this->postJson('/api/logout')->assertNoContent();

        forgetResolvedGuards();

        $this->getJson('/api/customers')->assertUnauthorized();
        $this->getJson('/api/me')->assertUnauthorized();
    });
});
