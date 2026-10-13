<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalização de campos que o usuário digita com máscara, mas que
 * persistimos apenas como dígitos (CPF, telefone e CEP).
 *
 * Centralizado para que a normalização feita antes da validação seja
 * exatamente a mesma feita antes da persistência — divergência entre as
 * duas é a origem clássica de duplicata de CPF que passa pela validação
 * e só estoura na constraint do banco.
 */
final class Digits
{
    /**
     * Remove tudo que não for dígito. Devolve null para entrada nula ou
     * sem nenhum dígito, de modo que `required` continue reprovando
     * valores como "---" ou "   ".
     */
    public static function only(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        return $digits === '' ? null : $digits;
    }
}
