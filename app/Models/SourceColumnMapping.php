<?php

namespace App\Models;

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
