<?php

namespace App\Enums;

enum MatchingStatus: string
{
    case Unmatched = 'unmatched';
    case Matched = 'matched';
    case Partial = 'partial';
    case Conflict = 'conflict';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::Unmatched => 'Non rapproché',
            self::Matched => 'Rapproché',
            self::Partial => 'Partiel',
            self::Conflict => 'Conflit',
            self::Ignored => 'Ignoré',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Unmatched => 'bg-secondary',
            self::Matched => 'bg-success',
            self::Partial => 'bg-warning text-dark',
            self::Conflict => 'bg-danger',
            self::Ignored => 'bg-light text-dark',
        };
    }
}
