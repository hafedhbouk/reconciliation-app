<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * One export class serves CSV, XLSX, and PDF (via Maatwebsite/Excel's
 * writer-type abstraction, only the $writerType passed to Excel::download()
 * changes) for every exportable screen in the app. Each caller supplies its
 * own already-filtered query, headings, and a row-mapping closure -- this
 * class has no resource-specific knowledge, mirroring the "generic engine
 * over per-case code" precedent set by the Phase 2 mapping engine and the
 * Phase 3 matching engine.
 */
class GenericTableExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param array<int,string> $headings
     * @param \Closure(mixed):array<int,mixed> $mapRow
     */
    public function __construct(
        private Builder $query,
        private array $headings,
        private \Closure $mapRow,
    ) {
    }

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function map($row): array
    {
        return ($this->mapRow)($row);
    }
}
