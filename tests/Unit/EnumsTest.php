<?php

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Enums\MatchingCardinality;
use App\Enums\MatchingResultStatus;
use App\Enums\MatchingStatus;

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
