@extends('layouts.app')

@section('title', __('ess.notifications_text.center'))
@section('page-title', __('ess.notifications_text.center'))

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="section-kicker">{{ __('ess.eyebrow') }}</p><h1 class="mt-2 text-3xl font-bold text-primary">{{ __('ess.notifications_text.center') }}</h1></div><form method="POST" action="{{ route('notifications.read-all') }}">@csrf @method('PATCH')<x-button type="submit" variant="secondary">{{ __('ess.mark_all_read') }}</x-button></form></div>
    <div class="mt-6 space-y-3">
        @forelse ($notifications as $notification)
            @php($data = $notification->data)
            <article @class(['surface-panel rounded-2xl border p-5 shadow-panel', 'border-brand-300 dark:border-brand-700' => $notification->read_at === null])>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div><div class="flex items-center gap-2"><h2 class="font-bold text-primary">{{ __((string) ($data['title_key'] ?? 'ess.notifications_text.center'), (array) ($data['parameters'] ?? [])) }}</h2><x-badge :variant="$notification->read_at === null ? 'brand' : 'neutral'">{{ $notification->read_at === null ? __('ess.unread') : __('ess.read') }}</x-badge></div><p class="mt-2 text-sm text-secondary">{{ __((string) ($data['body_key'] ?? 'ess.notifications_text.center'), (array) ($data['parameters'] ?? [])) }}</p><p class="mt-2 text-xs text-secondary">{{ $notification->created_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</p></div><form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf @method('PATCH')<x-button type="submit" variant="secondary">{{ __('ess.mark_read_open') }}</x-button></form></div>
            </article>
        @empty
            <x-state-panel icon="bell" :title="__('ess.no_notifications')" />
        @endforelse
    </div>
    <div class="mt-5">{{ $notifications->links() }}</div>
@endsection
