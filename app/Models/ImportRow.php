<?php

namespace App\Models;

use App\Enums\ImportRowStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Une ligne d'un fichier importé, gardée à chacune des 3 étapes du traitement.
 *
 * C'est ce que montre la page « détail d'un import » (/admin/imports/{id}) :
 * - raw_data         : « Données brutes », la ligne exactement comme dans le fichier
 *                      ex. {"Date": "15/05/2026", "Montant (TND)": "253.000", ...}
 * - transformed_data : « Données transformées », après mapping et transformations
 *                      ex. {"date": "2026-05-15", "amount": 253000, ...}
 * - normalized_data  : « Données normalisées », les champs utilisés par le rapprochement
 *                      ex. {"normalized_reference": "504190", ...}
 * Si la ligne est invalide, status = error et error_message explique pourquoi.
 *
 * (Les notions fillable, casts, relations et traits sont expliquées en
 * détail dans app/Models/Import.php.)
 */
class ImportRow extends Model
{
    use Auditable, HasFactory, HasUserstamps, SoftDeletes;

    protected $fillable = [
        'import_id',
        'row_number',       // numéro de ligne dans le fichier (pour retrouver la ligne en cas d'erreur)
        'raw_data',
        'transformed_data',
        'normalized_data',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            // Les trois colonnes sont du JSON en base : on les manipule comme des tableaux PHP.
            'raw_data' => 'array',
            'transformed_data' => 'array',
            'normalized_data' => 'array',
            'status' => ImportRowStatus::class,
        ];
    }

    /** Le fichier dont provient la ligne. */
    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    /** La transaction créée à partir de cette ligne (aucune si la ligne est en erreur). */
    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }
}
