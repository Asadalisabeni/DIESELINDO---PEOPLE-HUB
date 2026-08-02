@extends('layouts.app')

@section('title', __('organization.title'))
@section('page-title', __('organization.title'))

@section('content')
    @if (session('status'))
        <x-alert variant="success" :title="__('auth.status')" class="mb-6">{{ session('status') }}</x-alert>
    @endif
    @if ($errors->any())
        <x-alert variant="danger" :title="__('ui.design.danger_title')" class="mb-6">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </x-alert>
    @endif

    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="section-kicker">{{ __('organization.eyebrow') }}</p>
            <h1 class="mt-2 text-3xl font-bold text-primary">{{ __('organization.title') }}</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">{{ __('organization.description') }}</p>
        </div>
    </header>

    @can('create', App\Models\LegalEntity::class)
        <details class="surface-panel mt-6 rounded-2xl border p-6 shadow-panel" @if($entities->isEmpty()) open @endif>
            <summary class="cursor-pointer text-lg font-bold text-primary">{{ __('organization.create_entity') }}</summary>
            <form method="POST" action="{{ route('organization.legal-entities.store') }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @csrf
                <x-form.input name="code" :label="__('organization.code')" :value="old('code')" maxlength="32" required />
                <x-form.input name="legal_name" :label="__('organization.legal_name')" :value="old('legal_name')" required />
                <x-form.input name="display_name" :label="__('organization.display_name')" :value="old('display_name')" required />
                <x-form.input name="tax_identifier" :label="__('organization.tax_identifier')" :value="old('tax_identifier')" />
                <x-form.input name="address_line_1" :label="__('organization.address_line_1')" :value="old('address_line_1')" />
                <x-form.input name="address_line_2" :label="__('organization.address_line_2')" :value="old('address_line_2')" />
                <x-form.input name="city" :label="__('organization.city')" :value="old('city')" />
                <x-form.input name="province" :label="__('organization.province')" :value="old('province')" />
                <x-form.input name="postal_code" :label="__('organization.postal_code')" :value="old('postal_code')" />
                <x-form.input name="country_code" :label="__('organization.country_code')" value="ID" maxlength="2" required />
                <x-form.input name="timezone" :label="__('organization.timezone')" value="Asia/Jakarta" required />
                <x-form.input name="currency" :label="__('organization.currency')" value="IDR" maxlength="3" required />
                <input type="hidden" name="status" value="active">
                <div class="flex items-end"><x-button type="submit">{{ __('organization.create_entity') }}</x-button></div>
            </form>
        </details>
    @endcan

    @if ($entities->isEmpty())
        <x-state-panel variant="empty" :title="__('organization.entity_empty')" :description="__('organization.entity_empty_help')" class="mt-6 shadow-panel" />
    @endif

    <div class="mt-6 space-y-6">
        @foreach ($entities as $entity)
            <article class="surface-panel overflow-hidden rounded-2xl border shadow-panel">
                <header class="border-b border-slate-200 p-6 dark:border-slate-700">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-xl font-bold text-primary">{{ $entity->display_name }}</h2>
                                <x-badge :variant="$entity->status->value === 'active' ? 'success' : 'neutral'">{{ __('organization.'.$entity->status->value) }}</x-badge>
                            </div>
                            <p class="mt-1 text-sm text-secondary">{{ $entity->code }} · {{ $entity->legal_name }}</p>
                            <p class="mt-1 text-xs text-secondary">{{ $entity->city ?: '—' }} · {{ $entity->timezone }} · {{ $entity->currency }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach ([
                                ['branches_count', 'branches'], ['departments_count', 'departments'],
                                ['positions_count', 'positions'], ['employees_count', 'employees'],
                            ] as [$count, $label])
                                <div class="surface-muted rounded-xl px-4 py-3 text-center">
                                    <p class="text-xl font-black text-primary">{{ $entity->{$count} }}</p>
                                    <p class="text-[11px] font-bold uppercase tracking-wide text-secondary">{{ __('organization.'.$label) }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </header>

                <div class="grid gap-6 p-6 xl:grid-cols-2">
                    @foreach ([
                        ['branches', 'branches', $entity->branches], ['divisions', 'divisions', $entity->divisions],
                        ['departments', 'departments', $entity->departments], ['positions', 'positions', $entity->positions],
                        ['work_locations', 'work-locations', $entity->workLocations], ['cost_centers', 'cost-centers', $entity->costCenters],
                    ] as [$label, $unitType, $records])
                        <section>
                            <h3 class="text-sm font-bold text-primary">{{ __('organization.'.$label) }}</h3>
                            <div class="mt-3 space-y-2">
                                @forelse ($records as $record)
                                    <details class="surface-muted rounded-lg px-3 py-2">
                                        <summary class="cursor-pointer text-xs font-bold text-primary">{{ $record->code }} · {{ $record->name }} · {{ __('organization.'.$record->status->value) }}</summary>
                                        @can('update', $entity)
                                            <form method="POST" action="{{ route('organization.units.update', [$entity->public_id, $unitType, $record->public_id]) }}" class="mt-3 grid gap-3 sm:grid-cols-[1fr_160px_auto] sm:items-end">
                                                @csrf @method('PUT')
                                                <x-form.input name="name" :label="__('organization.name')" :value="$record->name" required />
                                                <x-form.select name="status" :label="__('organization.status_label')"><option value="active" @selected($record->status->value === 'active')>{{ __('organization.active') }}</option><option value="inactive" @selected($record->status->value === 'inactive')>{{ __('organization.inactive') }}</option></x-form.select>
                                                <x-button type="submit" variant="secondary">{{ __('ui.actions.save') }}</x-button>
                                            </form>
                                        @endcan
                                    </details>
                                @empty
                                    <span class="text-sm text-secondary">—</span>
                                @endforelse
                            </div>
                        </section>
                    @endforeach
                </div>

                @can('update', $entity)
                    <div class="space-y-3 border-t border-slate-200 p-6 dark:border-slate-700">
                        @include('organization._unit-form', ['entity' => $entity, 'type' => 'branches', 'label' => __('organization.branches')])
                        @include('organization._unit-form', ['entity' => $entity, 'type' => 'divisions', 'label' => __('organization.divisions')])
                        @include('organization._unit-form', ['entity' => $entity, 'type' => 'departments', 'label' => __('organization.departments')])
                        @include('organization._unit-form', ['entity' => $entity, 'type' => 'positions', 'label' => __('organization.positions')])
                        @include('organization._unit-form', ['entity' => $entity, 'type' => 'work-locations', 'label' => __('organization.work_locations')])
                        @include('organization._unit-form', ['entity' => $entity, 'type' => 'cost-centers', 'label' => __('organization.cost_centers')])

                        <details class="surface-muted rounded-xl p-4">
                            <summary class="cursor-pointer text-sm font-bold text-primary">{{ __('organization.edit_entity') }}</summary>
                            <form method="POST" action="{{ route('organization.legal-entities.update', $entity->public_id) }}" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                @csrf @method('PUT')
                                <x-form.input name="legal_name" :label="__('organization.legal_name')" :value="$entity->legal_name" required />
                                <x-form.input name="display_name" :label="__('organization.display_name')" :value="$entity->display_name" required />
                                <x-form.input name="tax_identifier" :label="__('organization.tax_identifier')" :hint="__('organization.tax_identifier_hint')" />
                                <x-form.input name="address_line_1" :label="__('organization.address_line_1')" :value="$entity->address_line_1" />
                                <x-form.input name="address_line_2" :label="__('organization.address_line_2')" :value="$entity->address_line_2" />
                                <x-form.input name="city" :label="__('organization.city')" :value="$entity->city" />
                                <x-form.input name="province" :label="__('organization.province')" :value="$entity->province" />
                                <x-form.input name="postal_code" :label="__('organization.postal_code')" :value="$entity->postal_code" />
                                <x-form.select name="status" :label="__('organization.status_label')">
                                    <option value="active" @selected($entity->status->value === 'active')>{{ __('organization.active') }}</option>
                                    <option value="inactive" @selected($entity->status->value === 'inactive')>{{ __('organization.inactive') }}</option>
                                </x-form.select>
                                <div class="flex items-end"><x-button type="submit">{{ __('ui.actions.save') }}</x-button></div>
                            </form>
                        </details>
                    </div>
                @endcan
            </article>
        @endforeach
    </div>
@endsection
