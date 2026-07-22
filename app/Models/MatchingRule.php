<?php

namespace App\Models;

use App\Enums\MatchingCardinality;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MatchingRule extends Model
{
    use HasFactory, SoftDeletes, HasUserstamps, Auditable;

    protected $fillable = [
        'name',
        'description',
        'source_a_id',
        'source_b_id',
        'cardinality',
        'priority',
        'is_active',
        'criteria',
    ];

    protected function casts(): array
    {
        return [
            'cardinality' => MatchingCardinality::class,
            'priority' => 'integer',
            'is_active' => 'boolean',
            'criteria' => 'array',
        ];
    }

    public function sourceA(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_a_id');
    }

    public function sourceB(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_b_id');
    }

    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(Source::class, 'matching_rule_sources');
    }

    public function matchingResults(): HasMany
    {
        return $this->hasMany(MatchingResult::class);
    }
}
