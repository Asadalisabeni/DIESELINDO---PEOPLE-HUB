<?php

namespace App\Services\Employee;

use App\Enums\ContractStatus;
use App\Models\Branch;
use App\Models\Contract;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Division;
use App\Models\EmergencyContact;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeBpjsProfile;
use App\Models\EmployeeContact;
use App\Models\EmployeeTaxProfile;
use App\Models\EmploymentHistory;
use App\Models\LegalEntity;
use App\Models\Position;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\Organization\LegalEntityScope;
use App\Support\Security\SensitiveValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeManager
{
    public function __construct(private readonly LegalEntityScope $scope) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, LegalEntity $entity, array $data): Employee
    {
        return DB::transaction(function () use ($actor, $entity, $data): Employee {
            $references = $this->assignmentReferences($entity, $data);
            $nik = $this->nullableString($data['nik'] ?? null);
            $employee = Employee::query()->create([
                'legal_entity_id' => $entity->getKey(),
                'employee_number' => trim((string) $data['employee_number']),
                'full_name' => trim((string) $data['full_name']),
                'nik' => $nik,
                'nik_last_four' => SensitiveValue::lastFour($nik),
                'nik_blind_index' => SensitiveValue::blindIndex($nik, 'employee.nik'),
                'birth_place' => $this->nullableString($data['birth_place'] ?? null),
                'birth_date' => $this->nullableString($data['birth_date'] ?? null),
                'gender' => $this->nullableString($data['gender'] ?? null),
                'marital_status' => $this->nullableString($data['marital_status'] ?? null),
                'personal_email' => $this->nullableString($data['personal_email'] ?? null),
                'company_email' => $this->nullableString($data['company_email'] ?? null),
                'status' => 'active',
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);

            $history = EmploymentHistory::query()->create([
                'legal_entity_id' => $entity->getKey(),
                'employee_id' => $employee->getKey(),
                'employee_number' => $employee->employee_number,
                ...$references,
                'employment_status' => (string) $data['employment_status'],
                'join_date' => (string) $data['join_date'],
                'effective_from' => (string) $data['effective_from'],
                'change_reason' => (string) ($data['change_reason'] ?? 'Initial employment record.'),
                'source' => 'manual',
                'created_by' => $actor->getKey(),
            ]);

            $this->recordContact($employee, $actor, 'phone', $data['phone'] ?? null, (string) $data['effective_from']);
            $this->recordContact($employee, $actor, 'address', $data['address'] ?? null, (string) $data['effective_from']);
            $this->recordEmergencyContact($employee, $actor, $data, (string) $data['effective_from']);
            $this->recordInitialContract($employee, $actor, $data);
            $this->recordSensitiveProfiles($employee, $actor, $data, (string) $data['effective_from']);

            activity('employee')
                ->causedBy($actor)
                ->performedOn($employee)
                ->event('employee_created')
                ->withProperties([
                    'legal_entity_public_id' => $entity->public_id,
                    'employee_public_id' => $employee->public_id,
                    'employment_history_public_id' => $history->public_id,
                    'sensitive_fields_present' => array_values(array_filter([
                        $nik ? 'nik' : null,
                        ! empty($data['bank_account_number']) ? 'bank_account' : null,
                        ! empty($data['tax_identifier']) ? 'tax_identifier' : null,
                        ! empty($data['bpjs_health_number']) || ! empty($data['bpjs_employment_number']) ? 'bpjs' : null,
                    ])),
                ])
                ->log('Employee master created.');

            return $employee->load('currentEmployment.branch', 'currentEmployment.department', 'currentEmployment.position');
        });
    }

    /** @param array<string, mixed> $data */
    public function updateIdentity(User $actor, Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($actor, $employee, $data): Employee {
            $nik = $this->nullableString($data['nik'] ?? null) ?? $employee->nik;
            $employee->update([
                'full_name' => trim((string) $data['full_name']),
                'nik' => $nik,
                'nik_last_four' => SensitiveValue::lastFour($nik),
                'nik_blind_index' => SensitiveValue::blindIndex($nik, 'employee.nik'),
                'birth_place' => $this->nullableString($data['birth_place'] ?? null),
                'birth_date' => $this->nullableString($data['birth_date'] ?? null),
                'gender' => $this->nullableString($data['gender'] ?? null),
                'marital_status' => $this->nullableString($data['marital_status'] ?? null),
                'personal_email' => $this->nullableString($data['personal_email'] ?? null),
                'company_email' => $this->nullableString($data['company_email'] ?? null),
                'status' => (string) $data['status'],
                'updated_by' => $actor->getKey(),
            ]);

            $effectiveFrom = (string) $data['effective_from'];
            $this->replaceContact($employee, $actor, 'phone', $data['phone'] ?? null, $effectiveFrom);
            $this->replaceContact($employee, $actor, 'address', $data['address'] ?? null, $effectiveFrom);

            activity('employee')
                ->causedBy($actor)
                ->performedOn($employee)
                ->event('employee_identity_updated')
                ->withProperties([
                    'legal_entity_public_id' => $employee->legalEntity()->value('public_id'),
                    'changed_fields' => array_values(array_diff(array_keys($employee->getChanges()), ['nik'])),
                ])
                ->log('Employee identity or contact data updated.');

            return $employee->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function changeAssignment(User $actor, Employee $employee, LegalEntity $targetEntity, array $data): EmploymentHistory
    {
        return DB::transaction(function () use ($actor, $employee, $targetEntity, $data): EmploymentHistory {
            if (! $this->scope->manages($actor, (int) $employee->legal_entity_id)
                || ! $this->scope->manages($actor, (int) $targetEntity->getKey())) {
                throw ValidationException::withMessages(['legal_entity' => __('employee.validation.transfer_scope')]);
            }

            $effectiveFrom = (string) $data['effective_from'];
            $references = $this->assignmentReferences($targetEntity, $data, $employee);
            $histories = EmploymentHistory::query()
                ->where('employee_id', $employee->getKey())
                ->lockForUpdate()
                ->orderBy('effective_from')
                ->get();

            if ($histories->contains(fn (EmploymentHistory $history): bool => $this->rawDate($history, 'effective_from') === $effectiveFrom)) {
                throw ValidationException::withMessages(['effective_from' => __('employee.validation.duplicate_effective_date')]);
            }

            $first = $histories->first();
            if ($first && $effectiveFrom < $this->rawDate($first, 'join_date')) {
                throw ValidationException::withMessages(['effective_from' => __('employee.validation.before_join_date')]);
            }

            $next = $histories->first(fn (EmploymentHistory $history): bool => $this->rawDate($history, 'effective_from') > $effectiveFrom);
            $covering = $histories->last(function (EmploymentHistory $history) use ($effectiveFrom): bool {
                $effectiveTo = $this->rawDate($history, 'effective_to');

                return $this->rawDate($history, 'effective_from') < $effectiveFrom
                    && ($effectiveTo === null || $effectiveTo > $effectiveFrom);
            });

            if ($covering) {
                if (! $this->scope->manages($actor, (int) $covering->legal_entity_id)) {
                    throw ValidationException::withMessages(['legal_entity' => __('employee.validation.transfer_scope')]);
                }

                $covering->update(['effective_to' => $effectiveFrom]);
            }

            $history = EmploymentHistory::query()->create([
                'legal_entity_id' => $targetEntity->getKey(),
                'employee_id' => $employee->getKey(),
                'employee_number' => trim((string) $data['employee_number']),
                ...$references,
                'employment_status' => (string) $data['employment_status'],
                'join_date' => $first ? $this->rawDate($first, 'join_date') : $effectiveFrom,
                'termination_date' => $this->nullableString($data['termination_date'] ?? null),
                'effective_from' => $effectiveFrom,
                'effective_to' => $next ? $this->rawDate($next, 'effective_from') : null,
                'change_reason' => trim((string) $data['change_reason']),
                'source' => 'manual',
                'created_by' => $actor->getKey(),
            ]);

            $historyEffectiveTo = $this->rawDate($history, 'effective_to');
            if ($effectiveFrom <= now()->toDateString() && ($historyEffectiveTo === null || $historyEffectiveTo > now()->toDateString())) {
                $employee->update([
                    'legal_entity_id' => $targetEntity->getKey(),
                    'employee_number' => $history->employee_number,
                    'updated_by' => $actor->getKey(),
                ]);
            }

            activity('employee')
                ->causedBy($actor)
                ->performedOn($employee)
                ->event('employment_assignment_created')
                ->withProperties([
                    'legal_entity_public_id' => $targetEntity->public_id,
                    'employment_history_public_id' => $history->public_id,
                    'target_legal_entity_public_id' => $targetEntity->public_id,
                    'effective_from' => $effectiveFrom,
                    'changed_dimensions' => ['legal_entity', 'branch', 'division', 'department', 'position', 'manager', 'employment_status'],
                ])
                ->log('Effective-dated employment assignment created.');

            return $history;
        });
    }

    /** @param array<string, mixed> $data */
    public function addContract(User $actor, Employee $employee, array $data): Contract
    {
        return DB::transaction(function () use ($actor, $employee, $data): Contract {
            Contract::query()
                ->where('employee_id', $employee->getKey())
                ->where('legal_entity_id', $employee->legal_entity_id)
                ->where('status', ContractStatus::Active->value)
                ->whereDate('start_date', '<=', (string) $data['start_date'])
                ->lockForUpdate()
                ->update(['status' => ContractStatus::Superseded->value]);

            $contract = Contract::query()->create([
                'legal_entity_id' => $employee->legal_entity_id,
                'employee_id' => $employee->getKey(),
                'contract_type' => (string) $data['contract_type'],
                'contract_number' => trim((string) $data['contract_number']),
                'start_date' => (string) $data['start_date'],
                'end_date' => $this->nullableString($data['end_date'] ?? null),
                'probation_end_date' => $this->nullableString($data['probation_end_date'] ?? null),
                'status' => ContractStatus::Active->value,
                'change_reason' => trim((string) $data['change_reason']),
                'source' => 'manual',
                'created_by' => $actor->getKey(),
            ]);

            activity('employee')
                ->causedBy($actor)
                ->performedOn($employee)
                ->event('contract_created')
                ->withProperties([
                    'legal_entity_public_id' => $employee->legalEntity()->value('public_id'),
                    'contract_public_id' => $contract->public_id,
                    'contract_type' => $contract->contract_type,
                    'start_date' => $this->rawDate($contract, 'start_date'),
                    'end_date' => $this->rawDate($contract, 'end_date'),
                ])
                ->log('Employee contract history created.');

            return $contract;
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, int|null>
     */
    private function assignmentReferences(LegalEntity $entity, array $data, ?Employee $employee = null): array
    {
        $branch = $this->resolveInEntity(Branch::class, $entity, $data['branch_public_id'] ?? null);
        $division = $this->resolveOptionalInEntity(Division::class, $entity, $data['division_public_id'] ?? null);
        $department = $this->resolveInEntity(Department::class, $entity, $data['department_public_id'] ?? null);
        $position = $this->resolveInEntity(Position::class, $entity, $data['position_public_id'] ?? null);
        $workLocation = $this->resolveOptionalInEntity(WorkLocation::class, $entity, $data['work_location_public_id'] ?? null);
        $costCenter = $this->resolveOptionalInEntity(CostCenter::class, $entity, $data['cost_center_public_id'] ?? null);

        if ((int) $department->getAttribute('branch_id') !== (int) $branch->getKey()
            || ($division && (int) $department->getAttribute('division_id') !== (int) $division->getKey())
            || (int) $position->getAttribute('department_id') !== (int) $department->getKey()
            || ($workLocation && $workLocation->getAttribute('branch_id') !== null
                && (int) $workLocation->getAttribute('branch_id') !== (int) $branch->getKey())) {
            throw ValidationException::withMessages(['organization' => __('employee.validation.invalid_hierarchy')]);
        }

        $manager = $this->resolveOptionalInEntity(Employee::class, $entity, $data['manager_public_id'] ?? null);
        if ($employee && $manager?->is($employee)) {
            throw ValidationException::withMessages(['manager_public_id' => __('employee.validation.self_manager')]);
        }

        return [
            'branch_id' => (int) $branch->getKey(),
            'division_id' => $division?->getKey(),
            'department_id' => (int) $department->getKey(),
            'position_id' => (int) $position->getKey(),
            'work_location_id' => $workLocation?->getKey(),
            'cost_center_id' => $costCenter?->getKey(),
            'manager_employee_id' => $manager?->getKey(),
        ];
    }

    private function recordContact(Employee $employee, User $actor, string $type, mixed $value, string $effectiveFrom): void
    {
        $normalized = $this->nullableString($value);
        if ($normalized === null) {
            return;
        }

        EmployeeContact::query()->create([
            'legal_entity_id' => $employee->legal_entity_id,
            'employee_id' => $employee->getKey(),
            'type' => $type,
            'value' => $normalized,
            'is_primary' => true,
            'effective_from' => $effectiveFrom,
            'created_by' => $actor->getKey(),
        ]);
    }

    private function replaceContact(Employee $employee, User $actor, string $type, mixed $value, string $effectiveFrom): void
    {
        $latest = EmployeeContact::query()
            ->where('employee_id', $employee->getKey())
            ->where('legal_entity_id', $employee->legal_entity_id)
            ->where('type', $type)
            ->whereDate('effective_from', '<', $effectiveFrom)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>', $effectiveFrom))
            ->lockForUpdate()
            ->latest('effective_from')
            ->first();
        $latest?->update(['effective_to' => $effectiveFrom]);
        $this->recordContact($employee, $actor, $type, $value, $effectiveFrom);
    }

    /** @param array<string, mixed> $data */
    private function recordEmergencyContact(Employee $employee, User $actor, array $data, string $effectiveFrom): void
    {
        if (empty($data['emergency_name']) || empty($data['emergency_phone'])) {
            return;
        }

        EmergencyContact::query()->create([
            'legal_entity_id' => $employee->legal_entity_id,
            'employee_id' => $employee->getKey(),
            'name' => trim((string) $data['emergency_name']),
            'relationship' => trim((string) $data['emergency_relationship']),
            'phone' => trim((string) $data['emergency_phone']),
            'address' => $this->nullableString($data['emergency_address'] ?? null),
            'priority' => 1,
            'effective_from' => $effectiveFrom,
            'created_by' => $actor->getKey(),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function recordInitialContract(Employee $employee, User $actor, array $data): void
    {
        if (empty($data['contract_number']) || empty($data['contract_type'])) {
            return;
        }

        Contract::query()->create([
            'legal_entity_id' => $employee->legal_entity_id,
            'employee_id' => $employee->getKey(),
            'contract_type' => (string) $data['contract_type'],
            'contract_number' => trim((string) $data['contract_number']),
            'start_date' => (string) ($data['contract_start_date'] ?? $data['effective_from']),
            'end_date' => $this->nullableString($data['contract_end_date'] ?? null),
            'probation_end_date' => $this->nullableString($data['probation_end_date'] ?? null),
            'status' => ContractStatus::Active->value,
            'change_reason' => 'Initial contract record.',
            'source' => 'manual',
            'created_by' => $actor->getKey(),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function recordSensitiveProfiles(Employee $employee, User $actor, array $data, string $effectiveFrom): void
    {
        $bankAccount = $this->nullableString($data['bank_account_number'] ?? null);
        if ($bankAccount && ! empty($data['bank_account_holder'])) {
            EmployeeBankAccount::query()->create([
                'legal_entity_id' => $employee->legal_entity_id,
                'employee_id' => $employee->getKey(),
                'bank_code' => (string) ($data['bank_code'] ?? 'BCA'),
                'bank_name' => (string) ($data['bank_name'] ?? 'Bank Central Asia'),
                'account_number' => $bankAccount,
                'account_number_last_four' => SensitiveValue::lastFour($bankAccount),
                'account_number_blind_index' => SensitiveValue::blindIndex($bankAccount, 'employee.bank_account'),
                'account_holder_name' => trim((string) $data['bank_account_holder']),
                'verification_status' => 'pending',
                'effective_from' => $effectiveFrom,
                'created_by' => $actor->getKey(),
            ]);
        }

        $taxIdentifier = $this->nullableString($data['tax_identifier'] ?? null);
        if ($taxIdentifier || ! empty($data['ptkp_code'])) {
            EmployeeTaxProfile::query()->create([
                'legal_entity_id' => $employee->legal_entity_id,
                'employee_id' => $employee->getKey(),
                'tax_identifier' => $taxIdentifier,
                'tax_identifier_last_four' => SensitiveValue::lastFour($taxIdentifier),
                'tax_identifier_blind_index' => SensitiveValue::blindIndex($taxIdentifier, 'employee.tax_identifier'),
                'ptkp_code' => $this->nullableString($data['ptkp_code'] ?? null),
                'tax_method' => 'gross',
                'verification_status' => 'pending',
                'effective_from' => $effectiveFrom,
                'created_by' => $actor->getKey(),
            ]);
        }

        $healthNumber = $this->nullableString($data['bpjs_health_number'] ?? null);
        $employmentNumber = $this->nullableString($data['bpjs_employment_number'] ?? null);
        if ($healthNumber || $employmentNumber) {
            EmployeeBpjsProfile::query()->create([
                'legal_entity_id' => $employee->legal_entity_id,
                'employee_id' => $employee->getKey(),
                'health_number' => $healthNumber,
                'health_number_last_four' => SensitiveValue::lastFour($healthNumber),
                'health_number_blind_index' => SensitiveValue::blindIndex($healthNumber, 'employee.bpjs_health'),
                'employment_number' => $employmentNumber,
                'employment_number_last_four' => SensitiveValue::lastFour($employmentNumber),
                'employment_number_blind_index' => SensitiveValue::blindIndex($employmentNumber, 'employee.bpjs_employment'),
                'jkk_risk_category' => $this->nullableString($data['jkk_risk_category'] ?? null),
                'verification_status' => 'pending',
                'effective_from' => $effectiveFrom,
                'created_by' => $actor->getKey(),
            ]);
        }
    }

    /** @param class-string<Model> $modelClass */
    private function resolveInEntity(string $modelClass, LegalEntity $entity, mixed $publicId): Model
    {
        $model = $this->resolveOptionalInEntity($modelClass, $entity, $publicId);

        if (! $model) {
            throw ValidationException::withMessages(['organization' => __('employee.validation.invalid_hierarchy')]);
        }

        return $model;
    }

    /** @param class-string<Model> $modelClass */
    private function resolveOptionalInEntity(string $modelClass, LegalEntity $entity, mixed $publicId): ?Model
    {
        if (! is_string($publicId) || $publicId === '') {
            return null;
        }

        return $modelClass::query()
            ->where('legal_entity_id', $entity->getKey())
            ->where('public_id', $publicId)
            ->first();
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function rawDate(Model $model, string $attribute): ?string
    {
        $value = $model->getRawOriginal($attribute);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
