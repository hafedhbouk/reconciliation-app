<?php

namespace Database\Factories;

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Models\ExceptionRecord;
use App\Models\NormalizedTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExceptionRecord>
 */
class ExceptionRecordFactory extends Factory
{
    protected $model = ExceptionRecord::class;

    public function definition(): array
    {
        return [
            'normalized_transaction_id' => NormalizedTransaction::factory(),
            'matching_result_id' => null,
            'type' => ExceptionType::Unmatched->value,
            'status' => ExceptionStatus::Open->value,
            'assigned_to' => null,
            'resolution_comment' => null,
            'resolved_by' => null,
            'resolved_at' => null,
        ];
    }
}
