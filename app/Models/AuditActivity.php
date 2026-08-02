<?php

namespace App\Models;

use LogicException;
use Spatie\Activitylog\Models\Activity;

class AuditActivity extends Activity
{
    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Audit records are append-only.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Audit records are append-only.');
        });
    }
}
