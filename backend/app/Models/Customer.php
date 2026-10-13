<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BrazilianState;
use App\Policies\CustomerPolicy;
use App\Support\Digits;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $cpf 11 dígitos, sem pontuação
 * @property string $phone 10 ou 11 dígitos, sem pontuação
 * @property string $postal_code 8 dígitos, sem pontuação
 * @property BrazilianState $state
 * @property string $city
 * @property string $neighborhood
 * @property string $street
 * @property string $number
 * @property string|null $complement
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(CustomerFactory::class)]
#[UsePolicy(CustomerPolicy::class)]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * Lista explícita de atributos atribuíveis em massa.
     *
     * `is_active` fica deliberadamente de fora: a situação do cliente muda
     * apenas pelos endpoints de inativar e reativar, que passam pela
     * autorização correspondente. Se estivesse aqui, um `PUT` com
     * `is_active: true` reativaria um cliente sem passar por essa
     * verificação. O mesmo vale para `id`, `created_at` e `updated_at`.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'cpf',
        'phone',
        'postal_code',
        'state',
        'city',
        'neighborhood',
        'street',
        'number',
        'complement',
    ];

    /**
     * Valores padrão da instância recém-criada.
     *
     * A migration já define `is_active` como `true` no banco, mas esse
     * default só se aplica ao `INSERT` — a instância devolvida por
     * `Customer::create()` não o conhece, e o Resource serializaria
     * `is_active: null` na resposta do cadastro. Declarar aqui mantém a
     * instância em memória coerente com a linha gravada, sem a consulta
     * extra que um `refresh()` custaria.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Ordenações permitidas na listagem.
     *
     * Existe como lista fechada porque aceitar o nome da coluna direto do
     * parâmetro da requisição permitiria ordenar por qualquer coluna e
     * inferir dados por meio da ordem — e, pior, exporia a cláusula
     * `ORDER BY` à entrada do usuário.
     *
     * @var list<string>
     */
    public const SORTABLE = ['name', 'cpf', 'created_at', 'city', 'state'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => BrazilianState::class,
            'is_active' => 'boolean',
        ];
    }

    /** Endereço completo, para a coluna única exigida na listagem. */
    public function fullAddress(): string
    {
        $line = "{$this->street}, {$this->number}";

        if ($this->complement !== null && trim($this->complement) !== '') {
            $line .= " - {$this->complement}";
        }

        return sprintf(
            '%s - %s, %s/%s - CEP %s',
            $line,
            $this->neighborhood,
            $this->city,
            $this->state->value,
            substr($this->postal_code, 0, 5).'-'.substr($this->postal_code, 5),
        );
    }

    /**
     * Busca por nome, insensível a caixa e a acentuação.
     *
     * A expressão precisa ser idêntica à do índice criado em
     * `2026_10_13_000001_enable_text_search_support` — qualquer divergência
     * faria o PostgreSQL ignorar o índice e varrer a tabela. Os caracteres
     * especiais de `LIKE` são escapados para que um termo com `%` busque o
     * literal em vez de casar com tudo.
     *
     * @param  Builder<Customer>  $query
     */
    #[Scope]
    protected function whereNameMatches(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->whereRaw(
            'immutable_unaccent(lower(name)) LIKE immutable_unaccent(lower(?))',
            ['%'.self::escapeLike($term).'%'],
        );
    }

    /**
     * Busca por CPF, por prefixo dos dígitos informados.
     *
     * O termo é normalizado da mesma forma que na persistência, então o
     * usuário pode digitar com ou sem máscara e encontrar o registro.
     *
     * @param  Builder<Customer>  $query
     */
    #[Scope]
    protected function whereCpfStartsWith(Builder $query, ?string $term): void
    {
        $digits = Digits::only($term);

        if ($digits === null) {
            return;
        }

        $query->where('cpf', 'like', $digits.'%');
    }

    /**
     * Filtro de situação. `all` existe para o requisito de visualizar
     * inativos sem precisar de um segundo endpoint.
     *
     * @param  Builder<Customer>  $query
     */
    #[Scope]
    protected function whereStatus(Builder $query, string $status): void
    {
        match ($status) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => null,
        };
    }

    /**
     * Ordenação previsível.
     *
     * A coluna é validada contra `SORTABLE` antes de chegar aqui; a
     * verificação é repetida porque o escopo é público e não deve confiar
     * em quem o chama. O desempate por `id` garante que duas páginas
     * consecutivas não repitam nem omitam registros quando há valores
     * iguais na coluna ordenada.
     *
     * @param  Builder<Customer>  $query
     */
    #[Scope]
    protected function sortedBy(Builder $query, string $column, string $direction): void
    {
        $column = in_array($column, self::SORTABLE, true) ? $column : 'name';
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        $query->orderBy($column, $direction)->orderBy('id');
    }

    /** Escapa os curingas de `LIKE` para que sejam buscados literalmente. */
    private static function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term);
    }
}
