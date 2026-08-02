<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuthenticationEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'type',
        'email_hash',
        'ip_address',
        'user_agent',
        'context',
        'occurred_at',
    ];

    protected $hidden = [
        'email_hash',
        'ip_address',
        'user_agent',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'ip_address' => 'encrypted',
            'user_agent' => 'encrypted',
            'context' => 'encrypted:array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Authentication events are append-only.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Authentication events are append-only.');
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
