<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Os parâmetros da listagem, já validados e com padrões aplicados.
 *
 * Objeto em vez de array para que o controller e o escopo do model recebam
 * valores nomeados e tipados: um array solto permitiria errar a chave
 * silenciosamente e deixaria o padrão de cada campo espalhado pelo código.
 */
final readonly class CustomerListFilters
{
    public function __construct(
        public ?string $name,
        public ?string $cpf,
        public string $status,
        public string $sort,
        public string $direction,
        public int $perPage,
    ) {}
}
