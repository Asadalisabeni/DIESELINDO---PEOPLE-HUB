<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmploymentAssignmentRequest;
use App\Models\User;
use App\Services\Employee\EmployeeManager;
use Illuminate\Http\RedirectResponse;

class EmploymentAssignmentController extends Controller
{
    public function store(StoreEmploymentAssignmentRequest $request, EmployeeManager $manager): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $employee = $request->scopedEmployee();
        $manager->changeAssignment($actor, $employee, $request->scopedLegalEntity(), $request->validated());

        return redirect()->route('employees.show', $employee)->with('status', __('employee.status.assignment_created'));
    }
}
