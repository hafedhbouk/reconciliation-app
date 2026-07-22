<?php

namespace App\Models;

use App\Enums\MatchingResultStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MatchingResult extends Model
{
    use HasFactory, SoftDeletes, HasUserstamps, Auditable;

    protected $fillable = [
        'matching_rule_id',
        'batch_reference',
        'status',
        'confidence_score',
        'matched_by',
        'matched_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => MatchingResultStatus::class,
            'confidence_score' => 'decimal:2',
            'matched_at' => 'datetime',
        ];
    }

    public function matchingRule(): BelongsTo
    {
        return $this->belongsTo(MatchingRule::class);
    }

    public function matchedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    public function matchingDetails(): HasMany
    {
        return $this->hasMany(MatchingDetail::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(ExceptionRecord::class);
    }
}
