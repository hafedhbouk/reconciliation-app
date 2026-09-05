<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnmatchedSnapshot extends Model
{
    use HasFactory, HasUserstamps, Auditable;

    protected $table = 'unmatched_snapshots';

    protected $fillable = [
        'import_a_id',
        'import_b_id',
        'status',
        'result_a',
        'result_b',
        'error',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'result_a' => 'array',
            'result_b' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function importA(): BelongsTo
    {
        return $this->belongsTo(Import::class, 'import_a_id');
    }

    public function importB(): BelongsTo
    {
        return $this->belongsTo(Import::class, 'import_b_id');
    }
}
