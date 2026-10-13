<?php

declare(strict_types=1);

use App\Support\Cpf;

describe('validação de CPF', function (): void {
    it('aceita CPF válido com e sem máscara', function (string $cpf): void {
        expect(Cpf::isValid($cpf))->toBeTrue();
    })->with([
        '529.982.247-25',
        '52998224725',
        '111.444.777-35',
        '11144477735',
        '012.953.109-02',   // inicia com zero
        '01295310902',
    ]);

    it('rejeita CPF com dígito verificador incorreto', function (): void {
        // Mesmo CPF válido de referência, apenas com o último dígito
        // alterado: isola a verificação aritmética de qualquer outra regra.
        expect(Cpf::isValid('529.982.247-26'))->toBeFalse();
    });

    it('rejeita sequências de dígito repetido', function (string $cpf): void {
        // Estes satisfazem o cálculo dos dígitos verificadores, mas não são
        // CPFs atribuíveis. Sem a verificação explícita, passariam.
        expect(Cpf::isValid($cpf))->toBeFalse();
    })->with([
        '111.111.111-11',
        '000.000.000-00',
        '222.222.222-22',
        '999.999.999-99',
    ]);

    it('rejeita quantidade de dígitos diferente de 11', function (string $cpf): void {
        expect(Cpf::isValid($cpf))->toBeFalse();
    })->with(['1234567890', '123456789012', '1', '']);

    it('rejeita entrada que não contém dígitos', function (mixed $value): void {
        expect(Cpf::isValid($value))->toBeFalse();
    })->with([
        'abcdefghijk',
        '   ',
        '...-',
        null,
        [[]],
    ]);
});

describe('formatação de CPF', function (): void {
    it('aplica a máscara 000.000.000-00', function (): void {
        expect(Cpf::format('52998224725'))->toBe('529.982.247-25');
    });

    it('preserva zeros à esquerda', function (): void {
        expect(Cpf::format('01295310902'))->toBe('012.953.109-02');
    });

    it('devolve a entrada inalterada quando o tamanho é inesperado', function (): void {
        // Não deve lançar exceção: o Resource formata o que vem do banco, e
        // falhar aqui transformaria dado inconsistente em erro 500 na
        // listagem inteira em vez de um valor exibido sem máscara.
        expect(Cpf::format('123'))->toBe('123');
    });
});

describe('geração de CPF', function (): void {
    it('gera somente CPFs que a própria validação aceita', function (): void {
        // 500 iterações: a geração depende do cálculo dos dígitos
        // verificadores, e um erro de peso só apareceria em parte dos casos.
        $gerados = array_map(fn (): string => Cpf::random(), range(1, 500));

        // Filtrar os inválidos, em vez de afirmar sobre cada um, faz a
        // mensagem de falha listar exatamente quais CPFs quebraram.
        $invalidos = array_values(array_filter(
            $gerados,
            fn (string $cpf): bool => ! Cpf::isValid($cpf),
        ));

        expect($invalidos)->toBeEmpty();
    });

    it('gera 11 dígitos', function (): void {
        expect(Cpf::random())->toHaveLength(11)->toMatch('/^\d{11}$/');
    });
});
