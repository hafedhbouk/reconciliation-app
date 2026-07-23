<?php

use App\Services\Import\Transforms\TrimTransform;

test('it trims surrounding whitespace', function () {
    expect((new TrimTransform())->apply('  hello  ', [], []))->toBe('hello');
});

test('it turns an all-whitespace value into null', function () {
    expect((new TrimTransform())->apply('   ', [], []))->toBeNull();
});

test('it casts non-string scalars to string before trimming', function () {
    expect((new TrimTransform())->apply(42, [], []))->toBe('42');
});
