<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validação e formatação de CPF.
 *
 * A verificação dos dígitos verificadores é a única regra de negócio do
 * backend com peso real, por isso vive numa classe própria, coberta por
 * teste unitário e reutilizada pela regra de validação, pelo factory e
 * pelo seeder.
 */
final class Cpf
{
    public const LENGTH = 11;

    /**
     * Valida um CPF já normalizado ou ainda mascarado.
     *
     * Reprova, nesta ordem: tamanho diferente de 11 dígitos, sequências de
     * dígito repetido (`111.111.111-11` e similares, que satisfazem o
     * cálculo mas não são CPFs atribuíveis) e, por fim, os dois dígitos
     * verificadores.
     */
    public static function isValid(mixed $value): bool
    {
        $cpf = Digits::only($value);

        if ($cpf === null || strlen($cpf) !== self::LENGTH) {
            return false;
        }

        if (preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        return self::checkDigit($cpf, 9) === (int) $cpf[9]
            && self::checkDigit($cpf, 10) === (int) $cpf[10];
    }

    /**
     * Calcula o dígito verificador da posição informada.
     *
     * O peso começa em `$position + 1` e decresce a cada dígito: 10..2 para
     * o primeiro dígito verificador e 11..2 para o segundo.
     */
    private static function checkDigit(string $cpf, int $position): int
    {
        $sum = 0;

        for ($i = 0; $i < $position; $i++) {
            $sum += (int) $cpf[$i] * ($position + 1 - $i);
        }

        $remainder = $sum % self::LENGTH;

        return $remainder < 2 ? 0 : self::LENGTH - $remainder;
    }

    /**
     * Aplica a máscara 000.000.000-00. Usado apenas na saída da API; o
     * banco guarda sempre a forma normalizada.
     */
    public static function format(string $cpf): string
    {
        if (strlen($cpf) !== self::LENGTH) {
            return $cpf;
        }

        return vsprintf('%s%s%s.%s%s%s.%s%s%s-%s%s', str_split($cpf));
    }

    /**
     * Gera um CPF válido aleatório, para factories e seeders.
     *
     * Evita a alternativa de manter uma lista fixa de CPFs no repositório,
     * que limitaria o volume de dados de demonstração.
     */
    public static function random(): string
    {
        do {
            $base = '';
            for ($i = 0; $i < 9; $i++) {
                $base .= (string) random_int(0, 9);
            }

            $cpf = $base.self::checkDigit($base.'00', 9);
            $cpf .= self::checkDigit($cpf.'0', 10);
        } while (! self::isValid($cpf));

        return $cpf;
    }
}
