<?php

namespace App\Models;

/**
 * Mapping entre une colonne du fichier source et un champ cible de l'application.
 *
 * Chaque enregistrement définit : la colonne source (ex: "Réf"), le champ
 * cible (ex: "reference"), la série de transforms à appliquer (trim,
 * zero_pad, etc.) et si la colonne est obligatoire. L'ordre (sort_order)
 * détermine la séquence d'application des transforms dans le MappingEngine.
 *
 * Exemple réel (BNA) :
 *   source_column = 'N° autorisation', target_field = 'num_autorisation',
 *   transform = [{"key": "trim"}, {"key": "zero_pad", "config": {"length": 6}}]
 * Les valeurs par défaut sont créées par database/seeders/SourceColumnMappingSeeder.php
 * et modifiables dans l'écran « Mapping » d'une source.
 *
 * (Les notions fillable, casts, relations et traits sont expliquées en
 * détail dans app/Models/Import.php.)
 */
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SourceColumnMapping extends Model
{
    use Auditable, HasFactory, HasUserstamps, SoftDeletes;

    protected $fillable = [
        'source_id',
        'target_field',   // champ interne (valeur de App\Enums\MappingTargetField)
        'source_column',  // nom exact de la colonne dans le fichier
        'transform',      // liste ordonnée des transformations à appliquer
        'is_required',    // si vrai, une ligne sans cette valeur est rejetée
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'transform' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
