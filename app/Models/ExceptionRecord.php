<?php

namespace App\Models;

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExceptionRecord extends Model
{
    use HasFactory, SoftDeletes, HasUserstamps, Auditable;

    protected $table = 'exceptions';

    protected $fillable = [
        'normalized_transaction_id',
        'matching_result_id',
        'type',
        'status',
        'assigned_to',
        'resolution_comment',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExceptionType::class,
            'status' => ExceptionStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function normalizedTransaction(): BelongsTo
    {
        return $this->belongsTo(NormalizedTransaction::class);
    }

    public function matchingResult(): BelongsTo
    {
        return $this->belongsTo(MatchingResult::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ExceptionAttachment::class, 'exception_id');
    }
}
