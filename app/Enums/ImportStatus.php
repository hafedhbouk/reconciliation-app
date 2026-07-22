<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case PartiallyCompleted = 'partially_completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Processing => 'En cours',
            self::Completed => 'Terminé',
            self::Failed => 'Échoué',
            self::PartiallyCompleted => 'Partiellement terminé',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-secondary',
            self::Processing => 'bg-info text-dark',
            self::Completed => 'bg-success',
            self::Failed => 'bg-danger',
            self::PartiallyCompleted => 'bg-warning text-dark',
        };
    }
}
