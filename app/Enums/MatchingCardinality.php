<?php

namespace App\Enums;

enum MatchingCardinality: string
{
    case OneToOne = '1:1';
    case OneToMany = '1:N';
    case ManyToOne = 'N:1';
    case ManyToMany = 'N:M';

    public function label(): string
    {
        return match ($this) {
            self::OneToOne => 'Un à un',
            self::OneToMany => 'Un à plusieurs',
            self::ManyToOne => 'Plusieurs à un',
            self::ManyToMany => 'Plusieurs à plusieurs',
        };
    }
}
