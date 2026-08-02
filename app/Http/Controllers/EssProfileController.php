<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEssContactRequest;
use App\Models\Employee;
use App\Models\User;
use App\Services\Employee\EmployeeSelfServiceManager;
use Illuminate\Http\RedirectResponse;

class EssProfileController extends Controller
{
    public function update(UpdateEssContactRequest $request, EmployeeSelfServiceManager $manager): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $employee = $actor->employee;
        abort_unless($employee instanceof Employee, 403);
        $manager->updateDirectProfile($actor, $employee, $request->validated());

        return redirect()->route('ess.dashboard')->with('status', __('ess.status.contact_updated'));
    }
}
