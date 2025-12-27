<?php

namespace Infinity\Evolver\Models;

use Illuminate\Database\Eloquent\Model;

class Evolution extends Model
{
    /**
     * The attributes that are mass-assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'batch_id',
        'action_id',
        'checksum',
        'status',
        'introduced_in',
        'required_until',
        'target_version',
        'duration_ms',
        'exception',
        'ran_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'ran_at' => 'datetime',
        ];
    }
}
