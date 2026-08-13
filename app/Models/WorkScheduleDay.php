<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkScheduleDay extends Model
{
    protected $fillable = [
        'work_schedule_id', 'day_of_week', 'is_working_day', 'start_time', 'end_time',
        'break_minutes', 'crosses_midnight',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_working_day' => 'boolean',
            'break_minutes' => 'integer',
            'crosses_midnight' => 'boolean',
        ];
    }

    /** @return BelongsTo<WorkSchedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }
}
