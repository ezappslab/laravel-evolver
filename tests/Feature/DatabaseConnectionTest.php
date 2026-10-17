<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Infinity\Evolver\Contracts\ActionRepository;

it('uses the Laravel default connection without overriding its binding', function (): void {
    config()->set('evolver.database.connection');

    $laravelConnection = app('db.connection');
    app(ActionRepository::class)->record('001_default', Str::repeat('0', 64), '00000000-0000-0000-0000-000000000001', 0, null);

    expect($laravelConnection)->toBe(DB::connection())
        ->and($laravelConnection->table('evolutions')->pluck('action_id')->all())->toBe(['001_default']);
});

it('uses a named connection through Laravel database manager', function (): void {
    config()->set('database.connections.evolver_testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    config()->set('evolver.database.connection', 'evolver_testing');

    $defaultConnection = app('db.connection');
    $namedConnection = DB::connection('evolver_testing');
    Schema::connection('evolver_testing')->create('evolutions', function (Blueprint $table): void {
        $table->id();
        $table->uuid('batch_id')->index();
        $table->string('action_id')->unique();
        $table->string('checksum', 64);
        $table->string('target_version')->nullable();
        $table->unsignedInteger('duration_ms');
        $table->timestamp('ran_at')->index();
    });

    app(ActionRepository::class)->record('001_named', Str::repeat('0', 64), '00000000-0000-0000-0000-000000000001', 0, null);

    expect($namedConnection)->toBe(DB::connection('evolver_testing'))
        ->and($namedConnection->table('evolutions')->pluck('action_id')->all())->toBe(['001_named'])
        ->and($defaultConnection)->toBe(app('db.connection'))
        ->and($defaultConnection->table('evolutions')->count())->toBe(0)
        ->and(app(Connection::class))->toBe($defaultConnection)
        ->and(app(Connection::class))->not->toBe($namedConnection);
});
