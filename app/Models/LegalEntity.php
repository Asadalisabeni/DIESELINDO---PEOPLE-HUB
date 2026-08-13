<?php

namespace App\Models;

use App\Enums\MasterStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalEntity extends Model
{
    use HasPublicId;

    protected $fillable = [
        'code', 'legal_name', 'display_name', 'tax_identifier', 'tax_identifier_last_four',
        'tax_identifier_blind_index', 'address_line_1', 'address_line_2', 'city', 'province',
        'postal_code', 'country_code', 'timezone', 'currency', 'status', 'created_by', 'updated_by',
    ];

    protected $hidden = ['tax_identifier', 'tax_identifier_blind_index'];

    protected function casts(): array
    {
        return [
            'tax_identifier' => 'encrypted',
            'status' => MasterStatus::class,
        ];
    }

    /** @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', MasterStatus::Active->value);
    }

    /** @return HasMany<Branch, $this> */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /** @return HasMany<Division, $this> */
    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }

    /** @return HasMany<Department, $this> */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /** @return HasMany<Position, $this> */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /** @return HasMany<WorkLocation, $this> */
    public function workLocations(): HasMany
    {
        return $this->hasMany(WorkLocation::class);
    }

    /** @return HasMany<CostCenter, $this> */
    public function costCenters(): HasMany
    {
        return $this->hasMany(CostCenter::class);
    }

    /** @return HasMany<Employee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /** @return HasMany<UserLegalEntityAccess, $this> */
    public function accessAssignments(): HasMany
    {
        return $this->hasMany(UserLegalEntityAccess::class);
    }

    /** @return HasMany<WorkSchedule, $this> */
    public function workSchedules(): HasMany
    {
        return $this->hasMany(WorkSchedule::class);
    }

    /** @return HasMany<AttendanceSource, $this> */
    public function attendanceSources(): HasMany
    {
        return $this->hasMany(AttendanceSource::class);
    }
}
