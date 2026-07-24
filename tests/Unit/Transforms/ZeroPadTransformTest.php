<?php

use App\Services\Import\Transforms\ZeroPadTransform;

test('it pads a short numeric value with leading zeros to the target length', function () {
    $result = (new ZeroPadTransform())->apply('3512', ['length' => 6], []);
    expect($result)->toBe('003512');
});

test('it leaves a value already at the target length untouched', function () {
    $result = (new ZeroPadTransform())->apply('003512', ['length' => 6], []);
    expect($result)->toBe('003512');
});

test('it leaves a value longer than the target length untouched', function () {
    $result = (new ZeroPadTransform())->apply('1234567', ['length' => 6], []);
    expect($result)->toBe('1234567');
});

test('it leaves the value untouched when no length is configured', function () {
    $result = (new ZeroPadTransform())->apply('3512', [], []);
    expect($result)->toBe('3512');
});
