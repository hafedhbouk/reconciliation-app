<?php

namespace App\Services\Import;

use App\Models\Import;
use App\Models\Source;
use App\Models\SourceColumnMapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportMappingVersion
{
    public function capture(Source $source): array
    {
        return [
            'normalizer_version' => 1,
            'file_type' => $source->file_type,
            'config' => $source->config ?? [],
            'bank_id' => $source->bank_id,
            'default_currency_id' => $source->default_currency_id,
            'mappings' => SourceColumnMapping::where('source_id', $source->id)->orderBy('sort_order')->orderBy('target_field')
                ->get()->map(fn ($mapping) => $mapping->only(['target_field', 'source_column', 'transform', 'is_required', 'sort_order']))->all(),
        ];
    }

    public function hash(array $snapshot): string
    {
        // JSON object ordering must not create a spurious mapping revision.
        $canonical = function ($value) use (&$canonical) {
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($canonical, $value);
        };

        return hash('sha256', json_encode($canonical($snapshot), JSON_THROW_ON_ERROR));
    }

    public function freeze(Import $import, bool $refreshUnused = false): Import
    {
        return DB::transaction(function () use ($import, $refreshUnused) {
            $locked = Import::whereKey($import->id)->lockForUpdate()->firstOrFail();
            if ($locked->mapping_snapshot === null || ($refreshUnused && ! $locked->rows()->exists())) {
                if ($locked->rows()->exists()) {
                    throw new RuntimeException('Ancien import sans version de mapping : renormalisation ou nouvel import nécessaire avant reprise.');
                }
                $snapshot = $this->capture($locked->source);
                $locked->update(['mapping_snapshot' => $snapshot, 'mapping_hash' => $this->hash($snapshot)]);
            }

            return $locked;
        });
    }

    public function mappings(array $snapshot): Collection
    {
        return collect($snapshot['mappings'])->map(fn ($row) => new SourceColumnMapping($row));
    }

    public function state(Import $import, ?string $currentHash = null): string
    {
        if ($import->mapping_hash === null) {
            return 'unknown';
        }

        return hash_equals($import->mapping_hash, $currentHash ?? $this->hash($this->capture($import->source))) ? 'current' : 'outdated';
    }
}
