<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContractRequest;
use App\Models\User;
use App\Services\Employee\EmployeeManager;
use Illuminate\Http\RedirectResponse;

class ContractController extends Controller
{
    public function store(StoreContractRequest $request, EmployeeManager $manager): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $employee = $request->scopedEmployee();
        $manager->addContract($actor, $employee, $request->validated());

        return redirect()->route('employees.show', $employee)->with('status', __('employee.status.contract_created'));
    }
}
