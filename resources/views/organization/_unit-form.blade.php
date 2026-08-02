<details class="surface-muted rounded-xl p-4">
    <summary class="cursor-pointer text-sm font-bold text-primary">{{ __('organization.add_unit', ['unit' => $label]) }}</summary>
    <form method="POST" action="{{ route('organization.units.store', [$entity->public_id, $type]) }}" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @csrf
        <x-form.input name="code" :label="__('organization.code')" maxlength="32" required />
        <x-form.input name="name" :label="__('organization.name')" maxlength="255" required />

        @if (in_array($type, ['departments', 'work-locations'], true))
            <x-form.select name="branch_public_id" :label="__('organization.branch')" :required="$type === 'departments'">
                <option value="">{{ __('organization.select', ['item' => __('organization.branch')]) }}</option>
                @foreach ($entity->branches as $branch)
                    <option value="{{ $branch->public_id }}">{{ $branch->code }} · {{ $branch->name }}</option>
                @endforeach
            </x-form.select>
        @endif

        @if ($type === 'departments')
            <x-form.select name="division_public_id" :label="__('organization.division')">
                <option value="">{{ __('organization.optional') }}</option>
                @foreach ($entity->divisions as $division)
                    <option value="{{ $division->public_id }}">{{ $division->code }} · {{ $division->name }}</option>
                @endforeach
            </x-form.select>
        @endif

        @if ($type === 'positions')
            <x-form.select name="department_public_id" :label="__('organization.department')" required>
                <option value="">{{ __('organization.select', ['item' => __('organization.department')]) }}</option>
                @foreach ($entity->departments as $department)
                    <option value="{{ $department->public_id }}">{{ $department->code }} · {{ $department->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="level" :label="__('organization.level')" maxlength="64" />
        @endif

        @if (in_array($type, ['branches', 'work-locations'], true))
            <x-form.input name="address" :label="__('organization.address')" maxlength="500" />
            <x-form.input name="timezone" :label="__('organization.timezone')" :value="$entity->timezone" required />
        @endif

        @if ($type === 'cost-centers')
            <x-form.input name="external_code" :label="__('organization.external_code')" maxlength="64" />
        @endif

        <x-form.select name="status" :label="__('organization.status_label')" required>
            <option value="active">{{ __('organization.active') }}</option>
            <option value="inactive">{{ __('organization.inactive') }}</option>
        </x-form.select>

        <div class="flex items-end">
            <x-button type="submit">{{ __('ui.actions.save') }}</x-button>
        </div>
    </form>
</details>
