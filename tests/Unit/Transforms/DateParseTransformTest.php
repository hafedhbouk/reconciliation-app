<?php

use App\Exceptions\Import\TransformException;
use App\Services\Import\Transforms\DateParseTransform;

test('it parses ALPHA/BNA style d/m/Y dates', function () {
    $result = (new DateParseTransform())->apply('01/01/2026', ['format' => 'd/m/Y', 'output' => 'date'], []);
    expect($result)->toBe('2026-01-01');
});

test('it parses SMT date_paiement style Y-m-d H:i:s datetimes', function () {
    $result = (new DateParseTransform())->apply('2026-01-01 15:36:04', ['format' => 'Y-m-d H:i:s', 'output' => 'datetime'], []);
    expect($result)->toBe('2026-01-01 15:36:04');
});

test('it parses WEB style dotted Y.m.d H:i:s datetimes', function () {
    $result = (new DateParseTransform())->apply('2026.01.31 23:51:52', ['format' => 'Y.m.d H:i:s', 'output' => 'datetime'], []);
    expect($result)->toBe('2026-01-31 23:51:52');
});

test('it parses SMT compact dmY dates', function () {
    $result = (new DateParseTransform())->apply('16122025', ['format' => 'dmY', 'output' => 'date'], []);
    expect($result)->toBe('2025-12-16');
});

test('it parses STEG style d/m/Y H:i:s datetimes', function () {
    $result = (new DateParseTransform())->apply('01/01/2026 08:30:00', ['format' => 'd/m/Y H:i:s', 'output' => 'datetime'], []);
    expect($result)->toBe('2026-01-01 08:30:00');
});

test('it defaults to date output when not configured', function () {
    $result = (new DateParseTransform())->apply('01/01/2026', ['format' => 'd/m/Y'], []);
    expect($result)->toBe('2026-01-01');
});

test('it throws on a malformed date string', function () {
    (new DateParseTransform())->apply('31-13-2026', ['format' => 'd/m/Y'], []);
})->throws(TransformException::class);

test('it throws when the format config is missing', function () {
    (new DateParseTransform())->apply('01/01/2026', [], []);
})->throws(TransformException::class);
