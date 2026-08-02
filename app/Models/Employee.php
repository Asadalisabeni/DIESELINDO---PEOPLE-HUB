<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'employee_number', 'full_name', 'nik', 'nik_last_four', 'nik_blind_index',
        'birth_place', 'birth_date', 'gender', 'marital_status', 'personal_email', 'company_email',
        'status', 'created_by', 'updated_by',
    ];

    protected $hidden = ['nik', 'nik_blind_index'];

    protected function casts(): array
    {
        return [
            'nik' => 'encrypted',
            'birth_date' => 'date',
            'status' => EmployeeStatus::class,
        ];
    }

    /** @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (! $search) {
            return $query;
        }

        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

        return $query->where(fn (Builder $builder) => $builder
            ->where('full_name', 'like', $term)
            ->orWhere('employee_number', 'like', $term)
            ->orWhere('company_email', 'like', $term));
    }

    /** @return HasMany<EmploymentHistory, $this> */
    public function employmentHistories(): HasMany
    {
        return $this->hasMany(EmploymentHistory::class)->latest('effective_from');
    }

    /** @return HasOne<EmploymentHistory, $this> */
    public function currentEmployment(): HasOne
    {
        $today = now()->toDateString();

        return $this->hasOne(EmploymentHistory::class)
            ->whereDate('effective_from', '<=', $today)
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>', $today))
            ->latestOfMany('effective_from');
    }

    /** @return HasMany<EmployeeContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(EmployeeContact::class)->latest('effective_from');
    }

    /** @return HasMany<EmergencyContact, $this> */
    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class)->latest('effective_from');
    }

    /** @return HasMany<EmployeeDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class)->latest();
    }

    /** @return HasMany<Contract, $this> */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class)->latest('start_date');
    }

    /** @return HasMany<EmployeeBankAccount, $this> */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(EmployeeBankAccount::class)->latest('effective_from');
    }

    /** @return HasMany<EmployeeTaxProfile, $this> */
    public function taxProfiles(): HasMany
    {
        return $this->hasMany(EmployeeTaxProfile::class)->latest('effective_from');
    }

    /** @return HasMany<EmployeeBpjsProfile, $this> */
    public function bpjsProfiles(): HasMany
    {
        return $this->hasMany(EmployeeBpjsProfile::class)->latest('effective_from');
    }

    /** @return HasOne<User, $this> */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }
}
