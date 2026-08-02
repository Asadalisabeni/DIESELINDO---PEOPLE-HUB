<?php

namespace App\Http\Requests\Concerns;

use App\Models\Employee;

trait ResolvesScopedEmployee
{
    private ?Employee $resolvedEmployee = null;

    protected function resolveEmployee(): ?Employee
    {
        if ($this->resolvedEmployee) {
            return $this->resolvedEmployee;
        }

        $user = $this->user();
        $publicId = $this->route('employee');

        if (! $user || ! is_string($publicId)) {
            return null;
        }

        $this->resolvedEmployee = Employee::query()
            ->visibleTo($user)
            ->where('public_id', $publicId)
            ->first();

        return $this->resolvedEmployee;
    }

    public function scopedEmployee(): Employee
    {
        return $this->resolvedEmployee ?? abort(404);
    }
}
