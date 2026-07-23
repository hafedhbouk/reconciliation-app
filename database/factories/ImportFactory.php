<?php

namespace Database\Factories;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
{
    protected $model = Import::class;

    public function definition(): array
    {
        return [
            'source_id' => Source::factory(),
            'original_filename' => fake()->word().'.csv',
            'stored_path' => 'imports/'.fake()->uuid().'.csv',
            'file_hash' => hash('sha256', fake()->uuid()),
            'mime_type' => 'text/csv',
            'size_bytes' => fake()->numberBetween(100, 100000),
            'status' => ImportStatus::Pending->value,
            'total_rows' => null,
            'processed_rows' => 0,
            'success_rows' => 0,
            'error_rows' => 0,
        ];
    }
}
