<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée la table imports : un enregistrement par fichier déposé (voir app/Models/Import.php).
 *
 * ---------------------------------------------------------------------------
 * GUIDE DÉBUTANT : qu'est-ce qu'une « migration » ? (valable pour database/migrations)
 * ---------------------------------------------------------------------------
 * Une migration décrit, en PHP, une modification de la STRUCTURE de la base
 * (créer une table, ajouter une colonne, un index…). Avantages : la structure
 * est versionnée avec le code, et chaque développeur obtient la même base.
 *   - up()   : applique la modification ;
 *   - down() : l'annule (utilisé par `php artisan migrate:rollback`).
 * `php artisan migrate` exécute, dans l'ordre de leur nom (date en préfixe),
 * les migrations pas encore appliquées. La table `migrations` garde la liste
 * de celles déjà exécutées.
 * RÈGLE D'OR : ne jamais modifier une migration déjà exécutée en production ;
 * on en crée une nouvelle (comme les fichiers « add_... » plus récents).
 *
 * Types de colonnes courants :
 *   id()                 clé primaire auto-incrémentée
 *   foreignId('x_id')    clé étrangère vers une autre table
 *     ->constrained('t') la base vérifie que la valeur existe dans t.id
 *     ->cascadeOnDelete() si la ligne liée est supprimée, celle-ci aussi
 *     ->nullOnDelete()    si la ligne liée est supprimée, la colonne passe à NULL
 *   string / text / json / boolean / timestamp / unsignedInteger …
 *   ->nullable()         la valeur peut être vide (NULL)
 *   ->default(...)       valeur par défaut
 *   timestamps()         colonnes created_at et updated_at
 *   softDeletes()        colonne deleted_at (suppression « douce »)
 *   userstamps()         colonnes created_by et updated_by (macro définie dans AppServiceProvider)
 *   index('col')         accélère les recherches sur cette colonne
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->string('original_filename');                   // nom du fichier déposé
            $table->string('stored_path');                         // emplacement sur le disque
            $table->string('file_hash', 64)->nullable();           // empreinte SHA-256 (64 caractères)
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('status')->default('pending');          // voir App\Enums\ImportStatus
            $table->unsignedInteger('total_rows')->nullable();
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_summary')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();

            // Index sur l'empreinte : la détection d'un fichier déjà importé reste rapide.
            $table->index('file_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
