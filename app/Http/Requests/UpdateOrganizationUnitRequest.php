<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesScopedLegalEntity;
use App\Models\Branch;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Division;
use App\Models\Position;
use App\Models\WorkLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationUnitRequest extends FormRequest
{
    use ResolvesScopedLegalEntity;

    private ?Model $resolvedUnit = null;

    public function authorize(): bool
    {
        $entity = $this->resolveLegalEntity();
        $modelClass = $this->modelClass();
        if (! $entity || ! $modelClass) {
            return false;
        }

        $publicId = $this->route('unit');
        $this->resolvedUnit = is_string($publicId)
            ? $modelClass::query()->where('legal_entity_id', $entity->getKey())->where('public_id', $publicId)->first()
            : null;

        return $this->resolvedUnit !== null && $this->user()?->can('update', $entity) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    public function scopedUnit(): Model
    {
        return $this->resolvedUnit ?? abort(404);
    }

    /** @return class-string<Model>|null */
    private function modelClass(): ?string
    {
        return match ($this->route('unitType')) {
            'branches' => Branch::class,
            'divisions' => Division::class,
            'departments' => Department::class,
            'positions' => Position::class,
            'work-locations' => WorkLocation::class,
            'cost-centers' => CostCenter::class,
            default => null,
        };
    }
}
