@extends('layouts.app')
@section('title', __('attendance.review_queue'))
@section('page-title', __('attendance.review_queue'))
@section('content')
    @if ($errors->any())<x-alert variant="danger" :title="__('ui.design.error_title')" class="mb-6"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif
    @foreach ([['title' => __('attendance.manager_queue'), 'items' => $managerQueue, 'route' => 'attendance.review.manager'], ['title' => __('attendance.hr_queue'), 'items' => $hrQueue, 'route' => 'attendance.review.hr']] as $queue)
        <section class="surface-panel mb-6 rounded-2xl border p-6 shadow-panel"><h2 class="text-xl font-bold text-primary">{{ $queue['title'] }}</h2><div class="mt-5 space-y-4">
            @forelse ($queue['items'] as $correction)
                <article class="rounded-xl border border-slate-200 p-5 dark:border-slate-800"><div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="font-bold text-primary">{{ $correction->employee?->full_name }} · {{ __('attendance.correction_types.'.$correction->type) }}</h3><p class="mt-1 text-sm text-secondary">{{ $correction->record?->work_date?->format('d M Y') }} · {{ $correction->submitted_at?->format('d M Y H:i') }}</p></div><x-badge variant="warning">{{ __('attendance.statuses.'.$correction->status->value) }}</x-badge></div>
                    <form method="POST" action="{{ route($queue['route'], $correction) }}" class="mt-5 grid gap-4 sm:grid-cols-[12rem_1fr_auto]">@csrf @method('PUT')
                        <x-form.select name="decision" :label="__('attendance.decision')"><option value="approve">{{ __('attendance.approve') }}</option><option value="reject">{{ __('attendance.reject') }}</option></x-form.select>
                        <x-form.input name="review_notes" :label="__('attendance.review_notes')" required />
                        <div class="flex items-end"><x-button type="submit">{{ __('attendance.review') }}</x-button></div>
                    </form>
                </article>
            @empty <p class="text-sm text-secondary">{{ __('attendance.no_queue') }}</p>@endforelse
        </div></section>
    @endforeach
@endsection
