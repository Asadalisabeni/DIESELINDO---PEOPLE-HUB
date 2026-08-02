@extends('layouts.app')

@section('title', __('employee.create'))
@section('page-title', __('employee.create'))

@section('content')
    <header>
        <p class="section-kicker">{{ __('employee.eyebrow') }}</p>
        <h1 class="mt-2 text-3xl font-bold text-primary">{{ __('employee.create') }}</h1>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">{{ __('employee.create_description') }}</p>
    </header>

    @if ($errors->any())
        <x-alert variant="danger" :title="__('ui.design.danger_title')" class="mt-6">
            <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </x-alert>
    @endif

    @if ($legalEntities->isEmpty())
        <x-state-panel variant="empty" :title="__('organization.entity_empty')" :description="__('organization.entity_empty_help')" class="mt-6 shadow-panel" />
    @else
        <form method="POST" action="{{ route('employees.store') }}" class="mt-6 space-y-6">
            @csrf

            <section class="surface-panel rounded-2xl border p-6 shadow-panel">
                <h2 class="text-xl font-bold text-primary">{{ __('employee.identity') }}</h2>
                <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <x-form.input name="full_name" :label="__('employee.full_name')" :value="old('full_name')" required />
                    <x-form.input name="nik" :label="__('employee.nik')" :value="old('nik')" autocomplete="off" />
                    <x-form.input name="birth_place" :label="__('employee.birth_place')" :value="old('birth_place')" />
                    <x-form.input name="birth_date" type="date" :label="__('employee.birth_date')" :value="old('birth_date')" />
                    <x-form.select name="gender" :label="__('employee.gender')">
                        <option value="">{{ __('employee.optional') }}</option>
                        <option value="male" @selected(old('gender') === 'male')>{{ __('employee.male') }}</option>
                        <option value="female" @selected(old('gender') === 'female')>{{ __('employee.female') }}</option>
                    </x-form.select>
                    <x-form.select name="marital_status" :label="__('employee.marital_status')">
                        <option value="">{{ __('employee.optional') }}</option>
                        @foreach (['single', 'married', 'divorced', 'widowed'] as $status)
                            <option value="{{ $status }}" @selected(old('marital_status') === $status)>{{ __('employee.'.$status) }}</option>
                        @endforeach
                    </x-form.select>
                    <x-form.input name="personal_email" type="email" :label="__('employee.personal_email')" :value="old('personal_email')" />
                    <x-form.input name="company_email" type="email" :label="__('employee.company_email')" :value="old('company_email')" />
                </div>
            </section>

            <section class="surface-panel rounded-2xl border p-6 shadow-panel">
                <h2 class="text-xl font-bold text-primary">{{ __('employee.employment') }}</h2>
                <p class="mt-2 text-sm text-secondary">{{ __('employee.description') }}</p>
                <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    @include('employees._assignment-fields', ['legalEntities' => $legalEntities])
                    <x-form.input name="join_date" type="date" :label="__('employee.join_date')" :value="old('join_date')" required />
                    <x-form.input name="effective_from" type="date" :label="__('employee.effective_from')" :value="old('effective_from')" required />
                    <x-form.input name="change_reason" :label="__('employee.change_reason')" :value="old('change_reason')" maxlength="500" />
                </div>
            </section>

            <section class="surface-panel rounded-2xl border p-6 shadow-panel">
                <h2 class="text-xl font-bold text-primary">{{ __('employee.contact') }} & {{ __('employee.emergency') }}</h2>
                <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <x-form.input name="phone" :label="__('employee.phone')" :value="old('phone')" />
                    <x-form.input name="address" :label="__('employee.address')" :value="old('address')" />
                    <x-form.input name="emergency_name" :label="__('employee.emergency_name')" :value="old('emergency_name')" />
                    <x-form.input name="emergency_relationship" :label="__('employee.emergency_relationship')" :value="old('emergency_relationship')" />
                    <x-form.input name="emergency_phone" :label="__('employee.emergency_phone')" :value="old('emergency_phone')" />
                    <x-form.input name="emergency_address" :label="__('employee.emergency_address')" :value="old('emergency_address')" />
                </div>
            </section>

            <section class="surface-panel rounded-2xl border p-6 shadow-panel">
                <h2 class="text-xl font-bold text-primary">{{ __('employee.contract') }}</h2>
                <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <x-form.select name="contract_type" :label="__('employee.contract_type')" required>
                        <option value="permanent" @selected(old('contract_type') === 'permanent')>{{ __('employee.permanent') }}</option>
                        <option value="fixed_term" @selected(old('contract_type') === 'fixed_term')>{{ __('employee.fixed_term') }}</option>
                    </x-form.select>
                    <x-form.input name="contract_number" :label="__('employee.contract_number')" :value="old('contract_number')" required />
                    <x-form.input name="contract_start_date" type="date" :label="__('employee.contract_start_date')" :value="old('contract_start_date')" required />
                    <x-form.input name="contract_end_date" type="date" :label="__('employee.contract_end_date')" :value="old('contract_end_date')" />
                    <x-form.input name="probation_end_date" type="date" :label="__('employee.probation_end_date')" :value="old('probation_end_date')" />
                </div>
            </section>

            @can('employees.view-sensitive')
                <section class="surface-panel rounded-2xl border p-6 shadow-panel">
                    <h2 class="text-xl font-bold text-primary">{{ __('employee.financial') }}</h2>
                    <p class="mt-2 text-sm text-secondary">{{ __('employee.masked') }} · Nilai disimpan terenkripsi dan berstatus verifikasi pending.</p>
                    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <x-form.input name="bank_code" :label="__('employee.bank_code')" value="BCA" />
                        <x-form.input name="bank_name" :label="__('employee.bank_name')" value="Bank Central Asia" />
                        <x-form.input name="bank_account_number" :label="__('employee.bank_account_number')" autocomplete="off" />
                        <x-form.input name="bank_account_holder" :label="__('employee.bank_account_holder')" />
                        <x-form.input name="tax_identifier" :label="__('employee.tax_identifier')" autocomplete="off" />
                        <x-form.input name="ptkp_code" :label="__('employee.ptkp_code')" />
                        <x-form.input name="bpjs_health_number" :label="__('employee.bpjs_health_number')" autocomplete="off" />
                        <x-form.input name="bpjs_employment_number" :label="__('employee.bpjs_employment_number')" autocomplete="off" />
                        <x-form.input name="jkk_risk_category" :label="__('employee.jkk_risk_category')" />
                    </div>
                </section>
            @endcan

            <div class="flex flex-wrap justify-end gap-3">
                <x-button :href="route('employees.index')" variant="secondary">{{ __('ui.actions.cancel') }}</x-button>
                <x-button type="submit">{{ __('employee.save_employee') }}</x-button>
            </div>
        </form>
    @endif
@endsection
