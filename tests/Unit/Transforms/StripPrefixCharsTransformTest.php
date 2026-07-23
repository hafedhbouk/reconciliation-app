<?php

use App\Services\Import\Transforms\StripPrefixCharsTransform;

test('it strips a lowercase b prefix when configured', function () {
    $result = (new StripPrefixCharsTransform())->apply('b934516', ['chars' => ['B', 'b']], []);
    expect($result)->toBe('934516');
});

test('it strips an uppercase B prefix when configured', function () {
    $result = (new StripPrefixCharsTransform())->apply('B934516', ['chars' => ['B', 'b']], []);
    expect($result)->toBe('934516');
});

test('it leaves a value untouched when it does not start with a configured char', function () {
    $result = (new StripPrefixCharsTransform())->apply('3xxxx', ['chars' => ['B', 'b']], []);
    expect($result)->toBe('3xxxx');
});

test('it never assumes the prefix is present even for letter-prefixed values', function () {
    $result = (new StripPrefixCharsTransform())->apply('Y934516', ['chars' => ['B', 'b']], []);
    expect($result)->toBe('Y934516');
});

test('it handles a single leading space character correctly', function () {
    $result = (new StripPrefixCharsTransform())->apply(' 934516', ['chars' => ['B', 'b']], []);
    expect($result)->toBe(' 934516');
});
