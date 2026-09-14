<?php

use App\Models\Source;
use App\Services\Import\Readers\ImportRowReaderFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

test('xlsx reading preserves references and row numbers across window boundaries', function () {
    $path = tempnam(sys_get_temp_dir(), 'xlsx-reader-');
    $book = new Spreadsheet;
    try {
        $sheet = $book->getActiveSheet();
        $sheet->fromArray(['REFERENCE', 'Montant'], null, 'A1');
        for ($i = 1; $i <= 1003; $i++) {
            $sheet->setCellValueExplicit('A'.($i + 1), sprintf('%09d', $i), 's');
            $sheet->setCellValue('B'.($i + 1), $i === 1001 ? 123.456 : $i);
        }
        (new Xlsx($book))->save($path);
        $reader = app(ImportRowReaderFactory::class)->make(new Source(['file_type' => 'xlsx']));

        expect($reader->headers($path, []))->toBe(['REFERENCE', 'Montant']);
        $rows = iterator_to_array($reader->read($path, []));
        expect($rows)->toHaveCount(1003)
            ->and(array_keys($rows))->toBe(range(1, 1003))
            ->and($rows[1]['REFERENCE'])->toBe('000000001')
            ->and($rows[1000]['REFERENCE'])->toBe('000001000')
            ->and($rows[1001]['REFERENCE'])->toBe('000001001')
            ->and((float) $rows[1001]['Montant'])->toBe(123.456)
            ->and($rows[1003]['REFERENCE'])->toBe('000001003');
    } finally {
        $book->disconnectWorksheets();
        unlink($path);
    }
});

test('a header only workbook produces no imported data rows', function () {
    $path = tempnam(sys_get_temp_dir(), 'xlsx-reader-');
    $book = new Spreadsheet;
    try {
        $book->getActiveSheet()->fromArray(['REFERENCE', 'Montant']);
        (new Xlsx($book))->save($path);
        $reader = app(ImportRowReaderFactory::class)->make(new Source(['file_type' => 'xlsx']));
        expect($reader->headers($path, []))->toBe(['REFERENCE', 'Montant'])
            ->and(iterator_to_array($reader->read($path, [])))->toBe([]);
    } finally {
        $book->disconnectWorksheets();
        unlink($path);
    }
});
