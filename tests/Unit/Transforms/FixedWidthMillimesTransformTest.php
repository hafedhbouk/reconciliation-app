<?php

use App\Exceptions\Import\TransformException;
use App\Services\Import\Transforms\FixedWidthMillimesTransform;

test('it trims and casts a space-padded millimes string', function () {
    $result = (new FixedWidthMillimesTransform())->apply(' 000000042000', [], []);
    expect($result)->toBe(42000);
});

test('it handles a negative fixed-width value', function () {
    $result = (new FixedWidthMillimesTransform())->apply('-000000042000', [], []);
    expect($result)->toBe(-42000);
});

test('it throws on non-numeric input', function () {
    (new FixedWidthMillimesTransform())->apply('not-a-number', [], []);
})->throws(TransformException::class);
