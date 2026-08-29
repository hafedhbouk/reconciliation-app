<?php

namespace App\Models;

/**
 * Mapping entre une colonne du fichier source et un champ cible de l'application.
 *
 * Chaque enregistrement définit : la colonne source (ex: "Réf"), le champ
 * cible (ex: "reference"), la série de transforms à appliquer (trim,
 * zero_pad, etc.) et si la colonne est obligatoire. L'ordre (sort_order)
 * détermine la séquence d'application des transforms dans le MappingEngine.
 */
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SourceColumnMapping extends Model
{
    use HasFactory, SoftDeletes, HasUserstamps, Auditable;

    protected $fillable = [
        'source_id',
        'target_field',
        'source_column',
        'transform',
        'is_required',
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
