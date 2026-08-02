<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserAccessRequest;
use App\Http\Requests\UpdateUserAccessRequest;
use App\Models\User;
use App\Support\Iam\RoleMatrix;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserAccessController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);
        $actor = request()->user();

        abort_unless($actor instanceof User, 403);

        $roles = RoleMatrix::assignableRoleNames($actor);
        $usersQuery = User::query()->with('roles')->orderBy('name');

        if (! $actor->hasRole('Super Admin')) {
            $usersQuery->whereDoesntHave(
                'roles',
                fn ($query) => $query->whereNotIn('name', $roles),
            );
        }

        $users = $usersQuery->paginate(20);

        return view('iam.index', compact('users', 'roles'));
    }

    public function store(StoreUserAccessRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => Str::lower($validated['email']),
            'password' => Hash::make(Str::random(64)),
            'is_active' => true,
        ]);
        $user->assignRole($validated['role']);

        Password::sendResetLink(['email' => $user->email]);

        activity('iam')
            ->causedBy($request->user())
            ->performedOn($user)
            ->event('user_provisioned')
            ->withProperties(['roles' => [$validated['role']]])
            ->log('User access was provisioned.');

        return back()->with('status', __('auth.user_provisioned'));
    }

    public function update(UpdateUserAccessRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $isActive = (bool) $validated['is_active'];
        $actor = $request->user();

        abort_unless($actor instanceof User, 403);

        if ($actor->is($user) && (! $isActive || ! in_array('Super Admin', $validated['roles'], true))) {
            throw ValidationException::withMessages([
                'roles' => __('auth.cannot_remove_own_admin'),
            ]);
        }

        $user->syncRoles($validated['roles']);
        $user->forceFill([
            'is_active' => $isActive,
            'deactivated_at' => $isActive ? null : now(),
        ])->save();

        activity('iam')
            ->causedBy($actor)
            ->performedOn($user)
            ->event('access_updated')
            ->withProperties([
                'roles' => array_values($validated['roles']),
                'is_active' => $isActive,
            ])
            ->log('User roles or account status changed.');

        return back()->with('status', __('auth.access_updated'));
    }
}
