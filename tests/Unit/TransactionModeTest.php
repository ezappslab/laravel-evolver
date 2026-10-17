<?php

declare(strict_types=1);

use Infinity\Evolver\Deployment\TransactionMode;

it('resolves configured transaction modes', function (mixed $value, TransactionMode $mode): void {
    expect(TransactionMode::configured($value))->toBe($mode);
})->with([
    ['none', TransactionMode::None],
    ['action', TransactionMode::Action],
    ['batch', TransactionMode::Batch],
    [TransactionMode::Batch, TransactionMode::Batch],
]);

it('rejects unsupported transaction modes', function (mixed $value): void {
    expect(fn () => TransactionMode::configured($value))->toThrow(InvalidArgumentException::class, 'Invalid Evolver transaction mode.');
})->with(['nope', 'Batch', '', null, 1, true]);
