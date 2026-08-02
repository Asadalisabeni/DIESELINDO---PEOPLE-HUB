<?php

namespace App\Services\Organization;

use App\Models\Branch;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Division;
use App\Models\LegalEntity;
use App\Models\Position;
use App\Models\User;
use App\Models\UserLegalEntityAccess;
use App\Models\WorkLocation;
use App\Support\Security\SensitiveValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganizationStructureManager
{
    public function __construct(private readonly LegalEntityScope $scope) {}

    /** @param array<string, mixed> $data */
    public function createLegalEntity(User $actor, array $data): LegalEntity
    {
        return DB::transaction(function () use ($actor, $data): LegalEntity {
            $taxIdentifier = $this->nullableString($data['tax_identifier'] ?? null);
            $entity = LegalEntity::query()->create([
                'code' => Str::upper(trim((string) $data['code'])),
                'legal_name' => trim((string) $data['legal_name']),
                'display_name' => trim((string) $data['display_name']),
                'tax_identifier' => $taxIdentifier,
                'tax_identifier_last_four' => SensitiveValue::lastFour($taxIdentifier),
                'tax_identifier_blind_index' => SensitiveValue::blindIndex($taxIdentifier, 'legal_entity.tax_identifier'),
                'address_line_1' => $this->nullableString($data['address_line_1'] ?? null),
                'address_line_2' => $this->nullableString($data['address_line_2'] ?? null),
                'city' => $this->nullableString($data['city'] ?? null),
                'province' => $this->nullableString($data['province'] ?? null),
                'postal_code' => $this->nullableString($data['postal_code'] ?? null),
                'country_code' => Str::upper((string) ($data['country_code'] ?? 'ID')),
                'timezone' => (string) ($data['timezone'] ?? 'Asia/Jakarta'),
                'currency' => Str::upper((string) ($data['currency'] ?? 'IDR')),
                'status' => (string) ($data['status'] ?? 'active'),
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);

            UserLegalEntityAccess::query()->create([
                'user_id' => $actor->getKey(),
                'legal_entity_id' => $entity->getKey(),
                'access_level' => 'manage',
                'effective_from' => now()->toDateString(),
                'granted_by' => $actor->getKey(),
                'reason' => 'Automatic scope for legal-entity creator.',
            ]);
            $this->scope->forget($actor);

            activity('organization')
                ->causedBy($actor)
                ->performedOn($entity)
                ->event('legal_entity_created')
                ->withProperties([
                    'legal_entity_public_id' => $entity->public_id,
                    'code' => $entity->code,
                    'status' => (string) $entity->getRawOriginal('status'),
                ])
                ->log('Legal entity created and creator scope granted.');

            return $entity;
        });
    }

    /** @param array<string, mixed> $data */
    public function updateLegalEntity(User $actor, LegalEntity $entity, array $data): LegalEntity
    {
        return DB::transaction(function () use ($actor, $entity, $data): LegalEntity {
            $taxIdentifier = $this->nullableString($data['tax_identifier'] ?? null) ?? $entity->tax_identifier;
            $entity->update([
                'legal_name' => trim((string) $data['legal_name']),
                'display_name' => trim((string) $data['display_name']),
                'tax_identifier' => $taxIdentifier,
                'tax_identifier_last_four' => SensitiveValue::lastFour($taxIdentifier),
                'tax_identifier_blind_index' => SensitiveValue::blindIndex($taxIdentifier, 'legal_entity.tax_identifier'),
                'address_line_1' => $this->nullableString($data['address_line_1'] ?? null),
                'address_line_2' => $this->nullableString($data['address_line_2'] ?? null),
                'city' => $this->nullableString($data['city'] ?? null),
                'province' => $this->nullableString($data['province'] ?? null),
                'postal_code' => $this->nullableString($data['postal_code'] ?? null),
                'status' => (string) $data['status'],
                'updated_by' => $actor->getKey(),
            ]);

            activity('organization')
                ->causedBy($actor)
                ->performedOn($entity)
                ->event('legal_entity_updated')
                ->withProperties([
                    'legal_entity_public_id' => $entity->public_id,
                    'changed_fields' => array_keys($entity->getChanges()),
                ])
                ->log('Legal entity updated.');

            return $entity->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function createUnit(User $actor, LegalEntity $entity, string $unitType, array $data): Model
    {
        return DB::transaction(function () use ($actor, $entity, $unitType, $data): Model {
            $attributes = [
                'legal_entity_id' => $entity->getKey(),
                'code' => Str::upper(trim((string) $data['code'])),
                'name' => trim((string) $data['name']),
                'status' => (string) ($data['status'] ?? 'active'),
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ];

            $model = match ($unitType) {
                'branches' => Branch::query()->create($attributes + [
                    'address' => $this->nullableString($data['address'] ?? null),
                    'timezone' => (string) ($data['timezone'] ?? $entity->timezone),
                ]),
                'divisions' => Division::query()->create($attributes),
                'departments' => Department::query()->create($attributes + [
                    'branch_id' => $this->resolveInEntity(Branch::class, $entity, $data['branch_public_id'] ?? null)->getKey(),
                    'division_id' => $this->resolveOptionalInEntity(Division::class, $entity, $data['division_public_id'] ?? null)?->getKey(),
                ]),
                'positions' => Position::query()->create($attributes + [
                    'department_id' => $this->resolveInEntity(Department::class, $entity, $data['department_public_id'] ?? null)->getKey(),
                    'level' => $this->nullableString($data['level'] ?? null),
                ]),
                'work-locations' => WorkLocation::query()->create($attributes + [
                    'branch_id' => $this->resolveOptionalInEntity(Branch::class, $entity, $data['branch_public_id'] ?? null)?->getKey(),
                    'address' => $this->nullableString($data['address'] ?? null),
                    'timezone' => (string) ($data['timezone'] ?? $entity->timezone),
                ]),
                'cost-centers' => CostCenter::query()->create($attributes + [
                    'external_code' => $this->nullableString($data['external_code'] ?? null),
                ]),
                default => throw ValidationException::withMessages(['unit_type' => __('organization.validation.invalid_unit_type')]),
            };

            activity('organization')
                ->causedBy($actor)
                ->performedOn($model)
                ->event('organization_unit_created')
                ->withProperties([
                    'legal_entity_public_id' => $entity->public_id,
                    'unit_type' => $unitType,
                    'code' => $model->getAttribute('code'),
                ])
                ->log('Organization unit created.');

            return $model;
        });
    }

    /** @param array<string, mixed> $data */
    public function updateUnit(User $actor, LegalEntity $entity, string $unitType, Model $unit, array $data): Model
    {
        return DB::transaction(function () use ($actor, $entity, $unitType, $unit, $data): Model {
            $unit->update([
                'name' => trim((string) $data['name']),
                'status' => (string) $data['status'],
                'updated_by' => $actor->getKey(),
            ]);

            activity('organization')
                ->causedBy($actor)
                ->performedOn($unit)
                ->event('organization_unit_updated')
                ->withProperties([
                    'legal_entity_public_id' => $entity->public_id,
                    'unit_type' => $unitType,
                    'changed_fields' => array_keys($unit->getChanges()),
                ])
                ->log('Organization unit updated or deactivated.');

            return $unit->refresh();
        });
    }

    /** @param class-string<Model> $modelClass */
    private function resolveInEntity(string $modelClass, LegalEntity $entity, mixed $publicId): Model
    {
        $model = $this->resolveOptionalInEntity($modelClass, $entity, $publicId);

        if (! $model) {
            throw ValidationException::withMessages(['organization' => __('organization.validation.invalid_hierarchy')]);
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
}
