<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BrazilianState;
use App\Models\Customer;
use App\Support\Cpf;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
final class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * Pares cidade/UF reais.
     *
     * O `city()` e o `stateAbbr()` do Faker são sorteados de forma
     * independente e produziriam combinações como "São Paulo/BA", que num
     * CRUD de endereços chama atenção na demonstração. Uma lista curada
     * custa pouco e dá dados coerentes.
     *
     * @var list<array{string, string}>
     */
    private const CITIES = [
        ['São Paulo', 'SP'],
        ['Campinas', 'SP'],
        ['Rio de Janeiro', 'RJ'],
        ['Niterói', 'RJ'],
        ['Belo Horizonte', 'MG'],
        ['Uberlândia', 'MG'],
        ['Curitiba', 'PR'],
        ['Londrina', 'PR'],
        ['Porto Alegre', 'RS'],
        ['Florianópolis', 'SC'],
        ['Joinville', 'SC'],
        ['Salvador', 'BA'],
        ['Recife', 'PE'],
        ['Fortaleza', 'CE'],
        ['Goiânia', 'GO'],
        ['Brasília', 'DF'],
        ['Manaus', 'AM'],
        ['Belém', 'PA'],
        ['Vitória', 'ES'],
        ['Natal', 'RN'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $faker = fake('pt_BR');
        [$city, $state] = $this->faker->randomElement(self::CITIES);

        return [
            'name' => $faker->name(),

            // CPF gerado pela própria classe de domínio, e não pelo Faker,
            // para que todo dado de teste satisfaça exatamente o validador
            // que a aplicação usa.
            'cpf' => Cpf::random(),

            'phone' => $this->faker->boolean(70)
                ? $this->areaCode().'9'.$this->faker->numerify('########')   // celular
                : $this->areaCode().$this->faker->numerify('########'),      // fixo

            'postal_code' => $this->faker->numerify('########'),
            'state' => BrazilianState::from($state),
            'city' => $city,
            // Nomes genéricos, que existem em praticamente toda cidade
            // brasileira. Nomes de bairro reais como "Copacabana" ou
            // "Savassi" produziriam combinações visivelmente erradas, do
            // tipo "Moinhos de Vento, Natal/RN".
            'neighborhood' => $faker->randomElement([
                'Centro', 'Jardim América', 'Vila Nova', 'Bela Vista', 'Santa Cecília',
                'Jardim Primavera', 'Vila Industrial', 'Alto da Boa Vista',
                'São José', 'Parque Residencial',
            ]),
            'street' => $faker->streetName(),
            'number' => (string) $this->faker->numberBetween(1, 2500),

            // Cerca de um terço sem complemento, exercitando o único campo
            // opcional do requisito em ambos os estados.
            'complement' => $this->faker->boolean(65)
                ? $this->faker->randomElement(['Apto 101', 'Apto 42', 'Casa 2', 'Bloco B', 'Sala 304', 'Fundos'])
                : null,

            'is_active' => true,
        ];
    }

    /** Cliente inativo, para testar listagem e reativação. */
    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /** CPF determinado, para testes que precisam de um valor conhecido. */
    public function withCpf(string $cpf): self
    {
        return $this->state(fn (): array => ['cpf' => preg_replace('/\D/', '', $cpf)]);
    }

    /** Nome determinado, para testes de filtro. */
    public function named(string $name): self
    {
        return $this->state(fn (): array => ['name' => $name]);
    }

    /** DDDs válidos — o Faker gera combinações inexistentes como 10 ou 19x. */
    private function areaCode(): string
    {
        return (string) $this->faker->randomElement([
            11, 12, 13, 14, 15, 16, 17, 18, 19, 21, 22, 24, 27, 28, 31, 32, 33, 34,
            41, 42, 43, 44, 47, 48, 49, 51, 53, 54, 55, 61, 62, 63, 65, 67, 71, 73,
            81, 85, 91, 92, 98,
        ]);
    }
}
