<?php

namespace App\Models;

/**
 * Modèle représentant un fichier importé par les utilisateurs.
 *
 * Un Import est le point d'entrée du pipeline : il stocke le fichier brut,
 * son hachage SHA-256 (pour détecter les doublons), les métadonnées de
 * traitement (statut, durée, compteurs de lignes) et l'utilisateur à l'origine
 * de l'import. Les relations vers ImportRow, Transaction et Source permettent
 * de naviguer depuis le fichier jusqu'aux transactions normalisées.
 *
 * ---------------------------------------------------------------------------
 * GUIDE DÉBUTANT : comment lire un modèle Eloquent (valable pour tous les
 * fichiers du dossier app/Models)
 * ---------------------------------------------------------------------------
 * - Un « modèle » est une classe PHP qui représente UNE table de la base de
 *   données. Import <-> table `imports`. Laravel devine le nom de la table en
 *   mettant le nom de la classe au pluriel et en snake_case.
 * - Un objet Import = une ligne de la table. On lit une colonne comme une
 *   propriété : $import->original_filename.
 * - Exemples d'utilisation :
 *     Import::find(9);                              // la ligne d'id 9
 *     Import::where('status', 'completed')->get();  // plusieurs lignes
 *     $import->update(['status' => 'failed']);      // modification
 * - Le pipeline complet d'un fichier :
 *     Import (le fichier) -> ImportRow (chaque ligne brute)
 *       -> Transaction (ligne convertie) -> NormalizedTransaction (version
 *          simplifiée utilisée par le rapprochement).
 */
use App\Enums\ImportStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Import extends Model
{
    /*
     * Les « traits » ajoutent des comportements réutilisables à la classe :
     * - Auditable     : chaque création/modification/suppression est écrite dans audit_logs
     * - HasFactory    : permet de créer de fausses données dans les tests (Import::factory())
     * - HasUserstamps : remplit automatiquement created_by / updated_by avec l'utilisateur connecté
     * - SoftDeletes   : delete() ne supprime pas vraiment la ligne. Il remplit
     *                   deleted_at, et la ligne est ensuite ignorée par les requêtes.
     */
    use Auditable, HasFactory, HasUserstamps, SoftDeletes;

    /**
     * Liste des colonnes qu'on a le droit de remplir « en masse » avec
     * Import::create([...]) ou ->update([...]). C'est une protection : une
     * colonne absente de cette liste est ignorée, ce qui évite qu'un
     * formulaire modifie une colonne imprévue.
     */
    protected $fillable = [
        // Copie figée du mapping au moment de l'import, et son empreinte (voir ImportMappingVersion).
        // heartbeat_at = « signe de vie » du job ; processing_file_hash = empreinte du fichier en cours.
        'mapping_snapshot', 'mapping_hash', 'heartbeat_at', 'processing_file_hash',
        'source_id',          // la source (ALPHA, BNA…) du fichier
        'bank_id',
        'original_filename',  // nom du fichier tel que déposé par l'utilisateur
        'stored_path',        // chemin du fichier sur le disque (storage/app/private/...)
        'file_hash',          // empreinte SHA-256 : deux fichiers identiques ont la même
        'mime_type',
        'size_bytes',
        'status',             // voir App\Enums\ImportStatus
        'total_rows',
        'processed_rows',
        'success_rows',
        'error_rows',
        'started_at',
        'finished_at',
        'job_dispatched_at',  // moment où le traitement a été mis en file d'attente
        'error_summary',
        'meta',
        'imported_by',        // id de l'utilisateur qui a déposé le fichier
    ];

    /**
     * « Casts » : conversions automatiques entre la base et PHP.
     * - 'array'    : la colonne contient du JSON, PHP reçoit un tableau
     * - 'datetime' : PHP reçoit un objet date (Carbon), pratique pour ->format('d/m/Y')
     * - ImportStatus::class : PHP reçoit un enum au lieu d'une simple chaîne
     */
    protected function casts(): array
    {
        return [
            'mapping_snapshot' => 'array',
            'heartbeat_at' => 'datetime',
            'status' => ImportStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'job_dispatched_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /*
     * ---- Relations ----
     * Une relation se déclare par une méthode, mais s'utilise comme une propriété :
     *   $import->source        -> l'objet Source lié (requête SQL faite automatiquement)
     *   $import->rows          -> la collection de toutes ses lignes
     * BelongsTo = « appartient à » : la table imports contient la clé étrangère (source_id).
     * HasMany   = « possède plusieurs » : c'est l'autre table qui contient import_id.
     */

    /** La source (type de fichier) de cet import. */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /** L'utilisateur qui a déposé le fichier (la colonne s'appelle imported_by, pas user_id, d'où le 2e argument). */
    public function importedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    /** Toutes les lignes brutes du fichier (table import_rows). */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    /** Toutes les transactions créées à partir de ce fichier. */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
