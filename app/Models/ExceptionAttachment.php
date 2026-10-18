<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pièce jointe ajoutée à une exception (ex. justificatif PDF d'un écart).
 *
 * Le fichier lui-même est sur le disque (disk + path) ; la base ne garde
 * que ses informations. Géré par ExceptionAttachmentController.
 * (Les notions fillable, relations et traits sont expliquées dans app/Models/Import.php.)
 */
class ExceptionAttachment extends Model
{
    use Auditable, HasFactory, HasUserstamps, SoftDeletes;

    protected $fillable = [
        'exception_id',
        'disk',          // nom du disque de stockage Laravel (config/filesystems.php), ex. 'local'
        'path',          // chemin du fichier sur ce disque
        'original_name', // nom du fichier tel qu'envoyé par l'utilisateur
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    /** L'exception à laquelle est jointe la pièce (la colonne s'appelle exception_id). */
    public function exception(): BelongsTo
    {
        return $this->belongsTo(ExceptionRecord::class, 'exception_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
