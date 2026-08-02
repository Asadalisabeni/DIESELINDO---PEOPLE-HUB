<?php

namespace App\Services\Employee;

use App\Enums\DocumentStatus;
use App\Enums\ProfileChangeStatus;
use App\Enums\ProfileChangeType;
use App\Models\EmergencyContact;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeBpjsProfile;
use App\Models\EmployeeContact;
use App\Models\EmployeeDocument;
use App\Models\EmployeeFamilyMember;
use App\Models\EmployeeProfileChangeRequest;
use App\Models\EmployeeTaxProfile;
use App\Models\User;
use App\Notifications\EmployeeProfileChangeReviewed;
use App\Notifications\EmployeeProfileChangeSubmitted;
use App\Services\Organization\LegalEntityScope;
use App\Support\Security\SensitiveValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;
use Throwable;

class EmployeeSelfServiceManager
{
    public function __construct(private readonly LegalEntityScope $scope) {}

    /** @param array<string, mixed> $data */
    public function updateDirectProfile(User $actor, Employee $employee, array $data): Employee
    {
        $this->assertOwner($actor, $employee);

        return DB::transaction(function () use ($actor, $employee, $data): Employee {
            $lockedEmployee = Employee::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();
            $today = now()->toDateString();
            $before = $this->directContactSnapshot($lockedEmployee, $today);

            $this->replaceContact($lockedEmployee, $actor, 'phone', trim((string) $data['phone']), $today);
            $this->replaceContact($lockedEmployee, $actor, 'address', trim((string) $data['address']), $today);
            $this->replaceEmergencyContact($lockedEmployee, $actor, $data, $today);

            $after = $this->directContactSnapshot($lockedEmployee, $today);
            $changedFields = array_keys(array_filter(
                $after,
                static fn (mixed $value, string $key): bool => ($before[$key] ?? null) !== $value,
                ARRAY_FILTER_USE_BOTH,
            ));

            activity('employee')
                ->causedBy($actor)
                ->performedOn($lockedEmployee)
                ->event('ess_contact_updated')
                ->withProperties([
                    'legal_entity_public_id' => $lockedEmployee->legalEntity()->value('public_id'),
                    'employee_public_id' => $lockedEmployee->public_id,
                    'changed_fields' => $changedFields,
                    'effective_from' => $today,
                ])
                ->log('Employee updated direct self-service contact fields.');

            return $lockedEmployee->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function submitChangeRequest(
        User $actor,
        Employee $employee,
        array $data,
        ?UploadedFile $attachment = null,
    ): EmployeeProfileChangeRequest {
        $this->assertOwner($actor, $employee);
        $type = ProfileChangeType::from((string) $data['request_type']);
        $proposed = $this->proposedValues($type, $data, $attachment);
        $attachmentMetadata = $attachment ? $this->storeAttachmentFile($employee, $attachment) : null;

        try {
            return DB::transaction(function () use ($actor, $employee, $data, $type, $proposed, $attachment, $attachmentMetadata): EmployeeProfileChangeRequest {
                Employee::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();

                $pendingExists = EmployeeProfileChangeRequest::query()
                    ->where('employee_id', $employee->getKey())
                    ->where('type', $type->value)
                    ->where('status', ProfileChangeStatus::Pending->value)
                    ->lockForUpdate()
                    ->exists();

                if ($pendingExists) {
                    throw ValidationException::withMessages([
                        'request_type' => __('ess.validation.pending_exists'),
                    ]);
                }

                $document = $attachment && $attachmentMetadata
                    ? $this->createAttachmentDocument($actor, $employee, $attachment, $attachmentMetadata)
                    : null;
                $current = $this->currentSnapshot($employee, $type, $proposed);
                $changeRequest = EmployeeProfileChangeRequest::query()->create([
                    'legal_entity_id' => $employee->legal_entity_id,
                    'employee_id' => $employee->getKey(),
                    'requested_by' => $actor->getKey(),
                    'type' => $type->value,
                    'status' => ProfileChangeStatus::Pending->value,
                    'current_values' => $current,
                    'proposed_values' => $proposed,
                    'snapshot_fingerprint' => $this->fingerprint($current),
                    'reason' => trim((string) $data['reason']),
                    'attachment_document_id' => $document?->getKey(),
                    'manual_follow_up_required' => $type->requiresManualFollowUp(),
                    'submitted_at' => now(),
                ]);

                $changeRequest->setRelation('employee', $employee);
                Notification::send($this->reviewersFor($employee), new EmployeeProfileChangeSubmitted($changeRequest));

                activity('employee')
                    ->causedBy($actor)
                    ->performedOn($employee)
                    ->event('ess_profile_change_submitted')
                    ->withProperties([
                        'legal_entity_public_id' => $employee->legalEntity()->value('public_id'),
                        'employee_public_id' => $employee->public_id,
                        'request_public_id' => $changeRequest->public_id,
                        'request_type' => $type->value,
                        'has_attachment' => $document !== null,
                    ])
                    ->log('Sensitive employee profile change submitted for review.');

                return $changeRequest;
            });
        } catch (Throwable $exception) {
            if ($attachmentMetadata) {
                Storage::disk('local')->delete($attachmentMetadata['path']);
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function review(
        User $reviewer,
        EmployeeProfileChangeRequest $changeRequest,
        array $data,
    ): EmployeeProfileChangeRequest {
        if (! $reviewer->can('ess.profile-change.review')
            || ! $this->scope->manages($reviewer, (int) $changeRequest->legal_entity_id)) {
            abort(403);
        }

        return DB::transaction(function () use ($reviewer, $changeRequest, $data): EmployeeProfileChangeRequest {
            $locked = EmployeeProfileChangeRequest::query()
                ->with(['employee', 'requester', 'attachmentDocument'])
                ->whereKey($changeRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->changeStatus() !== ProfileChangeStatus::Pending) {
                throw ValidationException::withMessages(['decision' => __('ess.validation.already_reviewed')]);
            }

            $employee = $locked->employee;
            abort_unless($employee instanceof Employee, 404);
            $requester = $locked->requester;
            abort_unless($requester instanceof User, 404);
            $type = $locked->changeType();

            $decision = (string) $data['decision'];
            if ($decision === 'approve') {
                $proposed = $locked->proposedValues();
                $current = $this->currentSnapshot($employee, $type, $proposed);
                if (! hash_equals($locked->snapshot_fingerprint, $this->fingerprint($current))) {
                    throw ValidationException::withMessages(['decision' => __('ess.validation.stale_snapshot')]);
                }

                $this->applyApprovedChange($reviewer, $locked, $proposed);
                $locked->setAttribute('status', ProfileChangeStatus::Approved->value);
                $locked->applied_at = $locked->manual_follow_up_required ? null : now();
                if ($locked->attachmentDocument) {
                    $locked->attachmentDocument->update([
                        'type' => $type === ProfileChangeType::IdentityDocument
                            ? (string) ($proposed['document_type'] ?? 'identity_document')
                            : 'ess_'.$type->value.'_evidence',
                        'status' => DocumentStatus::Valid->value,
                    ]);
                }
            } else {
                $locked->setAttribute('status', ProfileChangeStatus::Rejected->value);
                $locked->attachmentDocument?->update(['status' => DocumentStatus::Archived->value]);
            }

            $locked->reviewed_by = $reviewer->getKey();
            $locked->review_notes = trim((string) $data['review_notes']);
            $locked->reviewed_at = now();
            $locked->save();

            $requester->notify(new EmployeeProfileChangeReviewed($locked));
            $status = $locked->changeStatus();

            activity('employee')
                ->causedBy($reviewer)
                ->performedOn($employee)
                ->event('ess_profile_change_'.$status->value)
                ->withProperties([
                    'legal_entity_public_id' => $employee->legalEntity()->value('public_id'),
                    'employee_public_id' => $employee->public_id,
                    'request_public_id' => $locked->public_id,
                    'request_type' => $type->value,
                    'manual_follow_up_required' => $locked->manual_follow_up_required,
                ])
                ->log('Sensitive employee profile change reviewed.');

            return $locked->refresh();
        });
    }

    public function cancel(User $actor, EmployeeProfileChangeRequest $changeRequest): EmployeeProfileChangeRequest
    {
        return DB::transaction(function () use ($actor, $changeRequest): EmployeeProfileChangeRequest {
            $locked = EmployeeProfileChangeRequest::query()
                ->with(['employee', 'attachmentDocument'])
                ->whereKey($changeRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $locked->getAttribute('requested_by') !== (int) $actor->getKey()) {
                abort(403);
            }

            if ($locked->changeStatus() !== ProfileChangeStatus::Pending) {
                throw ValidationException::withMessages(['request' => __('ess.validation.already_reviewed')]);
            }

            $employee = $locked->employee;
            abort_unless($employee instanceof Employee, 404);
            $type = $locked->changeType();

            $locked->update([
                'status' => ProfileChangeStatus::Cancelled->value,
                'cancelled_at' => now(),
            ]);
            $locked->attachmentDocument?->update(['status' => DocumentStatus::Archived->value]);

            activity('employee')
                ->causedBy($actor)
                ->performedOn($employee)
                ->event('ess_profile_change_cancelled')
                ->withProperties([
                    'legal_entity_public_id' => $employee->legalEntity()->value('public_id'),
                    'employee_public_id' => $employee->public_id,
                    'request_public_id' => $locked->public_id,
                    'request_type' => $type->value,
                ])
                ->log('Pending employee profile change cancelled by requester.');

            return $locked->refresh();
        });
    }

    /** @param array<string, mixed>|null $values
     * @return array<string, mixed>
     */
    public function presentValues(ProfileChangeType $type, ?array $values): array
    {
        $presented = $values ?? [];
        $sensitiveKeys = [
            'account_number', 'tax_identifier', 'health_number', 'employment_number', 'identity_number',
        ];

        foreach ($sensitiveKeys as $key) {
            if (array_key_exists($key, $presented)) {
                $presented[$key] = SensitiveValue::mask(SensitiveValue::lastFour(
                    is_string($presented[$key]) ? $presented[$key] : null,
                ));
            }
        }

        return $presented;
    }

    /** @param array<string, mixed> $data */
    private function replaceEmergencyContact(Employee $employee, User $actor, array $data, string $effectiveFrom): void
    {
        $sameDay = EmergencyContact::query()
            ->where('employee_id', $employee->getKey())
            ->where('legal_entity_id', $employee->legal_entity_id)
            ->where('priority', 1)
            ->whereDate('effective_from', $effectiveFrom)
            ->lockForUpdate()
            ->first();
        $values = [
            'name' => trim((string) $data['emergency_name']),
            'relationship' => trim((string) $data['emergency_relationship']),
            'phone' => trim((string) $data['emergency_phone']),
            'address' => $this->nullableString($data['emergency_address'] ?? null),
        ];

        if ($sameDay) {
            $sameDay->update($values);

            return;
        }

        $this->closeCurrent(EmergencyContact::query()
            ->where('employee_id', $employee->getKey())
            ->where('legal_entity_id', $employee->legal_entity_id)
            ->where('priority', 1), $effectiveFrom);

        EmergencyContact::query()->create($values + [
            'legal_entity_id' => $employee->legal_entity_id,
            'employee_id' => $employee->getKey(),
            'priority' => 1,
            'effective_from' => $effectiveFrom,
            'created_by' => $actor->getKey(),
        ]);
    }

    private function replaceContact(Employee $employee, User $actor, string $type, string $value, string $effectiveFrom): void
    {
        $sameDay = EmployeeContact::query()
            ->where('employee_id', $employee->getKey())
            ->where('legal_entity_id', $employee->legal_entity_id)
            ->where('type', $type)
            ->whereDate('effective_from', $effectiveFrom)
            ->lockForUpdate()
            ->first();

        if ($sameDay) {
            $sameDay->update(['value' => $value, 'is_primary' => true]);

            return;
        }

        $this->closeCurrent(EmployeeContact::query()
            ->where('employee_id', $employee->getKey())
            ->where('legal_entity_id', $employee->legal_entity_id)
            ->where('type', $type), $effectiveFrom);

        EmployeeContact::query()->create([
            'legal_entity_id' => $employee->legal_entity_id,
            'employee_id' => $employee->getKey(),
            'type' => $type,
            'value' => $value,
            'is_primary' => true,
            'effective_from' => $effectiveFrom,
            'created_by' => $actor->getKey(),
        ]);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function closeCurrent(Builder $query, string $effectiveFrom): void
    {
        $query
            ->whereDate('effective_from', '<', $effectiveFrom)
            ->where(fn (Builder $builder) => $builder->whereNull('effective_to')->orWhereDate('effective_to', '>', $effectiveFrom))
            ->lockForUpdate()
            ->latest('effective_from')
            ->first()
            ?->update(['effective_to' => $effectiveFrom]);
    }

    /** @return array<string, mixed> */
    private function directContactSnapshot(Employee $employee, string $date): array
    {
        $contacts = EmployeeContact::query()
            ->where('employee_id', $employee->getKey())
            ->where('legal_entity_id', $employee->legal_entity_id)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>', $date))
            ->get()
            ->keyBy('type');
        $emergency = EmergencyContact::query()
            ->where('employee_id', $employee->getKey())
            ->where('legal_entity_id', $employee->legal_entity_id)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>', $date))
            ->orderBy('priority')
            ->first();

        return [
            'phone' => $contacts->get('phone')?->value,
            'address' => $contacts->get('address')?->value,
            'emergency_name' => $emergency?->name,
            'emergency_relationship' => $emergency?->relationship,
            'emergency_phone' => $emergency?->phone,
            'emergency_address' => $emergency?->address,
        ];
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function proposedValues(ProfileChangeType $type, array $data, ?UploadedFile $attachment): array
    {
        $keys = match ($type) {
            ProfileChangeType::LegalName => ['full_name'],
            ProfileChangeType::MaritalStatus => ['marital_status'],
            ProfileChangeType::BankAccount => ['bank_code', 'bank_name', 'account_number', 'account_holder_name', 'effective_from'],
            ProfileChangeType::TaxProfile => ['tax_identifier', 'ptkp_code', 'effective_from'],
            ProfileChangeType::BpjsProfile => ['health_number', 'employment_number', 'jkk_risk_category', 'effective_from'],
            ProfileChangeType::FamilyData => ['family_full_name', 'relationship', 'birth_date', 'identity_number', 'effective_from'],
            ProfileChangeType::IdentityDocument => ['document_type'],
            ProfileChangeType::EmploymentData => ['requested_change', 'preferred_effective_date'],
        };
        $values = Arr::only($data, $keys);

        foreach ($values as $key => $value) {
            $values[$key] = is_string($value) ? trim($value) : $value;
        }

        if ($attachment) {
            $values['document_original_name'] = $attachment->getClientOriginalName();
        }

        return $values;
    }

    /** @param array<string, mixed> $proposed
     * @return array<string, mixed>
     */
    private function currentSnapshot(Employee $employee, ProfileChangeType $type, array $proposed): array
    {
        $date = now()->toDateString();
        $freshEmployee = Employee::query()->whereKey($employee->getKey())->firstOrFail();

        return match ($type) {
            ProfileChangeType::LegalName => ['full_name' => $freshEmployee->full_name],
            ProfileChangeType::MaritalStatus => ['marital_status' => $freshEmployee->marital_status],
            ProfileChangeType::BankAccount => $this->profileSnapshot(
                EmployeeBankAccount::class,
                $employee,
                ['bank_code', 'bank_name', 'account_number', 'account_holder_name', 'effective_from'],
                $date,
            ),
            ProfileChangeType::TaxProfile => $this->profileSnapshot(
                EmployeeTaxProfile::class,
                $employee,
                ['tax_identifier', 'ptkp_code', 'effective_from'],
                $date,
            ),
            ProfileChangeType::BpjsProfile => $this->profileSnapshot(
                EmployeeBpjsProfile::class,
                $employee,
                ['health_number', 'employment_number', 'jkk_risk_category', 'effective_from'],
                $date,
            ),
            ProfileChangeType::FamilyData => [
                'member_versions' => EmployeeFamilyMember::query()
                    ->where('employee_id', $employee->getKey())
                    ->where('legal_entity_id', $employee->legal_entity_id)
                    ->orderBy('id')
                    ->get(['public_id', 'updated_at'])
                    ->map(fn (EmployeeFamilyMember $member): array => [
                        'public_id' => $member->public_id,
                        'updated_at' => $member->updated_at?->toJSON(),
                    ])->all(),
            ],
            ProfileChangeType::IdentityDocument => [
                'document_versions' => EmployeeDocument::query()
                    ->where('employee_id', $employee->getKey())
                    ->where('legal_entity_id', $employee->legal_entity_id)
                    ->where('type', (string) ($proposed['document_type'] ?? ''))
                    ->where('status', DocumentStatus::Valid->value)
                    ->orderBy('id')
                    ->get(['public_id', 'updated_at'])
                    ->map(fn (EmployeeDocument $document): array => [
                        'public_id' => $document->public_id,
                        'updated_at' => $document->updated_at?->toJSON(),
                    ])->all(),
            ],
            ProfileChangeType::EmploymentData => $this->employmentSnapshot($employee),
        };
    }

    /** @param class-string<Model> $modelClass
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function profileSnapshot(string $modelClass, Employee $employee, array $fields, string $date): array
    {
        $profile = $modelClass::query()
            ->where('employee_id', $employee->getKey())
            ->where('legal_entity_id', $employee->legal_entity_id)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>', $date))
            ->latest('effective_from')
            ->first();

        if (! $profile) {
            return [];
        }

        $snapshot = [];
        foreach ($fields as $field) {
            $value = $profile->getAttribute($field);
            $snapshot[$field] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
        }

        return $snapshot;
    }

    /** @return array<string, mixed> */
    private function employmentSnapshot(Employee $employee): array
    {
        $history = $employee->currentEmployment()->with([
            'legalEntity:id,public_id,display_name', 'branch:id,public_id,name', 'division:id,public_id,name',
            'department:id,public_id,name', 'position:id,public_id,name', 'workLocation:id,public_id,name',
            'manager:id,public_id,full_name',
        ])->first();

        if (! $history) {
            return [];
        }

        return [
            'legal_entity' => $history->legalEntity?->display_name,
            'branch' => $history->branch?->name,
            'division' => $history->division?->name,
            'department' => $history->department?->name,
            'position' => $history->position?->name,
            'work_location' => $history->workLocation?->name,
            'manager' => $history->manager?->full_name,
            'employment_status' => (string) $history->getRawOriginal('employment_status'),
            'effective_from' => (string) $history->getRawOriginal('effective_from'),
        ];
    }

    /** @param array<string, mixed> $proposed */
    private function applyApprovedChange(User $reviewer, EmployeeProfileChangeRequest $request, array $proposed): void
    {
        $employee = Employee::query()
            ->whereKey((int) $request->getAttribute('employee_id'))
            ->lockForUpdate()
            ->firstOrFail();

        match ($request->changeType()) {
            ProfileChangeType::LegalName => $employee->update([
                'full_name' => (string) $proposed['full_name'],
                'updated_by' => $reviewer->getKey(),
            ]),
            ProfileChangeType::MaritalStatus => $employee->update([
                'marital_status' => (string) $proposed['marital_status'],
                'updated_by' => $reviewer->getKey(),
            ]),
            ProfileChangeType::BankAccount => $this->applyBankAccount($reviewer, $employee, $proposed),
            ProfileChangeType::TaxProfile => $this->applyTaxProfile($reviewer, $employee, $proposed),
            ProfileChangeType::BpjsProfile => $this->applyBpjsProfile($reviewer, $employee, $proposed),
            ProfileChangeType::FamilyData => $this->applyFamilyMember($reviewer, $employee, $proposed),
            ProfileChangeType::IdentityDocument, ProfileChangeType::EmploymentData => null,
        };
    }

    /** @param array<string, mixed> $values */
    private function applyBankAccount(User $reviewer, Employee $employee, array $values): void
    {
        $effectiveFrom = (string) $values['effective_from'];
        $accountNumber = (string) $values['account_number'];
        $blindIndex = SensitiveValue::blindIndex($accountNumber, 'employee.bank_account');

        if ($blindIndex && EmployeeBankAccount::query()
            ->where('legal_entity_id', $employee->legal_entity_id)
            ->where('account_number_blind_index', $blindIndex)
            ->where('employee_id', '!=', $employee->getKey())
            ->exists()) {
            throw ValidationException::withMessages(['decision' => __('ess.validation.duplicate_bank_account')]);
        }

        $this->assertNoSameEffectiveDate(EmployeeBankAccount::class, $employee, $effectiveFrom);
        $this->closeCurrent(EmployeeBankAccount::query()
            ->where('employee_id', $employee->getKey())
            ->where('legal_entity_id', $employee->legal_entity_id), $effectiveFrom);
        EmployeeBankAccount::query()->create([
            'legal_entity_id' => $employee->legal_entity_id,
            'employee_id' => $employee->getKey(),
            'bank_code' => (string) $values['bank_code'],
            'bank_name' => (string) $values['bank_name'],
            'account_number' => $accountNumber,
            'account_number_last_four' => SensitiveValue::lastFour($accountNumber),
            'account_number_blind_index' => $blindIndex,
            'account_holder_name' => (string) $values['account_holder_name'],
            'verification_status' => 'verified',
            'effective_from' => $effectiveFrom,
            'created_by' => $reviewer->getKey(),
        ]);
    }

    /** @param array<string, mixed> $values */
    private function applyTaxProfile(User $reviewer, Employee $employee, array $values): void
    {
        $effectiveFrom = (string) $values['effective_from'];
        $identifier = $this->nullableString($values['tax_identifier'] ?? null);
        $this->assertNoSameEffectiveDate(EmployeeTaxProfile::class, $employee, $effectiveFrom);
        $this->closeCurrent(EmployeeTaxProfile::query()
            ->where('employee_id', $employee->getKey())
            ->where('legal_entity_id', $employee->legal_entity_id), $effectiveFrom);
        EmployeeTaxProfile::query()->create([
            'legal_entity_id' => $employee->legal_entity_id,
            'employee_id' => $employee->getKey(),
            'tax_identifier' => $identifier,
            'tax_identifier_last_four' => SensitiveValue::lastFour($identifier),
            'tax_identifier_blind_index' => SensitiveValue::blindIndex($identifier, 'employee.tax_identifier'),
            'ptkp_code' => (string) $values['ptkp_code'],
            'tax_method' => 'gross',
            'verification_status' => 'verified',
            'effective_from' => $effectiveFrom,
            'created_by' => $reviewer->getKey(),
        ]);
    }

    /** @param array<string, mixed> $values */
    private function applyBpjsProfile(User $reviewer, Employee $employee, array $values): void
    {
        $effectiveFrom = (string) $values['effective_from'];
        $healthNumber = $this->nullableString($values['health_number'] ?? null);
        $employmentNumber = $this->nullableString($values['employment_number'] ?? null);
        $this->assertNoSameEffectiveDate(EmployeeBpjsProfile::class, $employee, $effectiveFrom);
        $this->closeCurrent(EmployeeBpjsProfile::query()
            ->where('employee_id', $employee->getKey())
            ->where('legal_entity_id', $employee->legal_entity_id), $effectiveFrom);
        EmployeeBpjsProfile::query()->create([
            'legal_entity_id' => $employee->legal_entity_id,
            'employee_id' => $employee->getKey(),
            'health_number' => $healthNumber,
            'health_number_last_four' => SensitiveValue::lastFour($healthNumber),
            'health_number_blind_index' => SensitiveValue::blindIndex($healthNumber, 'employee.bpjs_health'),
            'employment_number' => $employmentNumber,
            'employment_number_last_four' => SensitiveValue::lastFour($employmentNumber),
            'employment_number_blind_index' => SensitiveValue::blindIndex($employmentNumber, 'employee.bpjs_employment'),
            'jkk_risk_category' => $this->nullableString($values['jkk_risk_category'] ?? null),
            'verification_status' => 'verified',
            'effective_from' => $effectiveFrom,
            'created_by' => $reviewer->getKey(),
        ]);
    }

    /** @param array<string, mixed> $values */
    private function applyFamilyMember(User $reviewer, Employee $employee, array $values): void
    {
        $identityNumber = $this->nullableString($values['identity_number'] ?? null);
        EmployeeFamilyMember::query()->create([
            'legal_entity_id' => $employee->legal_entity_id,
            'employee_id' => $employee->getKey(),
            'full_name' => (string) $values['family_full_name'],
            'relationship' => (string) $values['relationship'],
            'birth_date' => $this->nullableString($values['birth_date'] ?? null),
            'identity_number' => $identityNumber,
            'identity_number_last_four' => SensitiveValue::lastFour($identityNumber),
            'identity_number_blind_index' => SensitiveValue::blindIndex($identityNumber, 'employee.family_identity'),
            'status' => 'active',
            'effective_from' => (string) $values['effective_from'],
            'created_by' => $reviewer->getKey(),
        ]);
    }

    /** @param class-string<Model> $modelClass */
    private function assertNoSameEffectiveDate(string $modelClass, Employee $employee, string $effectiveFrom): void
    {
        if ($modelClass::query()
            ->where('employee_id', $employee->getKey())
            ->where('legal_entity_id', $employee->legal_entity_id)
            ->whereDate('effective_from', $effectiveFrom)
            ->exists()) {
            throw ValidationException::withMessages(['decision' => __('ess.validation.duplicate_effective_date')]);
        }
    }

    /** @return array{path: string, public_id: string, checksum: string, extension: string} */
    private function storeAttachmentFile(Employee $employee, UploadedFile $file): array
    {
        $publicId = (string) Str::ulid();
        $extension = $file->guessExtension() ?: 'bin';
        $directory = 'employee-documents/'.$employee->public_id;
        $path = $directory.'/'.$publicId.'.'.$extension;
        $checksum = hash_file('sha256', $file->getRealPath());
        $stored = Storage::disk('local')->putFileAs($directory, $file, $publicId.'.'.$extension);

        if ($stored !== $path || ! is_string($checksum)) {
            if (is_string($stored)) {
                Storage::disk('local')->delete($stored);
            }

            throw new RuntimeException('Private ESS attachment storage failed.');
        }

        return [
            'path' => $path,
            'public_id' => $publicId,
            'checksum' => $checksum,
            'extension' => $extension,
        ];
    }

    /** @param array{path: string, public_id: string, checksum: string, extension: string} $metadata */
    private function createAttachmentDocument(
        User $actor,
        Employee $employee,
        UploadedFile $file,
        array $metadata,
    ): EmployeeDocument {
        return EmployeeDocument::query()->create([
            'public_id' => $metadata['public_id'],
            'legal_entity_id' => $employee->legal_entity_id,
            'employee_id' => $employee->getKey(),
            'type' => 'ess_change_evidence',
            'storage_disk' => 'local',
            'storage_path' => $metadata['path'],
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => (string) $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'checksum_sha256' => $metadata['checksum'],
            'classification' => 'restricted',
            'status' => DocumentStatus::PendingReview->value,
            'uploaded_by' => $actor->getKey(),
        ]);
    }

    /** @return Collection<int, User> */
    private function reviewersFor(Employee $employee): Collection
    {
        $today = now()->toDateString();

        return User::query()
            ->where('is_active', true)
            ->whereHas('roles.permissions', fn (Builder $query) => $query->where('name', 'ess.profile-change.review'))
            ->whereHas('legalEntityAccess', fn (Builder $query) => $query
                ->where('legal_entity_id', $employee->legal_entity_id)
                ->where('access_level', 'manage')
                ->whereDate('effective_from', '<=', $today)
                ->where(fn (Builder $period) => $period->whereNull('effective_to')->orWhereDate('effective_to', '>', $today)))
            ->get();
    }

    /** @param array<string, mixed> $values */
    private function fingerprint(array $values): string
    {
        $normalized = $this->sortRecursively($values);

        try {
            $encoded = json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to fingerprint ESS profile snapshot.', previous: $exception);
        }

        return hash_hmac('sha256', "ess.profile.snapshot\0".$encoded, (string) config('security.blind_index_key'));
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function sortRecursively(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->sortRecursively($value);
            }
        }

        ksort($values);

        return $values;
    }

    private function assertOwner(User $actor, Employee $employee): void
    {
        if ((int) $actor->employee_id !== (int) $employee->getKey()) {
            abort(403);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
