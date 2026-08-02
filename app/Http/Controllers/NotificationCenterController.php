<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class NotificationCenterController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('notifications.view'), 403);

        return view('notifications.index', [
            'notifications' => $actor->notifications()->latest()->paginate(20),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('notifications.view'), 403);
        $record = $actor->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        $route = is_string($record->data['route'] ?? null) ? $record->data['route'] : null;
        $publicId = is_string($record->data['request_public_id'] ?? null) ? $record->data['request_public_id'] : null;

        return $route && $publicId && Route::has($route)
            ? redirect()->route($route, $publicId)
            : redirect()->route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('notifications.view'), 403);
        $actor->unreadNotifications()->update(['read_at' => now()]);

        return redirect()->route('notifications.index')->with('status', __('ess.status.notifications_read'));
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
