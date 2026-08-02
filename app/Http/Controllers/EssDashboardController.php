<?php

namespace App\Http\Controllers;

use App\Models\EmergencyContact;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeBpjsProfile;
use App\Models\EmployeeContact;
use App\Models\EmployeeTaxProfile;
use App\Models\User;
use App\Support\Security\SensitiveValue;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class EssDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $employee = $actor->employee;
        abort_unless($employee instanceof Employee, 403);
        $this->authorize('viewSelfService', $employee);

        $employee->load([
            'legalEntity', 'currentEmployment.branch', 'currentEmployment.division',
            'currentEmployment.department', 'currentEmployment.position',
            'currentEmployment.workLocation', 'currentEmployment.manager',
        ]);
        $today = now()->toDateString();
        $contacts = EmployeeContact::query()
            ->where('employee_id', $employee->getKey())
            ->where('legal_entity_id', $employee->legal_entity_id)
            ->whereDate('effective_from', '<=', $today)
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>', $today))
            ->get()->keyBy('type');
        $emergency = EmergencyContact::query()
            ->where('employee_id', $employee->getKey())
            ->where('legal_entity_id', $employee->legal_entity_id)
            ->whereDate('effective_from', '<=', $today)
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>', $today))
            ->orderBy('priority')->first();

        $bank = EmployeeBankAccount::query()
            ->where('employee_id', $employee->getKey())
            ->where('legal_entity_id', $employee->legal_entity_id)
            ->whereDate('effective_from', '<=', $today)
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>', $today))
            ->latest('effective_from')->first();
        $tax = EmployeeTaxProfile::query()
            ->where('employee_id', $employee->getKey())
            ->where('legal_entity_id', $employee->legal_entity_id)
            ->whereDate('effective_from', '<=', $today)
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>', $today))
            ->latest('effective_from')->first();
        $bpjs = EmployeeBpjsProfile::query()
            ->where('employee_id', $employee->getKey())
            ->where('legal_entity_id', $employee->legal_entity_id)
            ->whereDate('effective_from', '<=', $today)
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>', $today))
            ->latest('effective_from')->first();

        activity('employee')
            ->causedBy($actor)
            ->performedOn($employee)
            ->event('ess_profile_viewed')
            ->withProperties([
                'legal_entity_public_id' => $employee->legalEntity()->value('public_id'),
                'employee_public_id' => $employee->public_id,
                'sections' => ['identity', 'contact', 'employment', 'financial_masked'],
            ])
            ->log('Employee viewed their self-service profile.');

        return view('ess.dashboard', [
            'employee' => $employee,
            'contact' => [
                'phone' => $contacts->get('phone')?->value,
                'address' => $contacts->get('address')?->value,
                'emergency_name' => $emergency?->name,
                'emergency_relationship' => $emergency?->relationship,
                'emergency_phone' => $emergency?->phone,
                'emergency_address' => $emergency?->address,
            ],
            'financial' => [
                'bank' => $bank ? SensitiveValue::mask($bank->account_number_last_four) : '—',
                'tax' => $tax ? SensitiveValue::mask($tax->tax_identifier_last_four) : '—',
                'bpjs_health' => $bpjs ? SensitiveValue::mask($bpjs->health_number_last_four) : '—',
                'bpjs_employment' => $bpjs ? SensitiveValue::mask($bpjs->employment_number_last_four) : '—',
            ],
            'recentRequests' => $employee->profileChangeRequests()->with('attachmentDocument')->limit(5)->get(),
            'unreadNotifications' => $actor->unreadNotifications()->count(),
        ]);
    }
}
