<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BrazilianState;
use App\Rules\ValidCpf;
use App\Support\Digits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Regras compartilhadas entre o cadastro e a edição de cliente.
 *
 * As duas operações validam os mesmos campos da mesma forma; a única
 * diferença é a unicidade do CPF, que na edição precisa ignorar o próprio
 * registro. Em vez de duplicar quinze regras em duas classes — e correr o
 * risco de ajustar uma e esquecer a outra — a diferença fica isolada em
 * `cpfUniqueRule()`.
 */
abstract class CustomerRequest extends FormRequest
{
    /**
     * A regra de unicidade do CPF, que difere entre cadastro e edição.
     */
    abstract protected function cpfUniqueRule(): Unique;

    /**
     * Normaliza a entrada antes de validar.
     *
     * A ordem importa: validar antes de normalizar permitiria que
     * `529.982.247-25` e `52998224725` fossem tratados como CPFs distintos
     * pela regra `unique`, deixando a duplicata ser barrada apenas pela
     * constraint do banco — com erro 500 em vez de 422.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['cpf', 'phone', 'postal_code'] as $field) {
            if ($this->has($field)) {
                $normalized[$field] = Digits::only($this->input($field));
            }
        }

        if ($this->has('state') && is_string($this->input('state'))) {
            $normalized['state'] = strtoupper(trim($this->string('state')->value()));
        }

        // Colapsa espaços internos repetidos: "José   da   Silva" grava
        // como "José da Silva", de modo que a busca por "José da" encontre
        // o registro.
        foreach (['name', 'city', 'neighborhood', 'street', 'number', 'complement'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $value = preg_replace('/\s+/u', ' ', trim($this->string($field)->value())) ?? '';
                $normalized[$field] = $field === 'complement' && $value === '' ? null : $value;
            }
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:120'],

            // `size:11` opera sobre o valor já normalizado, então mede
            // dígitos e não caracteres de máscara.
            'cpf' => ['required', 'string', 'size:11', new ValidCpf, $this->cpfUniqueRule()],

            // 10 dígitos para fixo com DDD, 11 para celular com nono
            // dígito. Números internacionais ficam fora do escopo por
            // decisão de projeto, documentada no README: aceitá-los exigiria
            // armazenar o código do país e tornaria a máscara da interface
            // ambígua, sem que o requisito os peça.
            'phone' => ['required', 'string', 'regex:/^[0-9]{10,11}$/'],

            'postal_code' => ['required', 'string', 'size:8'],
            'state' => ['required', Rule::enum(BrazilianState::class)],
            'city' => ['required', 'string', 'min:2', 'max:120'],
            'neighborhood' => ['required', 'string', 'min:2', 'max:120'],
            'street' => ['required', 'string', 'min:3', 'max:180'],
            'number' => ['required', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * Mensagens específicas por campo, como exige o requisito.
     *
     * Só sobrescreve onde a mensagem padrão do Laravel seria vaga para quem
     * está preenchendo o formulário — "o campo cpf deve ter 11 caracteres"
     * não comunica que o esperado são dígitos sem pontuação.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cpf.size' => 'O CPF deve conter 11 dígitos.',
            'cpf.unique' => 'Já existe um cliente cadastrado com este CPF.',
            'phone.regex' => 'Informe um telefone válido com DDD, contendo 10 ou 11 dígitos.',
            'postal_code.size' => 'O CEP deve conter 8 dígitos.',
            'state.enum' => 'Selecione uma unidade federativa válida.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'cpf' => 'CPF',
            'phone' => 'telefone',
            'postal_code' => 'CEP',
            'state' => 'estado',
            'city' => 'cidade',
            'neighborhood' => 'bairro',
            'street' => 'endereço',
            'number' => 'número',
            'complement' => 'complemento',
        ];
    }
}
