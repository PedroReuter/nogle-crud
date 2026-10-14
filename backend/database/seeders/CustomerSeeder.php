<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BrazilianState;
use App\Models\Customer;
use App\Support\Cpf;
use Illuminate\Database\Seeder;

/**
 * Dados fictícios para demonstração.
 *
 * Não usa o factory, de propósito.
 *
 * O factory depende do Faker, que é dependência de desenvolvimento e
 * portanto não existe na imagem Docker, construída com `--no-dev`. Como o
 * ambiente Docker é justamente onde a demonstração acontece, um seeder que
 * dependesse dele falharia exatamente onde precisa funcionar — foi o que
 * aconteceu na primeira subida dos containers.
 *
 * A separação também é correta por si: o factory é infraestrutura de teste,
 * e os dados de demonstração são parte da entrega. Que cada um tenha a sua
 * fonte evita que a imagem de produção carregue uma biblioteca de geração de
 * dados falsos só para semear.
 *
 * Todos os CPFs são gerados pela classe de domínio e todos os nomes vêm das
 * listas abaixo: nenhum dado pessoal real entra no repositório.
 */
final class CustomerSeeder extends Seeder
{
    private const ACTIVE_COUNT = 42;

    private const INACTIVE_COUNT = 8;

    /** @var list<string> */
    private const FIRST_NAMES = [
        'Ana', 'Antônio', 'Beatriz', 'Carlos', 'Cecília', 'Daniel', 'Eduarda',
        'Fábio', 'Gabriela', 'Heitor', 'Inês', 'João', 'Júlia', 'Lucas',
        'Mariana', 'Mateus', 'Natália', 'Otávio', 'Patrícia', 'Rafael',
        'Renata', 'Sérgio', 'Tatiane', 'Vinícius', 'Letícia', 'Rodrigo',
    ];

    /** @var list<string> */
    private const LAST_NAMES = [
        'Almeida', 'Araújo', 'Barbosa', 'Camargo', 'Cardoso', 'Carvalho',
        'Conceição', 'Correia', 'Fernandes', 'Gonçalves', 'Macedo',
        'Magalhães', 'Monteiro', 'Nogueira', 'Oliveira', 'Pereira', 'Queiroz',
        'Ribeiro', 'Rocha', 'Sampaio', 'Siqueira', 'Teixeira', 'Vasconcelos',
    ];

    /**
     * Pares cidade/UF reais, para que a demonstração não exiba combinações
     * impossíveis como "São Paulo/BA".
     *
     * @var list<array{string, string}>
     */
    private const CITIES = [
        ['São Paulo', 'SP'], ['Campinas', 'SP'], ['Santos', 'SP'],
        ['Rio de Janeiro', 'RJ'], ['Niterói', 'RJ'],
        ['Belo Horizonte', 'MG'], ['Uberlândia', 'MG'],
        ['Curitiba', 'PR'], ['Londrina', 'PR'],
        ['Porto Alegre', 'RS'], ['Caxias do Sul', 'RS'],
        ['Florianópolis', 'SC'], ['Joinville', 'SC'],
        ['Salvador', 'BA'], ['Recife', 'PE'], ['Fortaleza', 'CE'],
        ['Goiânia', 'GO'], ['Brasília', 'DF'], ['Manaus', 'AM'],
        ['Belém', 'PA'], ['Vitória', 'ES'], ['Natal', 'RN'],
    ];

    /** @var list<string> */
    private const NEIGHBORHOODS = [
        'Centro', 'Jardim América', 'Vila Nova', 'Bela Vista', 'Santa Cecília',
        'Jardim Primavera', 'Vila Industrial', 'Alto da Boa Vista',
        'São José', 'Parque Residencial',
    ];

    /** @var list<string> */
    private const STREET_TYPES = ['Rua', 'Avenida', 'Travessa', 'Largo', 'Alameda'];

    /** @var list<string> */
    private const COMPLEMENTS = ['Apto 101', 'Apto 42', 'Casa 2', 'Bloco B', 'Sala 304', 'Fundos'];

    /** DDDs existentes, para que o telefone não fique obviamente inventado. */
    private const AREA_CODES = [
        11, 12, 13, 14, 15, 16, 17, 18, 19, 21, 22, 24, 27, 28, 31, 32, 33, 34,
        41, 42, 43, 44, 47, 48, 49, 51, 53, 54, 55, 61, 62, 63, 65, 67, 71, 73,
        81, 85, 91, 92, 98,
    ];

    public function run(): void
    {
        // Volume escolhido para que a paginação padrão de 15 registros renda
        // várias páginas na demonstração — paginação com uma página só não
        // demonstra nada.
        $this->createMany(self::ACTIVE_COUNT, active: true);

        // Inativos existem desde o início para que o filtro de situação e a
        // reativação tenham o que exercitar sem precisar inativar alguém
        // durante a demonstração.
        $this->createMany(self::INACTIVE_COUNT, active: false);

        // Registros com nome e CPF previsíveis, para que o roteiro de
        // demonstração possa buscar um valor conhecido. Os acentos são
        // deliberados: provam que a busca por "jose avila" encontra
        // "José Ávila Gonçalves".
        $this->create('José Ávila Gonçalves', '52998224725');
        $this->create('Maria Conceição Araújo', '11144477735');
    }

    private function createMany(int $quantity, bool $active): void
    {
        for ($i = 0; $i < $quantity; $i++) {
            // Dois sobrenomes distintos: `array_rand` com contagem 2 garante
            // índices diferentes, evitando nomes como "Patrícia Magalhães
            // Magalhães", que denunciam dado gerado.
            [$primeiro, $segundo] = array_rand(self::LAST_NAMES, 2);

            $name = sprintf(
                '%s %s %s',
                self::FIRST_NAMES[array_rand(self::FIRST_NAMES)],
                self::LAST_NAMES[$primeiro],
                self::LAST_NAMES[$segundo],
            );

            $this->create($name, Cpf::random(), $active);
        }
    }

    private function create(string $name, string $cpf, bool $active = true): void
    {
        [$city, $state] = self::CITIES[array_rand(self::CITIES)];

        $customer = new Customer([
            'name' => $name,
            'cpf' => $cpf,
            'phone' => $this->phone(),
            'postal_code' => str_pad((string) random_int(1000000, 99999999), 8, '0', STR_PAD_LEFT),
            'state' => BrazilianState::from($state),
            'city' => $city,
            'neighborhood' => self::NEIGHBORHOODS[array_rand(self::NEIGHBORHOODS)],
            'street' => sprintf(
                '%s %s',
                self::STREET_TYPES[array_rand(self::STREET_TYPES)],
                self::LAST_NAMES[array_rand(self::LAST_NAMES)],
            ),
            'number' => (string) random_int(1, 2500),

            // Cerca de um terço sem complemento, exercitando o único campo
            // opcional do requisito em ambos os estados.
            'complement' => random_int(1, 3) === 1
                ? null
                : self::COMPLEMENTS[array_rand(self::COMPLEMENTS)],
        ]);

        // `is_active` está fora do `$fillable` e por isso é atribuído
        // diretamente, como fazem os endpoints de inativar e reativar.
        $customer->is_active = $active;
        $customer->save();
    }

    /** 70% celulares, 30% fixos. */
    private function phone(): string
    {
        $area = (string) self::AREA_CODES[array_rand(self::AREA_CODES)];

        return random_int(1, 10) <= 7
            ? $area.'9'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT)
            : $area.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    }
}
