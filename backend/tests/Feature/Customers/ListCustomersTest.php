<?php

declare(strict_types=1);

use App\Models\Customer;

describe('listagem de clientes', function (): void {
    it('lista os clientes ativos', function (): void {
        Customer::factory()->count(3)->create();

        $this->getJson('/api/customers')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'name', 'cpf_formatted', 'phone_formatted', 'full_address', 'is_active']],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
            ]);
    });

    it('devolve lista vazia sem erro quando não há clientes', function (): void {
        $this->getJson('/api/customers')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    });

    it('oculta clientes inativos por padrão', function (): void {
        Customer::factory()->count(2)->create();
        Customer::factory()->count(3)->inactive()->create();

        $this->getJson('/api/customers')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    });

    it('lista apenas inativos quando solicitado', function (): void {
        Customer::factory()->count(2)->create();
        Customer::factory()->count(3)->inactive()->create();

        $response = $this->getJson('/api/customers?status=inactive')->assertOk();

        expect($response->json('meta.total'))->toBe(3)
            ->and(array_column($response->json('data'), 'is_active'))->each->toBeFalse();
    });

    it('lista ativos e inativos juntos quando solicitado', function (): void {
        Customer::factory()->count(2)->create();
        Customer::factory()->count(3)->inactive()->create();

        $this->getJson('/api/customers?status=all')
            ->assertOk()
            ->assertJsonPath('meta.total', 5);
    });
});

describe('filtro por nome', function (): void {
    it('filtra por parte do nome', function (): void {
        Customer::factory()->named('Joana Pereira')->create();
        Customer::factory()->named('Carlos Mendes')->create();

        $response = $this->getJson('/api/customers?name=Pereira')->assertOk();

        expect($response->json('meta.total'))->toBe(1)
            ->and($response->json('data.0.name'))->toBe('Joana Pereira');
    });

    it('ignora a diferença entre maiúsculas e minúsculas', function (string $termo): void {
        Customer::factory()->named('Joana Pereira')->create();

        expect($this->getJson("/api/customers?name={$termo}")->json('meta.total'))->toBe(1);
    })->with(['joana', 'JOANA', 'JoAnA', 'pereira', 'PEREIRA']);

    it('ignora acentuação, nos dois sentidos', function (string $termo): void {
        // É o requisito de "tratamento consistente de acentuação": quem
        // digita sem acento precisa achar o registro acentuado, e
        // vice-versa.
        Customer::factory()->named('José Ávila Gonçalves')->create();

        expect($this->getJson("/api/customers?name={$termo}")->json('meta.total'))->toBe(1);
    })->with([
        'sem acento' => 'jose',
        'com acento' => 'josé',
        'sem acento no sobrenome' => 'avila',
        'com acento no sobrenome' => 'Ávila',
        'cedilha ausente' => 'goncalves',
        'cedilha presente' => 'gonçalves',
        'nome composto sem acento' => 'jose avila',
    ]);

    it('não retorna nada quando o termo não casa', function (): void {
        Customer::factory()->named('Joana Pereira')->create();

        $this->getJson('/api/customers?name=Inexistente')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('trata o curinga do LIKE como texto literal', function (): void {
        // Sem escapar, `%` no termo casaria com todos os registros e a
        // busca por "100%" devolveria a tabela inteira.
        Customer::factory()->named('Joana Pereira')->create();
        Customer::factory()->named('Carlos Mendes')->create();

        $this->getJson('/api/customers?name=%25')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('ignora filtro de nome composto apenas por espaços', function (): void {
        Customer::factory()->count(2)->create();

        $this->getJson('/api/customers?name=%20%20')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    });
});

describe('filtro por CPF', function (): void {
    it('filtra pelos dígitos iniciais do CPF', function (): void {
        Customer::factory()->withCpf('52998224725')->create();
        Customer::factory()->withCpf('11144477735')->create();

        $response = $this->getJson('/api/customers?cpf=529')->assertOk();

        expect($response->json('meta.total'))->toBe(1)
            ->and($response->json('data.0.cpf'))->toBe('52998224725');
    });

    it('aceita o CPF com máscara', function (string $termo): void {
        // A normalização do filtro usa a mesma função da persistência, então
        // o usuário pode colar o valor mascarado direto do formulário.
        Customer::factory()->withCpf('52998224725')->create();
        Customer::factory()->withCpf('11144477735')->create();

        expect($this->getJson("/api/customers?cpf={$termo}")->json('meta.total'))->toBe(1);
    })->with([
        'completo com máscara' => '529.982.247-25',
        'completo sem máscara' => '52998224725',
        'prefixo com ponto' => '529.982',
        'prefixo sem pontuação' => '529982',
    ]);

    it('não retorna nada para CPF inexistente', function (): void {
        Customer::factory()->withCpf('52998224725')->create();

        $this->getJson('/api/customers?cpf=999')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });
});

describe('filtros combinados', function (): void {
    it('aplica nome e CPF ao mesmo tempo', function (): void {
        $alvo = Customer::factory()->named('Joana Pereira')->withCpf('52998224725')->create();
        Customer::factory()->named('Joana Souza')->withCpf('11144477735')->create();
        Customer::factory()->named('Carlos Pereira')->withCpf('76670069030')->create();

        $response = $this->getJson('/api/customers?name=Joana&cpf=529')->assertOk();

        expect($response->json('meta.total'))->toBe(1)
            ->and($response->json('data.0.id'))->toBe($alvo->id);
    });

    it('devolve vazio quando os dois filtros não casam no mesmo registro', function (): void {
        // Confirma que os filtros são combinados com E, e não com OU.
        Customer::factory()->named('Joana Pereira')->withCpf('52998224725')->create();
        Customer::factory()->named('Carlos Mendes')->withCpf('11144477735')->create();

        $this->getJson('/api/customers?name=Joana&cpf=111')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('combina filtros com a situação do cliente', function (): void {
        Customer::factory()->named('Joana Pereira')->create();
        Customer::factory()->named('Joana Pereira Inativa')->inactive()->create();

        expect($this->getJson('/api/customers?name=Joana')->json('meta.total'))->toBe(1)
            ->and($this->getJson('/api/customers?name=Joana&status=all')->json('meta.total'))->toBe(2)
            ->and($this->getJson('/api/customers?name=Joana&status=inactive')->json('meta.total'))->toBe(1);
    });
});

describe('paginação', function (): void {
    it('pagina com 15 registros por página por padrão', function (): void {
        Customer::factory()->count(20)->create();

        $this->getJson('/api/customers')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.last_page', 2);
    });

    it('respeita o tamanho de página solicitado', function (): void {
        Customer::factory()->count(20)->create();

        $this->getJson('/api/customers?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.last_page', 4);
    });

    it('devolve a página solicitada', function (): void {
        Customer::factory()->count(20)->create();

        $this->getJson('/api/customers?page=2&per_page=5')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.from', 6)
            ->assertJsonPath('meta.to', 10);
    });

    it('não repete nem omite registros entre páginas', function (): void {
        // O desempate por id na ordenação existe para isto: sem ele, dois
        // clientes com o mesmo nome poderiam aparecer nas duas páginas ou
        // em nenhuma, porque a ordem de linhas iguais não é determinística
        // no PostgreSQL.
        Customer::factory()->count(5)->named('Nome Repetido')->create();

        $pagina1 = $this->getJson('/api/customers?per_page=2&page=1')->json('data.*.id');
        $pagina2 = $this->getJson('/api/customers?per_page=2&page=2')->json('data.*.id');
        $pagina3 = $this->getJson('/api/customers?per_page=2&page=3')->json('data.*.id');

        $todos = [...$pagina1, ...$pagina2, ...$pagina3];

        expect($todos)->toHaveCount(5)
            ->and(array_unique($todos))->toHaveCount(5);
    });

    it('preserva os filtros nos links de paginação', function (): void {
        Customer::factory()->count(20)->named('Joana Pereira')->create();

        $next = $this->getJson('/api/customers?name=Joana&per_page=5')->json('links.next');

        expect($next)->toContain('name=Joana')->toContain('per_page=5');
    });

    it('rejeita tamanho de página acima do limite', function (): void {
        // Impede que `per_page=1000000` carregue a tabela inteira em memória.
        $this->getJson('/api/customers?per_page=1000000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    });

    it('rejeita parâmetros de paginação inválidos', function (string $query, string $campo): void {
        $this->getJson("/api/customers?{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$campo]);
    })->with([
        ['per_page=0', 'per_page'],
        ['per_page=-5', 'per_page'],
        ['per_page=abc', 'per_page'],
        ['page=0', 'page'],
        ['page=-1', 'page'],
    ]);
});

describe('ordenação', function (): void {
    it('ordena por nome em ordem crescente por padrão', function (): void {
        Customer::factory()->named('Carlos Mendes')->create();
        Customer::factory()->named('Ana Ferreira')->create();
        Customer::factory()->named('Bruno Lima')->create();

        $nomes = $this->getJson('/api/customers')->json('data.*.name');

        expect($nomes)->toBe(['Ana Ferreira', 'Bruno Lima', 'Carlos Mendes']);
    });

    it('ordena de forma decrescente quando solicitado', function (): void {
        Customer::factory()->named('Ana Ferreira')->create();
        Customer::factory()->named('Carlos Mendes')->create();

        $nomes = $this->getJson('/api/customers?sort=name&direction=desc')->json('data.*.name');

        expect($nomes)->toBe(['Carlos Mendes', 'Ana Ferreira']);
    });

    it('ordena pelas demais colunas permitidas', function (string $coluna): void {
        Customer::factory()->count(3)->create();

        $this->getJson("/api/customers?sort={$coluna}")->assertOk();
    })->with(['name', 'cpf', 'created_at', 'city', 'state']);

    it('rejeita ordenação por coluna não autorizada', function (string $coluna): void {
        // Impede que o parâmetro da requisição chegue à cláusula ORDER BY.
        $this->getJson("/api/customers?sort={$coluna}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort']);
    })->with(['id', 'is_active', 'password', 'customers.id', 'name; drop table customers']);

    it('rejeita direção de ordenação inválida', function (): void {
        $this->getJson('/api/customers?direction=sideways')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['direction']);
    });

    it('rejeita situação inválida no filtro', function (): void {
        $this->getJson('/api/customers?status=qualquer')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });
});

describe('desempenho da listagem', function (): void {
    it('não executa consulta por registro listado', function (): void {
        // Guarda contra N+1: a listagem deve custar o mesmo número de
        // consultas independentemente da quantidade de registros. Se alguém
        // acrescentar um acesso a relação dentro do Resource, este teste
        // falha.
        Customer::factory()->count(3)->create();

        $consultas = [];
        DB::listen(function ($q) use (&$consultas): void {
            // Conta apenas o que toca a tabela de clientes. As consultas de
            // sessão e de usuário autenticado variam entre a primeira
            // requisição e as seguintes, por causa do cache do guard, e
            // poluiriam a comparação sem nada dizer sobre N+1.
            if (str_contains($q->sql, 'customers')) {
                $consultas[] = $q->sql;
            }
        });

        $this->getJson('/api/customers?per_page=3')->assertOk();
        $comTres = count($consultas);

        Customer::factory()->count(27)->create();
        $consultas = [];
        $this->getJson('/api/customers?per_page=30')->assertOk();
        $comTrinta = count($consultas);

        // Dez vezes mais registros não pode custar nenhuma consulta a mais.
        expect($comTrinta)->toBe($comTres)
            ->and($comTres)->toBeLessThanOrEqual(2); // a página e a contagem total
    });
});
