<?php

namespace App\Models;

/**
 * Source de données de transaction (fichier émanant d'une banque ou d'un canal).
 *
 * Une Source définit la nature du fichier importé : type de fichier (csv/xlsx),
 * banque émettrice, devise par défaut et le mapping de colonnes qui lui est
 * associé. C'est le pivot entre les imports, les transactions et les règles
 * de rapprochement.
 */
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Source extends Model
{
    use HasFactory, SoftDeletes, HasUserstamps, Auditable;

    protected $fillable = [
        'code',
        'name',
        'bank_id',
        'file_type',
        'default_currency_id',
        'is_active',
        'description',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'config' => 'array',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function defaultCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'default_currency_id');
    }

    public function imports(): HasMany
    {
        return $this->hasMany(Import::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function matchingRules(): BelongsToMany
    {
        return $this->belongsToMany(MatchingRule::class, 'matching_rule_sources');
    }

    public function columnMappings(): HasMany
    {
        return $this->hasMany(SourceColumnMapping::class);
    }
}
