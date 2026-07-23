<?php

namespace App\Contracts;

interface ImportRowReader
{
    /** @return array<int,string> literal, untouched header text, in file order */
    public function headers(string $absolutePath, array $sourceConfig): array;

    /** @return \Generator<int,array<string,mixed>> row_number (1-indexed, data rows only) => raw row keyed by header */
    public function read(string $absolutePath, array $sourceConfig): \Generator;
}
