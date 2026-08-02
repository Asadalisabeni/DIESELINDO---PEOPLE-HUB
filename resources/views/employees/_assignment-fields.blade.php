<x-form.select name="legal_entity_public_id" :label="__('employee.legal_entity')" required>
    <option value="">{{ __('employee.select', ['item' => __('employee.legal_entity')]) }}</option>
    @foreach ($legalEntities as $entity)
        <option value="{{ $entity->public_id }}" @selected(old('legal_entity_public_id', $selectedEntity ?? null) === $entity->public_id)>{{ $entity->code }} · {{ $entity->display_name }}</option>
    @endforeach
</x-form.select>

<x-form.input name="employee_number" :label="__('employee.employee_number')" :value="old('employee_number', $employeeNumber ?? null)" maxlength="64" required />

<x-form.select name="branch_public_id" :label="__('employee.branch')" required>
    <option value="">{{ __('employee.select', ['item' => __('employee.branch')]) }}</option>
    @foreach ($legalEntities as $entity)
        <optgroup label="{{ $entity->display_name }}">
            @foreach ($entity->branches as $branch)
                <option value="{{ $branch->public_id }}" @selected(old('branch_public_id') === $branch->public_id)>{{ $branch->code }} · {{ $branch->name }}</option>
            @endforeach
        </optgroup>
    @endforeach
</x-form.select>

<x-form.select name="division_public_id" :label="__('employee.division')">
    <option value="">{{ __('employee.optional') }}</option>
    @foreach ($legalEntities as $entity)
        <optgroup label="{{ $entity->display_name }}">
            @foreach ($entity->divisions as $division)
                <option value="{{ $division->public_id }}" @selected(old('division_public_id') === $division->public_id)>{{ $division->code }} · {{ $division->name }}</option>
            @endforeach
        </optgroup>
    @endforeach
</x-form.select>

<x-form.select name="department_public_id" :label="__('employee.department')" required>
    <option value="">{{ __('employee.select', ['item' => __('employee.department')]) }}</option>
    @foreach ($legalEntities as $entity)
        <optgroup label="{{ $entity->display_name }}">
            @foreach ($entity->departments as $department)
                <option value="{{ $department->public_id }}" @selected(old('department_public_id') === $department->public_id)>{{ $department->code }} · {{ $department->name }}</option>
            @endforeach
        </optgroup>
    @endforeach
</x-form.select>

<x-form.select name="position_public_id" :label="__('employee.position')" required>
    <option value="">{{ __('employee.select', ['item' => __('employee.position')]) }}</option>
    @foreach ($legalEntities as $entity)
        <optgroup label="{{ $entity->display_name }}">
            @foreach ($entity->positions as $position)
                <option value="{{ $position->public_id }}" @selected(old('position_public_id') === $position->public_id)>{{ $position->code }} · {{ $position->name }}</option>
            @endforeach
        </optgroup>
    @endforeach
</x-form.select>

<x-form.select name="work_location_public_id" :label="__('employee.work_location')">
    <option value="">{{ __('employee.optional') }}</option>
    @foreach ($legalEntities as $entity)
        <optgroup label="{{ $entity->display_name }}">
            @foreach ($entity->workLocations as $location)
                <option value="{{ $location->public_id }}" @selected(old('work_location_public_id') === $location->public_id)>{{ $location->code }} · {{ $location->name }}</option>
            @endforeach
        </optgroup>
    @endforeach
</x-form.select>

<x-form.select name="cost_center_public_id" :label="__('employee.cost_center')">
    <option value="">{{ __('employee.optional') }}</option>
    @foreach ($legalEntities as $entity)
        <optgroup label="{{ $entity->display_name }}">
            @foreach ($entity->costCenters as $costCenter)
                <option value="{{ $costCenter->public_id }}" @selected(old('cost_center_public_id') === $costCenter->public_id)>{{ $costCenter->code }} · {{ $costCenter->name }}</option>
            @endforeach
        </optgroup>
    @endforeach
</x-form.select>

<x-form.select name="manager_public_id" :label="__('employee.manager')">
    <option value="">{{ __('employee.optional') }}</option>
    @foreach ($legalEntities as $entity)
        <optgroup label="{{ $entity->display_name }}">
            @foreach ($entity->employees as $manager)
                @if (! isset($employee) || ! $manager->is($employee))
                    <option value="{{ $manager->public_id }}" @selected(old('manager_public_id') === $manager->public_id)>{{ $manager->employee_number }} · {{ $manager->full_name }}</option>
                @endif
            @endforeach
        </optgroup>
    @endforeach
</x-form.select>

<x-form.select name="employment_status" :label="__('employee.employment_status')" required>
    <option value="permanent" @selected(old('employment_status') === 'permanent')>{{ __('employee.permanent') }}</option>
    <option value="fixed_term" @selected(old('employment_status') === 'fixed_term')>{{ __('employee.fixed_term') }}</option>
</x-form.select>
