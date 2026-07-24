<?php

use App\Services\Import\Transforms\SubstringFromRightTransform;

test('it takes the last N characters of the value', function () {
    $result = (new SubstringFromRightTransform())->apply('12,067890123', ['length' => 9], []);
    expect($result)->toBe('067890123');
});

test('it works regardless of how many delimiters are inside the value', function () {
    $result = (new SubstringFromRightTransform())->apply('a,b,c,123456789', ['length' => 9], []);
    expect($result)->toBe('123456789');
});

test('it returns the whole value when it is shorter than the requested length', function () {
    $result = (new SubstringFromRightTransform())->apply('4521', ['length' => 9], []);
    expect($result)->toBe('4521');
});

test('it leaves the value untouched when no length is configured', function () {
    $result = (new SubstringFromRightTransform())->apply('12,067890123', [], []);
    expect($result)->toBe('12,067890123');
});
