<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Eventos registrados na trilha de auditoria.
 */
enum AuditEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deactivated = 'deactivated';
    case Reactivated = 'reactivated';

    /** Eliminação definitiva, em atendimento ao direito do titular. */
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Cadastrado',
            self::Updated => 'Alterado',
            self::Deactivated => 'Inativado',
            self::Reactivated => 'Reativado',
            self::Deleted => 'Eliminado definitivamente',
        };
    }
}
