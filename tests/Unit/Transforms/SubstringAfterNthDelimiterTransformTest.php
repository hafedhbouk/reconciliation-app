<?php

use App\Exceptions\Import\TransformException;
use App\Services\Import\Transforms\SubstringAfterNthDelimiterTransform;

test('it implements the STEG rule: 9 chars after the 2nd comma', function () {
    $result = (new SubstringAfterNthDelimiterTransform())->apply(
        'a,b,123456789xyz',
        ['delimiter' => ',', 'n' => 2, 'length' => 9],
        []
    );

    expect($result)->toBe('123456789');
});

test('it defaults to comma delimiter and first occurrence', function () {
    $result = (new SubstringAfterNthDelimiterTransform())->apply('foo,bar,baz', [], []);
    expect($result)->toBe('bar,baz');
});

test('it returns the full remainder when no length is configured', function () {
    $result = (new SubstringAfterNthDelimiterTransform())->apply('a,b,123456789xyz', ['n' => 2], []);
    expect($result)->toBe('123456789xyz');
});

test('it throws when the nth delimiter does not exist', function () {
    (new SubstringAfterNthDelimiterTransform())->apply('a,b', ['n' => 5], []);
})->throws(TransformException::class);
