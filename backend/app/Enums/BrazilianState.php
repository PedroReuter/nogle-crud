<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * As 27 unidades federativas brasileiras.
 *
 * Existe como enum para que a lista válida de UFs tenha uma única fonte:
 * a validação do Form Request, a constraint CHECK da migration e o seeder
 * derivam todos deste mesmo conjunto.
 */
enum BrazilianState: string
{
    case AC = 'AC';
    case AL = 'AL';
    case AP = 'AP';
    case AM = 'AM';
    case BA = 'BA';
    case CE = 'CE';
    case DF = 'DF';
    case ES = 'ES';
    case GO = 'GO';
    case MA = 'MA';
    case MT = 'MT';
    case MS = 'MS';
    case MG = 'MG';
    case PA = 'PA';
    case PB = 'PB';
    case PR = 'PR';
    case PE = 'PE';
    case PI = 'PI';
    case RJ = 'RJ';
    case RN = 'RN';
    case RS = 'RS';
    case RO = 'RO';
    case RR = 'RR';
    case SC = 'SC';
    case SP = 'SP';
    case SE = 'SE';
    case TO = 'TO';

    /** @return list<string> */
    public static function codes(): array
    {
        return array_map(static fn (self $state): string => $state->value, self::cases());
    }
}
