<?php

namespace App\Enums;

enum MatchingResultStatus: string
{
    case Matched = 'matched';
    case Partial = 'partial';
    case Conflict = 'conflict';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Matched => 'Rapproché',
            self::Partial => 'Partiel',
            self::Conflict => 'Conflit',
            self::Rejected => 'Rejeté',
        };
    }
}
