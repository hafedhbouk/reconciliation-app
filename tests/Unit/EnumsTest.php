<?php

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Enums\MappingTargetField;
use App\Enums\MatchingCardinality;
use App\Enums\MatchingResultStatus;
use App\Enums\MatchingStatus;
use App\Enums\TransformType;

test('every ImportStatus case has a label and badge class', function () {
    foreach (ImportStatus::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
        expect($case->badgeClass())->toBeString()->not->toBeEmpty();
    }
});

test('every ImportRowStatus case has a label', function () {
    foreach (ImportRowStatus::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});

test('every MatchingStatus case has a label and badge class', function () {
    foreach (MatchingStatus::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
        expect($case->badgeClass())->toBeString()->not->toBeEmpty();
    }
});

test('every MatchingResultStatus case has a label', function () {
    foreach (MatchingResultStatus::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});

test('every MatchingCardinality case has a label', function () {
    foreach (MatchingCardinality::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});

test('every ExceptionType case has a label', function () {
    foreach (ExceptionType::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});

test('every ExceptionStatus case has a label and badge class', function () {
    foreach (ExceptionStatus::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
        expect($case->badgeClass())->toBeString()->not->toBeEmpty();
    }
});

test('every MappingTargetField case has a label and a defined core/auxiliary status', function () {
    foreach (MappingTargetField::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
        expect($case->isCore())->toBeBool();
    }
});

test('exactly the 6 real transaction-column fields are core mapping targets', function () {
    $core = array_map(fn ($case) => $case->value, array_filter(MappingTargetField::cases(), fn ($case) => $case->isCore()));

    expect($core)->toEqualCanonicalizing(['reference', 'amount', 'date', 'datetime', 'canal', 'currency_code']);
});

test('every TransformType case has a label', function () {
    foreach (TransformType::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});
