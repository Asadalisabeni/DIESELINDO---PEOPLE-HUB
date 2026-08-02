<?php

namespace App\Http\Controllers;

use App\Models\AuditActivity;
use App\Models\AuthenticationEvent;
use Illuminate\Contracts\View\View;

class AuditLogController extends Controller
{
    public function __invoke(): View
    {
        abort_unless(request()->user()?->can('audit.view') === true, 403);

        $activities = AuditActivity::query()
            ->with(['causer', 'subject'])
            ->latest()
            ->paginate(30, ['*'], 'activity_page');
        $authenticationEvents = AuthenticationEvent::query()
            ->with('user')
            ->latest('occurred_at')
            ->paginate(30, ['*'], 'auth_page');

        return view('audit.index', compact('activities', 'authenticationEvents'));
    }
}
