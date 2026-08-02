@extends('layouts.app')

@section('title', $employee->full_name)
@section('page-title', __('employee.title'))

@section('content')
    @if (session('status'))
        <x-alert variant="success" :title="__('auth.status')" class="mb-6">{{ session('status') }}</x-alert>
    @endif
    @if ($errors->any())
        <x-alert variant="danger" :title="__('ui.design.danger_title')" class="mb-6">
            <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </x-alert>
    @endif

    <header class="surface-panel rounded-2xl border p-6 shadow-panel">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex items-start gap-4">
                <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-navy-100 text-lg font-black text-navy-800 dark:bg-brand-500 dark:text-navy-950">{{ \Illuminate\Support\Str::of($employee->full_name)->explode(' ')->take(2)->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))->join('') }}</span>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-bold text-primary">{{ $employee->full_name }}</h1>
                        <x-badge :variant="$employee->status->value === 'active' ? 'success' : 'neutral'">{{ __('employee.'.$employee->status->value) }}</x-badge>
                    </div>
                    <p class="mt-1 text-sm text-secondary">{{ $employee->employee_number }} · {{ $employee->legalEntity->display_name }}</p>
                    <p class="mt-2 text-sm text-secondary">{{ $employee->company_email ?: '—' }}</p>
                </div>
            </div>
            <x-button :href="route('employees.index')" variant="secondary">{{ __('ui.actions.go_back') }}</x-button>
        </div>
    </header>

    <section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="{{ __('employee.current_assignment') }}">
        @foreach ([
            [__('employee.branch'), $employee->currentEmployment?->branch?->name],
            [__('employee.department'), $employee->currentEmployment?->department?->name],
            [__('employee.position'), $employee->currentEmployment?->position?->name],
            [__('employee.manager'), $employee->currentEmployment?->manager?->full_name],
        ] as [$label, $value])
            <article class="surface-panel rounded-2xl border p-5 shadow-panel">
                <p class="text-xs font-bold uppercase tracking-wide text-secondary">{{ $label }}</p>
                <p class="mt-2 font-bold text-primary">{{ $value ?: '—' }}</p>
            </article>
        @endforeach
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1.15fr_.85fr]">
        <div class="space-y-6">
            <section class="surface-panel rounded-2xl border p-6 shadow-panel">
                <h2 class="text-xl font-bold text-primary">{{ __('employee.identity') }}</h2>
                <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                    @foreach ([
                        [__('employee.nik'), $canViewSensitive ? App\Support\Security\SensitiveValue::mask($employee->nik_last_four) : App\Support\Security\SensitiveValue::mask(null)],
                        [__('employee.birth_place'), $employee->birth_place],
                        [__('employee.birth_date'), $employee->birth_date?->format('d M Y')],
                        [__('employee.gender'), $employee->gender ? __('employee.'.$employee->gender) : null],
                        [__('employee.marital_status'), $employee->marital_status ? __('employee.'.$employee->marital_status) : null],
                        [__('employee.personal_email'), $canViewSensitive ? $employee->personal_email : App\Support\Security\SensitiveValue::mask(null)],
                    ] as [$label, $value])
                        <div><dt class="text-xs font-bold uppercase tracking-wide text-secondary">{{ $label }}</dt><dd class="mt-1 text-sm font-semibold text-primary">{{ $value ?: '—' }}</dd></div>
                    @endforeach
                </dl>

                @if ($canViewSensitive)
                    <div class="mt-6 grid gap-4 border-t border-slate-200 pt-5 sm:grid-cols-2 dark:border-slate-700">
                        @foreach ($employee->contacts as $contact)
                            <div><p class="text-xs font-bold uppercase text-secondary">{{ __('employee.'.$contact->type) }}</p><p class="mt-1 text-sm text-primary">{{ $contact->value }}</p></div>
                        @endforeach
                        @foreach ($employee->emergencyContacts as $contact)
                            <div><p class="text-xs font-bold uppercase text-secondary">{{ __('employee.emergency') }}</p><p class="mt-1 text-sm text-primary">{{ $contact->name }} · {{ $contact->relationship }} · {{ $contact->phone }}</p></div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="surface-panel overflow-hidden rounded-2xl border shadow-panel">
                <div class="border-b border-slate-200 p-6 dark:border-slate-700"><h2 class="text-xl font-bold text-primary">{{ __('employee.history') }}</h2></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm dark:divide-slate-700">
                        <thead class="surface-muted text-xs uppercase text-secondary"><tr><th class="px-5 py-3">{{ __('employee.effective_from') }}</th><th class="px-5 py-3">{{ __('employee.legal_entity') }}</th><th class="px-5 py-3">{{ __('employee.department') }}</th><th class="px-5 py-3">{{ __('employee.position') }}</th><th class="px-5 py-3">{{ __('employee.change_reason') }}</th></tr></thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($employee->employmentHistories as $history)
                                <tr><td class="px-5 py-4 text-secondary">{{ $history->effective_from->format('d M Y') }}<br><span class="text-xs">{{ $history->effective_to?->format('d M Y') ?? '∞' }}</span></td><td class="px-5 py-4 text-primary">{{ $history->legalEntity->display_name }}<br><span class="text-xs text-secondary">{{ $history->branch->name }}</span></td><td class="px-5 py-4 text-secondary">{{ $history->department->name }}</td><td class="px-5 py-4 text-secondary">{{ $history->position->name }}</td><td class="max-w-xs px-5 py-4 text-secondary">{{ $history->change_reason }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="surface-panel overflow-hidden rounded-2xl border shadow-panel">
                <div class="border-b border-slate-200 p-6 dark:border-slate-700"><h2 class="text-xl font-bold text-primary">{{ __('employee.contract') }}</h2></div>
                <div class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse ($employee->contracts as $contract)
                        <div class="flex flex-col gap-2 p-5 sm:flex-row sm:items-center sm:justify-between"><div><p class="font-bold text-primary">{{ $contract->contract_number }}</p><p class="mt-1 text-sm text-secondary">{{ __('employee.'.$contract->contract_type) }} · {{ $contract->start_date->format('d M Y') }} — {{ $contract->end_date?->format('d M Y') ?? '∞' }}</p></div><x-badge>{{ $contract->status->value }}</x-badge></div>
                    @empty
                        <p class="p-6 text-sm text-secondary">{{ __('employee.not_available') }}</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="space-y-6">
            @if ($canViewFinancial)
                <section class="surface-panel rounded-2xl border p-6 shadow-panel">
                    <h2 class="text-xl font-bold text-primary">{{ __('employee.financial') }}</h2>
                    <p class="mt-2 text-sm text-secondary">{{ __('employee.masked') }}</p>
                    <dl class="mt-5 space-y-4">
                        <div><dt class="text-xs font-bold uppercase text-secondary">{{ __('employee.bank_account_number') }}</dt><dd class="mt-1 font-semibold text-primary">{{ App\Support\Security\SensitiveValue::mask($employee->bankAccounts->first()?->account_number_last_four) }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase text-secondary">{{ __('employee.tax_identifier') }}</dt><dd class="mt-1 font-semibold text-primary">{{ App\Support\Security\SensitiveValue::mask($employee->taxProfiles->first()?->tax_identifier_last_four) }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase text-secondary">{{ __('employee.bpjs_health_number') }}</dt><dd class="mt-1 font-semibold text-primary">{{ App\Support\Security\SensitiveValue::mask($employee->bpjsProfiles->first()?->health_number_last_four) }}</dd></div>
                    </dl>
                </section>
            @endif

            @can('documents.view')
                <section class="surface-panel rounded-2xl border p-6 shadow-panel">
                    <h2 class="text-xl font-bold text-primary">{{ __('employee.documents') }}</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($employee->documents as $document)
                            @can('view', $document)
                                <div class="surface-muted flex items-center justify-between gap-3 rounded-xl p-3"><div class="min-w-0"><p class="truncate text-sm font-bold text-primary">{{ $document->original_name }}</p><p class="mt-1 text-xs text-secondary">{{ $document->type }} · {{ $document->classification->value }}</p></div>@can('download', $document)<x-button :href="route('employee-documents.download', $document)" variant="secondary">{{ __('employee.download') }}</x-button>@endcan</div>
                            @endcan
                        @empty
                            <p class="text-sm text-secondary">{{ __('employee.not_available') }}</p>
                        @endforelse
                    </div>
                </section>
            @endcan

            @can('update', $employee)
                <details class="surface-panel rounded-2xl border p-6 shadow-panel">
                    <summary class="cursor-pointer text-lg font-bold text-primary">{{ __('employee.edit_identity') }}</summary>
                    @php($currentPhone = $employee->contacts->firstWhere('type', 'phone')?->value)
                    @php($currentAddress = $employee->contacts->firstWhere('type', 'address')?->value)
                    <form method="POST" action="{{ route('employees.update', $employee) }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                        @csrf @method('PUT')
                        <x-form.input name="full_name" :label="__('employee.full_name')" :value="$employee->full_name" required />
                        <x-form.input name="nik" :label="__('employee.nik')" :hint="__('organization.tax_identifier_hint')" autocomplete="off" />
                        <x-form.input name="birth_place" :label="__('employee.birth_place')" :value="$employee->birth_place" />
                        <x-form.input name="birth_date" type="date" :label="__('employee.birth_date')" :value="$employee->birth_date?->format('Y-m-d')" />
                        <x-form.select name="gender" :label="__('employee.gender')"><option value="">{{ __('employee.optional') }}</option><option value="male" @selected($employee->gender === 'male')>{{ __('employee.male') }}</option><option value="female" @selected($employee->gender === 'female')>{{ __('employee.female') }}</option></x-form.select>
                        <x-form.select name="marital_status" :label="__('employee.marital_status')"><option value="">{{ __('employee.optional') }}</option>@foreach(['single','married','divorced','widowed'] as $value)<option value="{{ $value }}" @selected($employee->marital_status === $value)>{{ __('employee.'.$value) }}</option>@endforeach</x-form.select>
                        <x-form.input name="personal_email" type="email" :label="__('employee.personal_email')" :value="$employee->personal_email" />
                        <x-form.input name="company_email" type="email" :label="__('employee.company_email')" :value="$employee->company_email" />
                        <x-form.input name="phone" :label="__('employee.phone')" :value="$currentPhone" />
                        <x-form.input name="address" :label="__('employee.address')" :value="$currentAddress" />
                        <x-form.select name="status" :label="__('organization.status_label')">@foreach(['active','inactive','terminated'] as $value)<option value="{{ $value }}" @selected($employee->status->value === $value)>{{ __('employee.'.$value) }}</option>@endforeach</x-form.select>
                        <x-form.input name="effective_from" type="date" :label="__('employee.effective_from')" :value="now()->format('Y-m-d')" required />
                        <div class="sm:col-span-2"><x-button type="submit">{{ __('ui.actions.save') }}</x-button></div>
                    </form>
                </details>

                <details class="surface-panel rounded-2xl border p-6 shadow-panel">
                    <summary class="cursor-pointer text-lg font-bold text-primary">{{ __('employee.new_assignment') }}</summary>
                    <form method="POST" action="{{ route('employees.assignments.store', $employee) }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                        @csrf
                        @include('employees._assignment-fields', ['legalEntities' => $legalEntities, 'employee' => $employee, 'selectedEntity' => $employee->legalEntity->public_id, 'employeeNumber' => $employee->employee_number])
                        <x-form.input name="termination_date" type="date" :label="__('employee.termination_date')" />
                        <x-form.input name="effective_from" type="date" :label="__('employee.effective_from')" required />
                        <x-form.input name="change_reason" :label="__('employee.change_reason')" maxlength="500" required />
                        <div class="sm:col-span-2"><x-button type="submit">{{ __('employee.save_assignment') }}</x-button></div>
                    </form>
                </details>
            @endcan

            @can('manageContract', $employee)
                <details class="surface-panel rounded-2xl border p-6 shadow-panel">
                    <summary class="cursor-pointer text-lg font-bold text-primary">{{ __('employee.new_contract') }}</summary>
                    <form method="POST" action="{{ route('employees.contracts.store', $employee) }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                        @csrf
                        <x-form.select name="contract_type" :label="__('employee.contract_type')"><option value="permanent">{{ __('employee.permanent') }}</option><option value="fixed_term">{{ __('employee.fixed_term') }}</option></x-form.select>
                        <x-form.input name="contract_number" :label="__('employee.contract_number')" required />
                        <x-form.input name="start_date" type="date" :label="__('employee.contract_start_date')" required />
                        <x-form.input name="end_date" type="date" :label="__('employee.contract_end_date')" />
                        <x-form.input name="probation_end_date" type="date" :label="__('employee.probation_end_date')" />
                        <x-form.input name="change_reason" :label="__('employee.change_reason')" required />
                        <div class="sm:col-span-2"><x-button type="submit">{{ __('employee.save_contract') }}</x-button></div>
                    </form>
                </details>
            @endcan

            @can('uploadDocument', $employee)
                <details class="surface-panel rounded-2xl border p-6 shadow-panel">
                    <summary class="cursor-pointer text-lg font-bold text-primary">{{ __('employee.upload_document') }}</summary>
                    <form method="POST" enctype="multipart/form-data" action="{{ route('employees.documents.store', $employee) }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                        @csrf
                        <x-form.select name="type" :label="__('employee.document_type')">@foreach(['ktp','kk','npwp','bpjs','contract','diploma','certificate','drivers_license','photo','bank_proof','warning_letter','health_document'] as $type)<option value="{{ $type }}">{{ strtoupper(str_replace('_', ' ', $type)) }}</option>@endforeach</x-form.select>
                        <x-form.input name="document" type="file" :label="__('employee.file')" accept="application/pdf,image/jpeg,image/png" required />
                        <x-form.input name="issued_date" type="date" :label="__('employee.issued_date')" />
                        <x-form.input name="expires_date" type="date" :label="__('employee.expires_date')" />
                        <x-form.select name="classification" :label="__('employee.classification')"><option value="confidential">{{ __('employee.confidential') }}</option><option value="restricted">{{ __('employee.restricted') }}</option></x-form.select>
                        <div class="sm:col-span-2"><x-button type="submit">{{ __('employee.upload_document') }}</x-button></div>
                    </form>
                </details>
            @endcan
        </div>
    </div>
@endsection
