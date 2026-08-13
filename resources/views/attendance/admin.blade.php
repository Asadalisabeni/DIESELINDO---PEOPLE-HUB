@extends('layouts.app')
@section('title', __('attendance.admin_title'))
@section('page-title', __('attendance.admin_title'))
@section('content')
    @if ($errors->any())<x-alert variant="danger" :title="__('ui.design.error_title')" class="mb-6"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif
    <section class="rounded-3xl bg-navy-950 px-6 py-9 text-white shadow-panel sm:px-9"><p class="text-xs font-bold uppercase tracking-[.2em] text-brand-400">{{ __('attendance.eyebrow') }}</p><h1 class="mt-3 text-3xl font-bold">{{ __('attendance.admin_title') }}</h1><p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300">{{ __('attendance.admin_description') }}</p></section>

    @unless ($canManage)<x-alert variant="info" :title="__('attendance.read_only')" class="mt-6">{{ __('attendance.read_only_help') }}</x-alert>@endunless
    <section class="mt-6 grid gap-6 xl:grid-cols-2">
        <article class="surface-panel rounded-2xl border p-6 shadow-panel"><h2 class="text-xl font-bold text-primary">{{ __('attendance.configured_schedules') }}</h2><div class="mt-4 space-y-3">@forelse($schedules as $schedule)<div class="rounded-xl border border-slate-200 p-3 dark:border-slate-800"><p class="font-bold text-primary">{{ $schedule->name }}</p><p class="mt-1 text-sm text-secondary">{{ $schedule->legalEntity?->code }} · {{ $schedule->code }} · {{ $schedule->late_grace_minutes }} {{ __('attendance.late_minutes') }}</p></div>@empty<p class="text-sm text-secondary">{{ __('attendance.no_records') }}</p>@endforelse</div></article>
        <article class="surface-panel rounded-2xl border p-6 shadow-panel"><h2 class="text-xl font-bold text-primary">{{ __('attendance.configured_sources') }}</h2><div class="mt-4 space-y-3">@forelse($sources as $source)<div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-800"><span><span class="block font-bold text-primary">{{ $source->name }}</span><span class="mt-1 block text-sm text-secondary">{{ $source->legalEntity?->code }} · {{ $source->code }}</span></span><x-badge variant="neutral">{{ $source->sourceType()->value }}</x-badge></div>@empty<p class="text-sm text-secondary">{{ __('attendance.no_records') }}</p>@endforelse</div></article>
    </section>

    @if ($canManage)
    <section class="mt-6 grid gap-6 xl:grid-cols-2">
        <article class="surface-panel rounded-2xl border p-6 shadow-panel"><h2 class="text-xl font-bold text-primary">{{ __('attendance.create_schedule') }}</h2>
            <form method="POST" action="{{ route('attendance.admin.schedules.store') }}" class="mt-5 space-y-5">@csrf
                <x-form.select name="legal_entity_public_id" :label="__('attendance.legal_entity')" required>@foreach($entities as $entity)<option value="{{ $entity->public_id }}">{{ $entity->display_name }}</option>@endforeach</x-form.select>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.select name="branch_public_id" :label="__('attendance.branch_scope')"><option value="">{{ __('attendance.entity_default') }}</option>@foreach($branches as $branch)<option value="{{ $branch->public_id }}">{{ $branch->legalEntity?->code }} · {{ $branch->name }}</option>@endforeach</x-form.select>
                    <x-form.select name="department_public_id" :label="__('attendance.department_scope')"><option value="">{{ __('attendance.no_department_override') }}</option>@foreach($departments as $department)<option value="{{ $department->public_id }}">{{ $department->legalEntity?->code }} · {{ $department->name }}</option>@endforeach</x-form.select>
                </div>
                <div class="grid gap-4 sm:grid-cols-2"><x-form.input name="code" :label="__('attendance.code')" required/><x-form.input name="name" :label="__('attendance.name')" required/><x-form.input name="timezone" :label="__('attendance.timezone')" value="Asia/Jakarta" required/><x-form.input name="effective_from" type="date" :label="__('attendance.effective_from')" :value="now()->toDateString()" required/><x-form.input name="effective_to" type="date" :label="__('attendance.effective_to')"/><x-form.input name="late_grace_minutes" type="number" min="0" max="240" :label="__('attendance.late_grace')" value="0" required/><x-form.input name="early_leave_grace_minutes" type="number" min="0" max="240" :label="__('attendance.early_grace')" value="0" required/></div>
                <fieldset><legend class="text-sm font-bold text-primary">{{ __('attendance.working_days') }}</legend><div class="mt-3 space-y-3">
                    @foreach(__('attendance.days') as $day => $label)
                        <div class="grid items-end gap-3 rounded-xl border border-slate-200 p-3 sm:grid-cols-[8rem_1fr_1fr_7rem] dark:border-slate-800"><label class="flex items-center gap-2 text-sm font-bold text-primary"><input type="hidden" name="days[{{ $day }}][is_working_day]" value="0"><input type="checkbox" name="days[{{ $day }}][is_working_day]" value="1">{{ $label }}</label><x-form.input name="days[{{ $day }}][start_time]" type="time" :label="__('attendance.start_time')"/><x-form.input name="days[{{ $day }}][end_time]" type="time" :label="__('attendance.end_time')"/><x-form.input name="days[{{ $day }}][break_minutes]" type="number" min="0" max="480" :label="__('attendance.break_minutes')" value="0"/></div>
                    @endforeach
                </div></fieldset>
                <x-button type="submit">{{ __('attendance.create_schedule') }}</x-button>
            </form>
        </article>

        <div class="space-y-6">
            <article class="surface-panel rounded-2xl border p-6 shadow-panel"><h2 class="text-xl font-bold text-primary">{{ __('attendance.create_source') }}</h2>
                <form method="POST" action="{{ route('attendance.admin.sources.store') }}" class="mt-5 space-y-5">@csrf
                    <x-form.select name="legal_entity_public_id" :label="__('attendance.legal_entity')" required>@foreach($entities as $entity)<option value="{{ $entity->public_id }}">{{ $entity->display_name }}</option>@endforeach</x-form.select>
                    <div class="grid gap-4 sm:grid-cols-2"><x-form.input name="code" :label="__('attendance.code')" required/><x-form.input name="name" :label="__('attendance.name')" required/>
                        <x-form.select name="type" :label="__('attendance.source_type')"><option value="web">web</option><option value="mobile_gps">mobile_gps</option><option value="offline_mobile">offline_mobile</option><option value="fingerprint">fingerprint</option><option value="import">import</option><option value="manual_adjustment">manual_adjustment</option></x-form.select>
                        <x-form.select name="adapter" :label="__('attendance.adapter')"><option value="web_gps_v1">web_gps_v1</option><option value="offline_mobile_v1">offline_mobile_v1</option><option value="x100c_csv_v1">x100c_csv_v1</option></x-form.select>
                        <x-form.input name="max_gps_accuracy_meters" type="number" min="1" :label="__('attendance.max_accuracy')" value="150" required/><x-form.input name="max_offline_delay_minutes" type="number" min="1" :label="__('attendance.max_offline_delay')" value="720" required/>
                    </div>
                    <div class="flex flex-wrap gap-5"><label class="flex items-center gap-2 text-sm font-bold text-primary"><input type="hidden" name="requires_gps" value="0"><input type="checkbox" name="requires_gps" value="1">{{ __('attendance.requires_gps') }}</label><label class="flex items-center gap-2 text-sm font-bold text-primary"><input type="hidden" name="requires_selfie" value="0"><input type="checkbox" name="requires_selfie" value="1">{{ __('attendance.requires_selfie') }}</label></div>
                    <x-button type="submit">{{ __('attendance.create_source') }}</x-button>
                </form>
            </article>

            <article class="surface-panel rounded-2xl border p-6 shadow-panel"><h2 class="text-xl font-bold text-primary">{{ __('attendance.assign_schedule') }}</h2>
                <form method="POST" action="{{ route('attendance.admin.assignments.store') }}" class="mt-5 space-y-5">@csrf
                    <x-form.select name="employee_public_id" :label="__('attendance.employee')" required>@foreach($employees as $employee)<option value="{{ $employee->public_id }}">{{ $employee->full_name }} · {{ $employee->employee_number }}</option>@endforeach</x-form.select>
                    <x-form.select name="work_schedule_public_id" :label="__('attendance.schedule')" required>@foreach($schedules as $schedule)<option value="{{ $schedule->public_id }}">{{ $schedule->legalEntity?->code }} · {{ $schedule->name }}</option>@endforeach</x-form.select>
                    <div class="grid gap-4 sm:grid-cols-2"><x-form.input name="effective_from" type="date" :label="__('attendance.effective_from')" :value="now()->toDateString()" required/><x-form.input name="effective_to" type="date" :label="__('attendance.effective_to')"/></div>
                    <x-form.input name="reason" :label="__('attendance.assignment_reason')" required/><x-button type="submit">{{ __('attendance.assign_schedule') }}</x-button>
                </form>
            </article>
        </div>
    </section>
    @endif

    <section class="surface-panel mt-6 rounded-2xl border p-6 shadow-panel"><h2 class="text-xl font-bold text-primary">{{ __('attendance.holidays') }}</h2>
        @if ($canManage)
        <form method="POST" action="{{ route('attendance.admin.holidays.store') }}" class="mt-5 grid items-end gap-4 md:grid-cols-3">@csrf
            <x-form.select name="legal_entity_public_id" :label="__('attendance.legal_entity')" required>@foreach($entities as $entity)<option value="{{ $entity->public_id }}">{{ $entity->display_name }}</option>@endforeach</x-form.select>
            <x-form.select name="branch_public_id" :label="__('attendance.branch_scope')"><option value="">{{ __('attendance.entity_default') }}</option>@foreach($branches as $branch)<option value="{{ $branch->public_id }}">{{ $branch->legalEntity?->code }} · {{ $branch->name }}</option>@endforeach</x-form.select>
            <x-form.input name="holiday_date" type="date" :label="__('attendance.holiday_date')" required/>
            <x-form.input name="name" :label="__('attendance.name')" required/>
            <x-form.select name="type" :label="__('attendance.holiday_type')"><option value="national">national</option><option value="company">company</option><option value="collective_leave">collective_leave</option><option value="special">special</option></x-form.select>
            <x-form.input name="source" :label="__('attendance.holiday_source')"/>
            <x-button type="submit">{{ __('attendance.create_holiday') }}</x-button>
        </form>
        @endif
        <div class="mt-5 flex flex-wrap gap-2">@foreach($holidays as $holiday)<x-badge variant="neutral">{{ $holiday->holiday_date?->format('d M Y') }} · {{ $holiday->name }}</x-badge>@endforeach</div>
    </section>

    <section class="surface-panel mt-6 rounded-2xl border p-6 shadow-panel"><h2 class="text-xl font-bold text-primary">{{ __('attendance.imports') }}</h2><x-alert variant="warning" :title="__('attendance.import_warning')" class="mt-4"/>
        @if ($canManage)
        <form method="POST" action="{{ route('attendance.admin.imports.store') }}" enctype="multipart/form-data" class="mt-5 grid items-end gap-5 md:grid-cols-[1fr_1fr_auto]">@csrf
            <x-form.select name="source_public_id" :label="__('attendance.source')" required>@foreach($sources->where('type.value', 'fingerprint') as $source)<option value="{{ $source->public_id }}">{{ $source->name }}</option>@endforeach</x-form.select>
            <div><label for="import_file" class="block text-sm font-bold text-primary">{{ __('attendance.import_file') }}</label><input id="import_file" name="import_file" type="file" accept=".csv,text/csv" class="control mt-2" required></div><x-button type="submit">{{ __('attendance.import') }}</x-button>
        </form>
        @endif
        <div class="mt-6 overflow-x-auto"><table class="min-w-full text-left text-sm"><thead><tr><th class="py-2">ID</th><th>{{ __('attendance.source') }}</th><th>Status</th><th>{{ __('attendance.counts') }}</th></tr></thead><tbody class="divide-y divide-slate-200 dark:divide-slate-800">@forelse($imports as $batch)<tr><td class="py-3 font-mono text-secondary">{{ $batch->public_id }}</td><td>{{ $batch->source?->name }}</td><td><x-badge variant="neutral">{{ $batch->status }}</x-badge></td><td>{{ $batch->row_count }} / {{ $batch->imported_count }} / {{ $batch->duplicate_count }} / {{ $batch->rejected_count }}</td></tr>@empty<tr><td colspan="4" class="py-5 text-secondary">{{ __('attendance.no_records') }}</td></tr>@endforelse</tbody></table></div>
    </section>
@endsection
