<?php

declare(strict_types=1);

use App\Support\Digits;

describe('normalização de dígitos', function (): void {
    it('remove toda pontuação de máscara', function (string $entrada, string $esperado): void {
        expect(Digits::only($entrada))->toBe($esperado);
    })->with([
        ['529.982.247-25', '52998224725'],
        ['(11) 98888-7777', '11988887777'],
        ['01001-000', '01001000'],
        ['  11 3333-4444  ', '1133334444'],
    ]);

    it('preserva zeros à esquerda', function (): void {
        // Um `(int)` em vez desta normalização transformaria o CEP
        // 01001-000 em 1001000 e perderia o zero inicial.
        expect(Digits::only('01001-000'))->toBe('01001000');
    });

    it('devolve nulo quando não há nenhum dígito', function (mixed $entrada): void {
        // Nulo, e não string vazia, para que a regra `required` do
        // validador reprove o valor em vez de aceitar ''.
        expect(Digits::only($entrada))->toBeNull();
    })->with([
        '',
        '   ',
        '---',
        '(  ) -',
        null,
        [[]],
        [true],
    ]);

    it('aceita inteiro, caso o JSON traga o campo sem aspas', function (): void {
        expect(Digits::only(52998224725))->toBe('52998224725');
    });
});
