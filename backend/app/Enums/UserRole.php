<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Perfis de acesso previstos no desafio.
 *
 * Dois perfis fixos em enum, e não uma tabela de papéis e permissões: o
 * requisito define exatamente dois, e um modelo relacional de ACL aqui
 * seria estrutura sem demanda. Se surgir um terceiro perfil, acrescentar
 * um case é barato; se surgirem permissões granulares, a migração para
 * tabela é um passo localizado, concentrado na Policy.
 */
enum UserRole: string
{
    /** Pode consultar, cadastrar, editar, inativar e reativar clientes. */
    case Admin = 'admin';

    /** Pode apenas consultar e filtrar clientes. */
    case Viewer = 'viewer';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Viewer => 'Leitura',
        };
    }

    /** Se o perfil pode alterar o cadastro de clientes. */
    public function canManageCustomers(): bool
    {
        return $this === self::Admin;
    }
}
