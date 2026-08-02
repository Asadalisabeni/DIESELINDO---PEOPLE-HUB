@extends('layouts.app')

@section('title', __('ess.title'))
@section('page-title', __('ess.title'))

@section('content')
    @if ($errors->any())
        <x-alert variant="danger" :title="__('ui.design.error_title')" class="mb-6">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </x-alert>
    @endif

    <section class="overflow-hidden rounded-3xl bg-navy-950 text-white shadow-panel" aria-labelledby="ess-title">
        <div class="relative grid gap-8 px-6 py-9 sm:px-9 lg:grid-cols-[1.3fr_.7fr] lg:items-end lg:px-12">
            <div class="pointer-events-none absolute -right-24 -top-32 size-72 rounded-full border-[56px] border-brand-500/10"></div>
            <div class="relative">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-400">{{ __('ess.eyebrow') }}</p>
                <h1 id="ess-title" class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">{{ $employee->full_name }}</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">{{ __('ess.description') }}</p>
                <div class="mt-6 flex flex-wrap gap-2">
                    <x-badge variant="brand">{{ __('ess.employee_number') }}: {{ $employee->employee_number }}</x-badge>
                    <x-badge variant="success">{{ $employee->legalEntity?->display_name }}</x-badge>
                </div>
            </div>
            <div class="relative grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                <x-button :href="route('ess.requests.index')" variant="secondary">{{ __('ess.request_history') }} <x-icon name="arrow-right" size="4" /></x-button>
                <x-button :href="route('notifications.index')" variant="ghost" class="border border-white/20 text-white hover:bg-white/10">
                    <x-icon name="bell" size="4" /> {{ __('ess.notifications_text.center') }}
                    @if ($unreadNotifications > 0)<span class="rounded-full bg-brand-500 px-2 py-0.5 text-xs text-navy-950">{{ $unreadNotifications }}</span>@endif
                </x-button>
            </div>
        </div>
    </section>

    @php($assignment = $employee->currentEmployment)
    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="{{ __('ess.current_assignment') }}">
        @foreach ([
            ['legal_entity', $employee->legalEntity?->display_name, 'grid'],
            ['department', $assignment?->department?->name, 'users'],
            ['position', $assignment?->position?->name, 'wallet'],
            ['manager', $assignment?->manager?->full_name, 'users'],
        ] as [$label, $value, $icon])
            <article class="surface-panel rounded-2xl border p-5 shadow-panel">
                <span class="grid size-10 place-items-center rounded-xl bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-200"><x-icon :name="$icon" /></span>
                <p class="mt-4 text-sm font-medium text-secondary">{{ __('ess.'.$label) }}</p>
                <p class="mt-1 font-bold text-primary">{{ $value ?: __('ess.not_available') }}</p>
            </article>
        @endforeach
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[1.1fr_.9fr]">
        <article class="surface-panel rounded-2xl border p-6 shadow-panel sm:p-7" aria-labelledby="contact-title">
            <p class="section-kicker">{{ __('ess.my_profile') }}</p>
            <h2 id="contact-title" class="mt-2 text-2xl font-bold text-primary">{{ __('ess.direct_contact') }}</h2>
            <p class="mt-2 text-sm leading-6 text-secondary">{{ __('ess.direct_contact_help') }}</p>

            <form method="POST" action="{{ route('ess.profile.contact.update') }}" class="mt-6 space-y-5">
                @csrf
                @method('PUT')
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.input name="phone" :label="__('ess.fields.phone')" :value="old('phone', $contact['phone'])" autocomplete="tel" required />
                    <x-form.input name="emergency_phone" :label="__('ess.fields.emergency_phone')" :value="old('emergency_phone', $contact['emergency_phone'])" autocomplete="tel" required />
                </div>
                <div>
                    <label for="address" class="block text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('ess.fields.address') }}</label>
                    <textarea id="address" name="address" rows="3" class="control mt-2" required>{{ old('address', $contact['address']) }}</textarea>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.input name="emergency_name" :label="__('ess.fields.emergency_name')" :value="old('emergency_name', $contact['emergency_name'])" required />
                    <x-form.input name="emergency_relationship" :label="__('ess.fields.emergency_relationship')" :value="old('emergency_relationship', $contact['emergency_relationship'])" required />
                </div>
                <div>
                    <label for="emergency_address" class="block text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('ess.fields.emergency_address') }}</label>
                    <textarea id="emergency_address" name="emergency_address" rows="2" class="control mt-2">{{ old('emergency_address', $contact['emergency_address']) }}</textarea>
                </div>
                <x-button type="submit"><x-icon name="check" size="4" /> {{ __('ess.save_contact') }}</x-button>
            </form>
        </article>

        <div class="space-y-6">
            <article class="surface-panel rounded-2xl border p-6 shadow-panel" aria-labelledby="sensitive-title">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="section-kicker">{{ __('ess.masked') }}</p>
                        <h2 id="sensitive-title" class="mt-2 text-xl font-bold text-primary">{{ __('ess.sensitive_summary') }}</h2>
                    </div>
                    <span class="grid size-10 place-items-center rounded-xl bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-200"><x-icon name="lock" /></span>
                </div>
                <p class="mt-3 text-sm leading-6 text-secondary">{{ __('ess.sensitive_summary_help') }}</p>
                <dl class="mt-5 divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    @foreach ([
                        'bank_account' => $financial['bank'],
                        'tax_identifier' => $financial['tax'],
                        'bpjs_health' => $financial['bpjs_health'],
                        'bpjs_employment' => $financial['bpjs_employment'],
                    ] as $label => $value)
                        <div class="flex items-center justify-between gap-4 py-3"><dt class="text-secondary">{{ __('ess.'.$label) }}</dt><dd class="font-mono font-bold text-primary">{{ $value }}</dd></div>
                    @endforeach
                </dl>
            </article>

            <article class="surface-panel rounded-2xl border p-6 shadow-panel" aria-labelledby="recent-title">
                <div class="flex items-center justify-between gap-4">
                    <h2 id="recent-title" class="text-xl font-bold text-primary">{{ __('ess.request_history') }}</h2>
                    <a href="{{ route('ess.requests.index') }}" class="text-sm font-bold text-brand-700 dark:text-brand-300">{{ __('ui.actions.learn_more') }}</a>
                </div>
                <div class="mt-4 space-y-3">
                    @forelse ($recentRequests as $changeRequest)
                        @php($status = $changeRequest->status->value)
                        <a href="{{ route('ess.requests.show', $changeRequest) }}" class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 p-3 transition hover:border-brand-300 dark:border-slate-800 dark:hover:border-brand-700">
                            <span><span class="block text-sm font-bold text-primary">{{ __($changeRequest->type->labelKey()) }}</span><span class="mt-1 block text-xs text-secondary">{{ $changeRequest->submitted_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</span></span>
                            <x-badge :variant="$status === 'approved' ? 'success' : ($status === 'rejected' ? 'danger' : ($status === 'pending' ? 'warning' : 'neutral'))">{{ __('ess.statuses.'.$status) }}</x-badge>
                        </a>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-4 text-sm text-secondary dark:bg-white/5">{{ __('ess.no_requests') }}</p>
                    @endforelse
                </div>
            </article>
        </div>
    </section>

    <section class="surface-panel mt-6 rounded-2xl border p-6 shadow-panel sm:p-7" aria-labelledby="request-title" x-data="{ type: @js(old('request_type', 'legal_name')) }">
        <p class="section-kicker">{{ __('ess.request_history') }}</p>
        <h2 id="request-title" class="mt-2 text-2xl font-bold text-primary">{{ __('ess.new_request') }}</h2>
        <p class="mt-2 text-sm leading-6 text-secondary">{{ __('ess.new_request_help') }}</p>

        <form method="POST" action="{{ route('ess.requests.store') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
            @csrf
            <x-form.select name="request_type" :label="__('ess.fields.request_type')" x-model="type" required>
                @foreach (App\Enums\ProfileChangeType::cases() as $requestType)
                    <option value="{{ $requestType->value }}">{{ __($requestType->labelKey()) }}</option>
                @endforeach
            </x-form.select>

            <div x-show="type === 'legal_name'" x-cloak><x-form.input name="full_name" :label="__('ess.fields.full_name')" :value="old('full_name')" /></div>
            <div x-show="type === 'marital_status'" x-cloak>
                <x-form.select name="marital_status" :label="__('ess.fields.marital_status')">
                    @foreach (['single', 'married', 'divorced', 'widowed'] as $value)<option value="{{ $value }}" @selected(old('marital_status') === $value)>{{ __('employee.'.$value) }}</option>@endforeach
                </x-form.select>
            </div>
            <div x-show="type === 'bank_account'" x-cloak class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="bank_code" :label="__('ess.fields.bank_code')" :value="old('bank_code')" />
                <x-form.input name="bank_name" :label="__('ess.fields.bank_name')" :value="old('bank_name')" />
                <x-form.input name="account_number" :label="__('ess.fields.account_number')" :value="old('account_number')" autocomplete="off" />
                <x-form.input name="account_holder_name" :label="__('ess.fields.account_holder_name')" :value="old('account_holder_name')" />
            </div>
            <div x-show="type === 'tax_profile'" x-cloak class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="tax_identifier" :label="__('ess.fields.tax_identifier')" :value="old('tax_identifier')" autocomplete="off" />
                <x-form.input name="ptkp_code" :label="__('ess.fields.ptkp_code')" :value="old('ptkp_code')" />
            </div>
            <div x-show="type === 'bpjs_profile'" x-cloak class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="health_number" :label="__('ess.fields.health_number')" :value="old('health_number')" autocomplete="off" />
                <x-form.input name="employment_number" :label="__('ess.fields.employment_number')" :value="old('employment_number')" autocomplete="off" />
                <x-form.input name="jkk_risk_category" :label="__('ess.fields.jkk_risk_category')" :value="old('jkk_risk_category')" />
            </div>
            <div x-show="type === 'family_data'" x-cloak class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="family_full_name" :label="__('ess.fields.family_full_name')" :value="old('family_full_name')" />
                <x-form.select name="relationship" :label="__('ess.fields.relationship')">
                    @foreach (['spouse', 'child', 'parent', 'other'] as $value)<option value="{{ $value }}" @selected(old('relationship') === $value)>{{ __('ess.relationships.'.$value) }}</option>@endforeach
                </x-form.select>
                <x-form.input name="birth_date" type="date" :label="__('ess.fields.birth_date')" :value="old('birth_date')" />
                <x-form.input name="identity_number" :label="__('ess.fields.identity_number')" :value="old('identity_number')" autocomplete="off" />
            </div>
            <div x-show="type === 'identity_document'" x-cloak>
                <x-form.select name="document_type" :label="__('ess.fields.document_type')">
                    @foreach (['ktp', 'kk', 'npwp', 'bpjs', 'diploma', 'certificate', 'drivers_license', 'photo', 'bank_proof', 'health_document'] as $value)<option value="{{ $value }}">{{ strtoupper(str_replace('_', ' ', $value)) }}</option>@endforeach
                </x-form.select>
            </div>
            <div x-show="type === 'employment_data'" x-cloak class="grid gap-5 sm:grid-cols-[1fr_16rem]">
                <div><label for="requested_change" class="block text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('ess.fields.requested_change') }}</label><textarea id="requested_change" name="requested_change" rows="4" class="control mt-2">{{ old('requested_change') }}</textarea></div>
                <x-form.input name="preferred_effective_date" type="date" :label="__('ess.fields.preferred_effective_date')" :value="old('preferred_effective_date')" />
            </div>
            <div x-show="['bank_account', 'tax_profile', 'bpjs_profile', 'family_data'].includes(type)" x-cloak class="max-w-sm">
                <x-form.input name="effective_from" type="date" :label="__('ess.fields.effective_from')" :value="old('effective_from', now()->toDateString())" />
            </div>

            <div><label for="reason" class="block text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('ess.reason') }}</label><textarea id="reason" name="reason" rows="3" class="control mt-2" required>{{ old('reason') }}</textarea></div>
            <div><label for="attachment" class="block text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('ess.attachment') }}</label><input id="attachment" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png" class="control mt-2"><p class="mt-2 text-sm text-secondary">{{ __('ess.attachment_help') }}</p></div>
            <x-button type="submit"><x-icon name="arrow-right" size="4" /> {{ __('ess.submit_request') }}</x-button>
        </form>
    </section>
@endsection
