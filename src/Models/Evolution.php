<?php

declare(strict_types=1);

namespace Infinity\Evolver\Models;

use Illuminate\Database\Eloquent\Model;

final class Evolution extends Model
{
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'batch_id',
        'action_id',
        'checksum',
        'target_version',
        'duration_ms',
        'ran_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['ran_at' => 'immutable_datetime'];
    }
}
