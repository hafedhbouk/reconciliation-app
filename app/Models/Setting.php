<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Paramètre de l'application, modifiable depuis l'écran Administration > Paramètres.
 *
 * Un paramètre est identifié par son groupe et sa clé (ex. groupe 'matching',
 * clé 'tolerance_days'). Les valeurs par défaut viennent de SettingSeeder.
 * SettingsService est prévu pour les lire avec un cache, mais aucun code ne
 * l'utilise encore : ces paramètres sont seulement affichés et modifiables à l'écran.
 * (Les notions fillable, casts et traits sont expliquées dans app/Models/Import.php.)
 */
class Setting extends Model
{
    use Auditable, HasFactory, HasUserstamps, SoftDeletes;

    protected $fillable = [
        'group',
        'key',
        'value',       // stockée en JSON : peut être un nombre, un texte, une liste…
        'type',        // type attendu, pour afficher le bon champ de formulaire
        'description',
        'is_editable', // si faux, le paramètre est en lecture seule dans l'interface
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_editable' => 'boolean',
        ];
    }
}
