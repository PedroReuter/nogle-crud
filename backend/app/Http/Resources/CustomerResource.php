<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Customer;
use App\Support\Cpf;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
final class CustomerResource extends JsonResource
{
    /**
     * Contrato de saída da API.
     *
     * Devolve cada campo em duas formas quando elas diferem: o valor
     * normalizado, que a interface reenvia ao salvar, e o valor formatado,
     * que ela exibe. Isso evita que o frontend tenha de reimplementar a
     * máscara de CPF e de CEP, e evita o oposto — devolver só o valor
     * formatado e obrigar o frontend a desmascarar antes de reenviar.
     *
     * A lista é explícita, e não um `$this->resource->toArray()`: assim uma
     * coluna acrescentada ao banco no futuro não passa a ser exposta pela
     * API sem decisão.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,

            'cpf' => $this->cpf,
            'cpf_formatted' => Cpf::format($this->cpf),

            'phone' => $this->phone,
            'phone_formatted' => $this->formatPhone($this->phone),

            'postal_code' => $this->postal_code,
            'postal_code_formatted' => substr($this->postal_code, 0, 5).'-'.substr($this->postal_code, 5),

            'street' => $this->street,
            'number' => $this->number,
            'complement' => $this->complement,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state->value,

            // Exigência da listagem: endereço completo em uma única coluna.
            // Montado no backend para que a interface não reimplemente a
            // concatenação e as duas visões não divirjam.
            'full_address' => $this->fullAddress(),

            'is_active' => $this->is_active,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** (00) 00000-0000 para celular, (00) 0000-0000 para fixo. */
    private function formatPhone(string $phone): string
    {
        $area = substr($phone, 0, 2);
        $rest = substr($phone, 2);

        return strlen($rest) === 9
            ? sprintf('(%s) %s-%s', $area, substr($rest, 0, 5), substr($rest, 5))
            : sprintf('(%s) %s-%s', $area, substr($rest, 0, 4), substr($rest, 4));
    }
}
