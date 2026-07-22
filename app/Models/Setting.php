<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setting extends Model
{
    use HasFactory, SoftDeletes, HasUserstamps, Auditable;

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'description',
        'is_editable',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_editable' => 'boolean',
        ];
    }
}
