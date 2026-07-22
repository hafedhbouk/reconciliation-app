<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchingDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'matching_result_id',
        'normalized_transaction_id',
        'side',
    ];

    public function matchingResult(): BelongsTo
    {
        return $this->belongsTo(MatchingResult::class);
    }

    public function normalizedTransaction(): BelongsTo
    {
        return $this->belongsTo(NormalizedTransaction::class);
    }
}
