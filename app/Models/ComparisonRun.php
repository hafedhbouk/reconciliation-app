<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComparisonRun extends Model
{
    protected $fillable = [
        'file_totals', 'invalidated_at',
        'import_a_id', 'import_b_id', 'batch_reference', 'criteria',
        'summary', 'unmatched_a_ids', 'unmatched_b_ids',
    ];

    protected function casts(): array
    {
        return ['invalidated_at' => 'datetime', 'file_totals' => 'array', 'criteria' => 'array', 'summary' => 'array', 'unmatched_a_ids' => 'array', 'unmatched_b_ids' => 'array'];
    }

    public function importA(): BelongsTo
    {
        return $this->belongsTo(Import::class, 'import_a_id')->withTrashed();
    }

    public function importB(): BelongsTo
    {
        return $this->belongsTo(Import::class, 'import_b_id')->withTrashed();
    }
}
