<?php

namespace App\Enums;

enum ImportRowStatus: string
{
    case Pending = 'pending';
    case Transformed = 'transformed';
    case Normalized = 'normalized';
    case Imported = 'imported';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Transformed => 'Transformé',
            self::Normalized => 'Normalisé',
            self::Imported => 'Importé',
            self::Error => 'Erreur',
        };
    }
}
