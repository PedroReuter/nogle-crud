<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Cpf;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Valida os dígitos verificadores do CPF.
 *
 * Regra separada, e não um `regex` no Form Request, porque a verificação é
 * aritmética: um regex consegue exigir 11 dígitos, mas não distingue
 * `529.982.247-25` de `529.982.247-26`.
 */
final class ValidCpf implements ValidationRule
{
    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Cpf::isValid($value)) {
            $fail('O CPF informado não é válido.');
        }
    }
}
