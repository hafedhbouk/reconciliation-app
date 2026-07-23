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

    public function badgeClass(): string
    {
        return match ($this) {
            self::Matched => 'bg-success',
            self::Partial => 'bg-warning text-dark',
            self::Conflict => 'bg-danger',
            self::Rejected => 'bg-secondary',
        };
    }
}
