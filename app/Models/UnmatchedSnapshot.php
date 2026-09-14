<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use App\Services\Matching\SnapshotRows;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnmatchedSnapshot extends Model
{
    use Auditable, HasFactory, HasUserstamps;

    protected $table = 'unmatched_snapshots';

    protected $fillable = [
        'rows_persisted', 'file_totals',
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
            'rows_persisted' => 'boolean', 'file_totals' => 'array',
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

    /** Compatibility for explicit full exports; screens use SnapshotRows::paginate. */
    public function getResultAAttribute($value): ?array
    {
        return $this->resultRows('a', $value);
    }

    public function getResultBAttribute($value): ?array
    {
        return $this->resultRows('b', $value);
    }

    private function resultRows(string $side, $value): ?array
    {
        if ($this->rows_persisted) {
            if ($this->status !== 'completed') {
                return null;
            }

            return app(SnapshotRows::class)->query($this->id, $side)->get(['data'])
                ->map(fn ($row) => json_decode($row->data, true))->all();
        }

        return is_string($value) ? json_decode($value, true) : $value;
    }

    public function importB(): BelongsTo
    {
        return $this->belongsTo(Import::class, 'import_b_id');
    }
}
