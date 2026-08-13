@extends('layouts.app')

@section('title', __('attendance.title'))
@section('page-title', __('attendance.title'))

@section('content')
    @if ($errors->any())
        <x-alert variant="danger" :title="__('ui.design.error_title')" class="mb-6"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>
    @endif

    <section class="overflow-hidden rounded-3xl bg-navy-950 px-6 py-9 text-white shadow-panel sm:px-9 lg:px-12" aria-labelledby="attendance-title">
        <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-400">{{ __('attendance.eyebrow') }}</p>
        <h1 id="attendance-title" class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">{{ $employee->full_name }}</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300">{{ __('attendance.description') }}</p>
        <div class="mt-6 flex flex-wrap gap-2"><x-badge variant="brand">{{ $employee->employee_number }}</x-badge><x-badge variant="success">{{ $employee->legalEntity?->display_name }}</x-badge></div>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[1.1fr_.9fr]">
        <article class="surface-panel rounded-2xl border p-6 shadow-panel sm:p-7" aria-labelledby="clock-title">
            <p class="section-kicker">{{ __('attendance.eyebrow') }}</p>
            <h2 id="clock-title" class="mt-2 text-2xl font-bold text-primary">{{ __('attendance.clock_title') }}</h2>
            <p class="mt-2 text-sm leading-6 text-secondary">{{ __('attendance.clock_help') }}</p>

            @if ($sources->isEmpty())
                <x-alert variant="warning" :title="__('attendance.source')" class="mt-6">{{ __('ui.states.empty_body') }}</x-alert>
            @else
                <form method="POST" action="{{ route('attendance.events.store') }}" enctype="multipart/form-data" class="mt-6 space-y-5" data-attendance-form data-sync-url="{{ route('attendance.sync.store') }}">
                    @csrf
                    <input type="hidden" name="external_event_id" value="{{ old('external_event_id') }}" data-event-id>
                    <input type="hidden" name="device_recorded_at" value="{{ old('device_recorded_at') }}" data-device-time>
                    <input type="hidden" name="latitude" value="{{ old('latitude') }}" data-latitude>
                    <input type="hidden" name="longitude" value="{{ old('longitude') }}" data-longitude>
                    <input type="hidden" name="gps_accuracy_meters" value="{{ old('gps_accuracy_meters') }}" data-accuracy>
                    <input type="hidden" name="device_info" value="" data-device-info>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form.select name="source_public_id" :label="__('attendance.source')" required>
                            @foreach ($sources as $source)<option value="{{ $source->public_id }}" @selected(old('source_public_id') === $source->public_id)>{{ $source->name }} · {{ $source->type->value }}</option>@endforeach
                        </x-form.select>
                        <x-form.select name="event_type" :label="__('attendance.event_type')" required>
                            <option value="check_in">{{ __('attendance.check_in') }}</option>
                            <option value="check_out" @selected(old('event_type') === 'check_out')>{{ __('attendance.check_out') }}</option>
                        </x-form.select>
                    </div>
                    <x-form.input name="occurred_at" type="datetime-local" :label="__('attendance.occurred_at')" :value="old('occurred_at', now()->format('Y-m-d\TH:i'))" required />
                    <div class="flex flex-wrap items-center gap-3">
                        <x-button type="button" variant="secondary" data-location-button><x-icon name="grid" size="4" /> {{ __('attendance.capture_location') }}</x-button>
                        <span class="text-sm text-secondary" data-location-status>{{ __('attendance.location_unavailable') }}</span>
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form.input name="activity" :label="__('attendance.activity')" :value="old('activity')" />
                        <x-form.input name="destination" :label="__('attendance.destination')" :value="old('destination')" />
                    </div>
                    <div><label for="notes" class="block text-sm font-bold text-primary">{{ __('attendance.notes') }}</label><textarea id="notes" name="notes" rows="2" class="control mt-2">{{ old('notes') }}</textarea></div>
                    <div><label for="selfie" class="block text-sm font-bold text-primary">{{ __('attendance.selfie') }}</label><input id="selfie" name="selfie" type="file" accept="image/jpeg,image/png" capture="user" class="control mt-2"></div>
                    <x-alert variant="info" :title="__('attendance.offline_notice')" />
                    <x-button type="submit"><x-icon name="clock" size="4" /> {{ __('attendance.submit') }}</x-button>
                </form>
            @endif
        </article>

        <div class="space-y-6">
            <article class="surface-panel rounded-2xl border p-6 shadow-panel">
                <p class="section-kicker">{{ now()->translatedFormat('F Y') }}</p>
                <h2 class="mt-2 text-xl font-bold text-primary">{{ __('attendance.summary') }}</h2>
                <p class="mt-6 text-4xl font-bold text-primary">{{ $monthLateMinutes }}</p>
                <p class="mt-1 text-sm font-semibold text-secondary">{{ __('attendance.late_minutes') }}</p>
                <p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-400/10 dark:text-amber-200">{{ __('attendance.late_policy') }}</p>
            </article>
            <article class="surface-panel rounded-2xl border p-6 shadow-panel">
                <h2 class="text-xl font-bold text-primary">{{ __('attendance.correction_history') }}</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($corrections as $correction)
                        <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-800"><div class="flex items-center justify-between gap-3"><span class="text-sm font-bold text-primary">{{ __('attendance.correction_types.'.$correction->type) }}</span><x-badge variant="neutral">{{ __('attendance.statuses.'.$correction->status->value) }}</x-badge></div><p class="mt-2 text-xs text-secondary">{{ $correction->submitted_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</p></div>
                    @empty
                        <p class="text-sm text-secondary">{{ __('attendance.no_queue') }}</p>
                    @endforelse
                </div>
            </article>
        </div>
    </section>

    <section class="surface-panel mt-6 overflow-hidden rounded-2xl border shadow-panel" aria-labelledby="history-title">
        <div class="border-b border-slate-200 p-6 dark:border-slate-800"><h2 id="history-title" class="text-xl font-bold text-primary">{{ __('attendance.history') }}</h2></div>
        <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-secondary dark:bg-white/5"><tr>@foreach (['work_date','schedule','actual_in','actual_out','late_minutes','record_status','payroll_eligibility','version'] as $heading)<th class="px-5 py-3">{{ __('attendance.'.$heading) }}</th>@endforeach</tr></thead><tbody class="divide-y divide-slate-200 dark:divide-slate-800">
            @forelse ($records as $record)<tr><td class="px-5 py-4 font-bold text-primary">{{ $record->work_date?->format('d M Y') }}</td><td class="px-5 py-4 text-secondary">{{ $record->scheduleAssignment?->schedule?->name ?? '—' }}</td><td class="px-5 py-4 text-secondary">{{ $record->check_in_at?->timezone($employee->legalEntity?->timezone)->format('H:i:s') ?? '—' }}</td><td class="px-5 py-4 text-secondary">{{ $record->check_out_at?->timezone($employee->legalEntity?->timezone)->format('H:i:s') ?? '—' }}</td><td class="px-5 py-4 text-secondary">{{ $record->late_minutes }}</td><td class="px-5 py-4"><x-badge variant="neutral">{{ __('attendance.statuses.'.$record->status) }}</x-badge></td><td class="px-5 py-4"><x-badge variant="warning">{{ __('attendance.statuses.'.$record->payroll_eligibility) }}</x-badge></td><td class="px-5 py-4 font-mono text-secondary">v{{ $record->normalization_version }}</td></tr>
            @empty <tr><td colspan="8" class="px-5 py-10 text-center text-secondary">{{ __('attendance.no_records') }}</td></tr>@endforelse
        </tbody></table></div>
        <div class="p-5">{{ $records->links() }}</div>
    </section>

    @if ($records->count() > 0)
        <section class="surface-panel mt-6 rounded-2xl border p-6 shadow-panel sm:p-7" aria-labelledby="correction-title">
            <h2 id="correction-title" class="text-2xl font-bold text-primary">{{ __('attendance.correction_title') }}</h2><p class="mt-2 text-sm text-secondary">{{ __('attendance.correction_help') }}</p>
            <form method="POST" action="{{ route('attendance.corrections.store') }}" enctype="multipart/form-data" class="mt-6 grid gap-5 lg:grid-cols-2">@csrf
                <x-form.select name="attendance_record_public_id" :label="__('attendance.record')" required>@foreach ($records as $record)<option value="{{ $record->public_id }}">{{ $record->work_date?->format('d M Y') }} · v{{ $record->normalization_version }}</option>@endforeach</x-form.select>
                <x-form.select name="type" :label="__('attendance.correction_type')" required>@foreach (__('attendance.correction_types') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</x-form.select>
                <x-form.input name="proposed_check_in_at" type="datetime-local" :label="__('attendance.proposed_check_in')" :value="old('proposed_check_in_at')" />
                <x-form.input name="proposed_check_out_at" type="datetime-local" :label="__('attendance.proposed_check_out')" :value="old('proposed_check_out_at')" />
                <div class="lg:col-span-2"><label for="correction_reason" class="block text-sm font-bold text-primary">{{ __('attendance.reason') }}</label><textarea id="correction_reason" name="reason" rows="3" class="control mt-2" required>{{ old('reason') }}</textarea></div>
                <div><label for="evidence" class="block text-sm font-bold text-primary">{{ __('attendance.evidence') }}</label><input id="evidence" name="evidence" type="file" accept=".pdf,.jpg,.jpeg,.png" class="control mt-2"></div>
                <div class="flex items-end"><x-button type="submit">{{ __('attendance.submit_correction') }}</x-button></div>
            </form>
        </section>
    @endif
@endsection
