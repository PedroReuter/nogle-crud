<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

final class UpdateCustomerRequest extends CustomerRequest
{
    /**
     * Ignora o próprio registro na checagem de unicidade.
     *
     * Atende ao requisito de "permitir que um cliente mantenha seu próprio
     * CPF durante a edição": sem o `ignore`, salvar o formulário sem mexer
     * no CPF acusaria duplicata contra o próprio cliente.
     */
    protected function cpfUniqueRule(): Unique
    {
        /** @var Customer $customer */
        $customer = $this->route('customer');

        return Rule::unique('customers', 'cpf')->ignore($customer->id);
    }
}
