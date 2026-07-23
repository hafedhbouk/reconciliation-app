<?php

use App\Exceptions\Import\TransformException;
use App\Services\Import\Transforms\DecimalStringToMillimesTransform;

test('it converts a 3-decimal string to millimes', function () {
    $result = (new DecimalStringToMillimesTransform())->apply('78.000', ['decimals' => 3], []);
    expect($result)->toBe(78000);
});

test('it converts a 2-decimal string to millimes by padding', function () {
    $result = (new DecimalStringToMillimesTransform())->apply('4.75', ['decimals' => 3], []);
    expect($result)->toBe(4750);
});

test('it handles a negative decimal string', function () {
    $result = (new DecimalStringToMillimesTransform())->apply('-12.1', ['decimals' => 3], []);
    expect($result)->toBe(-12100);
});

test('it handles a value with no decimal point', function () {
    $result = (new DecimalStringToMillimesTransform())->apply('27', ['decimals' => 3], []);
    expect($result)->toBe(27000);
});

test('it defaults to 3 decimals when not configured', function () {
    $result = (new DecimalStringToMillimesTransform())->apply('4.75', [], []);
    expect($result)->toBe(4750);
});

test('it throws on a malformed decimal string', function () {
    (new DecimalStringToMillimesTransform())->apply('abc.def', [], []);
})->throws(TransformException::class);

test('it strips a space thousands-separator on 4+ digit BNA amounts', function () {
    $result = (new DecimalStringToMillimesTransform())->apply('1 773.000', ['decimals' => 3], []);
    expect($result)->toBe(1773000);
});

test('it strips a non-breaking-space thousands-separator', function () {
    $result = (new DecimalStringToMillimesTransform())->apply("2\u{00A0}664.000", ['decimals' => 3], []);
    expect($result)->toBe(2664000);
});
