<?php

namespace App\Models;

/**
 * Source de données de transaction (fichier émanant d'une banque ou d'un canal).
 *
 * Une Source définit la nature du fichier importé : type de fichier (csv/xlsx),
 * banque émettrice, devise par défaut et le mapping de colonnes qui lui est
 * associé. C'est le pivot entre les imports, les transactions et les règles
 * de rapprochement.
 *
 * Sources utilisées : ALPHA (encaissements, XLSX), BNA (relevé bancaire, XLSX),
 * WEB/STEG (portail de paiement, CSV) et SMT (passerelle de paiement, CSV).
 *
 * (Les notions fillable, casts, relations et traits sont expliquées en
 * détail dans app/Models/Import.php.)
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
    use Auditable, HasFactory, HasUserstamps, SoftDeletes;

    protected $fillable = [
        'code',                 // ex. 'BNA' : utilisé partout dans le code pour reconnaître la source
        'name',
        'bank_id',
        'file_type',            // 'csv' ou 'xlsx' (voir App\Enums\FileType)
        'default_currency_id',
        'is_active',
        'description',
        'config',               // réglages de lecture, ex. {"csv_delimiter": ";"}
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

    /** Devise utilisée quand le fichier n'indique pas de devise. */
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

    /**
     * BelongsToMany = relation « plusieurs à plusieurs ». Une source peut être
     * utilisée par plusieurs règles, et une règle concerne plusieurs sources.
     * Le lien passe par une table intermédiaire, ici matching_rule_sources.
     */
    public function matchingRules(): BelongsToMany
    {
        return $this->belongsToMany(MatchingRule::class, 'matching_rule_sources');
    }

    /** Associations « colonne du fichier -> champ interne » de cette source. */
    public function columnMappings(): HasMany
    {
        return $this->hasMany(SourceColumnMapping::class);
    }
}
