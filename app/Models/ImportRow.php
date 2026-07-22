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

class ImportRow extends Model
{
    use HasFactory, SoftDeletes, HasUserstamps, Auditable;

    protected $fillable = [
        'import_id',
        'row_number',
        'raw_data',
        'transformed_data',
        'normalized_data',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'transformed_data' => 'array',
            'normalized_data' => 'array',
            'status' => ImportRowStatus::class,
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }
}
