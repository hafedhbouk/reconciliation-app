<?php

use App\Exceptions\Import\TransformException;
use App\Services\Import\Transforms\RightCharsTransform;

test('it extracts N rightmost characters from a value', function () {
    $transform = new RightCharsTransform();

    expect($transform->apply('session123456789', ['length' => 9], []))->toBe('123456789');
});

test('it extracts the correct number of characters when length is less than string', function () {
    $transform = new RightCharsTransform();

    expect($transform->apply('ABCDEFGHIJ', ['length' => 4], []))->toBe('GHIJ');
});

test('it returns the entire string when length equals string length', function () {
    $transform = new RightCharsTransform();

    expect($transform->apply('ABCDEF', ['length' => 6], []))->toBe('ABCDEF');
});

test('it trims whitespace before extracting characters', function () {
    $transform = new RightCharsTransform();

    expect($transform->apply('  123456789  ', ['length' => 9], []))->toBe('123456789');
});

test('it handles single character extraction', function () {
    $transform = new RightCharsTransform();

    expect($transform->apply('ABCDE', ['length' => 1], []))->toBe('E');
});

test('it handles multibyte characters correctly', function () {
    $transform = new RightCharsTransform();

    expect($transform->apply('héllo', ['length' => 3], []))->toBe('llo');
});

test('it throws TransformException when length config is missing', function () {
    $transform = new RightCharsTransform();

    $transform->apply('ABCDEFGHIJ', [], []);
})->throws(TransformException::class, 'Le paramètre "length" est requis pour right_chars.');

test('it throws TransformException when length is zero', function () {
    $transform = new RightCharsTransform();

    $transform->apply('ABCDEFGHIJ', ['length' => 0], []);
})->throws(TransformException::class, 'Le paramètre "length" doit être positif pour right_chars.');

test('it throws TransformException when length is negative', function () {
    $transform = new RightCharsTransform();

    $transform->apply('ABCDEFGHIJ', ['length' => -5], []);
})->throws(TransformException::class, 'Le paramètre "length" doit être positif pour right_chars.');

test('it throws TransformException when value is shorter than length', function () {
    $transform = new RightCharsTransform();

    $transform->apply('ABC', ['length' => 5], []);
})->throws(TransformException::class, "Impossible d'extraire 5 caractères à droite de 'ABC' (longueur insuffisante).");

test('it casts non-string values to string', function () {
    $transform = new RightCharsTransform();

    expect($transform->apply(123456789, ['length' => 4], []))->toBe('6789');
});

test('it handles the WEB/STEG use case: 9-digit reference from fused column', function () {
    $transform = new RightCharsTransform();

    // Simulates a fused session+reference column where reference is last 9 chars
    expect($transform->apply('SESS2024001234567', ['length' => 9], []))->toBe('001234567');
});
