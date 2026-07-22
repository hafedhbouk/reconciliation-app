<?php

namespace App\Enums;

enum ExceptionStatus: string
{
    case Open = 'open';
    case InReview = 'in_review';
    case Resolved = 'resolved';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Ouvert',
            self::InReview => 'En cours de traitement',
            self::Resolved => 'Résolu',
            self::Ignored => 'Ignoré',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Open => 'bg-danger',
            self::InReview => 'bg-warning text-dark',
            self::Resolved => 'bg-success',
            self::Ignored => 'bg-secondary',
        };
    }
}
