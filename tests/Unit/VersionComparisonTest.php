<?php

use Infinity\Evolver\Support\VersionTracking;

test('is equal', function () {
    $isEqual = VersionTracking::fromValue('1.0.0')->isEqual('1.0.0');

    expect($isEqual)->toBeTrue();
});

test('is not equal', function () {
    $isNotEqual = VersionTracking::fromValue('1.0.0')
        ->isNotEqual('2.0.0');

    expect($isNotEqual)->toBeTrue();
});

test('is less than', function () {
    $isLessThan = VersionTracking::fromValue('1.0.0')
        ->isLessThan('1.0.1');

    expect($isLessThan)->toBeTrue();
});

test('is less than or equal', function () {
    $isLessThanOrEqual = VersionTracking::fromValue('1.0.0')
        ->isLessThanOrEqual('1.0.1');

    expect($isLessThanOrEqual)->toBeTrue();
});

test('is greater than', function () {
    $isGreaterThan = VersionTracking::fromValue('1.0.0')
        ->isGreaterThan('0.9.9');

    expect($isGreaterThan)->toBeTrue();
});

test('is greater than or equal', function () {
    $isGreaterThanOrEqual = VersionTracking::fromValue('1.0.0')
        ->isGreaterThanOrEqual('0.9.9');

    expect($isGreaterThanOrEqual)->toBeTrue();
});

test('is throwing an exception', function () {
    expect(fn () => VersionTracking::fromValue('1.0.0')->throwsException())
        ->toThrow(RuntimeException::class);
});
