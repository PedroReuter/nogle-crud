<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida os parâmetros de filtro, ordenação e paginação da listagem.
 *
 * Um Form Request para uma requisição `GET` pode parecer excessivo, mas é
 * o que impede três problemas concretos: `per_page=1000000` como vetor de
 * exaustão de memória, `sort=password` expondo ordenação por coluna
 * arbitrária, e `page=-1` produzindo SQL com `OFFSET` negativo.
 */
final class IndexCustomerRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'cpf' => ['nullable', 'string', 'max:14'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
            'sort' => ['nullable', Rule::in(Customer::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sort.in' => 'Ordenação não permitida. Campos válidos: '.implode(', ', Customer::SORTABLE).'.',
            'per_page.max' => 'O máximo de registros por página é '.self::MAX_PER_PAGE.'.',
        ];
    }

    /** Filtros, já com os valores padrão aplicados. */
    public function filters(): CustomerListFilters
    {
        return new CustomerListFilters(
            name: $this->string('name')->value() ?: null,
            cpf: $this->string('cpf')->value() ?: null,
            status: $this->string('status')->value() ?: 'active',
            sort: $this->string('sort')->value() ?: 'name',
            direction: $this->string('direction')->value() ?: 'asc',
            perPage: $this->integer('per_page') ?: self::DEFAULT_PER_PAGE,
        );
    }
}
