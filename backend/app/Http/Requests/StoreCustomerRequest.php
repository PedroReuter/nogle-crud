<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

final class StoreCustomerRequest extends CustomerRequest
{
    protected function cpfUniqueRule(): Unique
    {
        return Rule::unique('customers', 'cpf');
    }
}
