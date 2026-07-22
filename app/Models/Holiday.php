<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Holiday extends Model
{
    use HasFactory, SoftDeletes, HasUserstamps, Auditable;

    protected $fillable = [
        'holiday_date',
        'name',
        'country_code',
        'is_recurring_yearly',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date:Y-m-d',
            'is_recurring_yearly' => 'boolean',
        ];
    }
}
