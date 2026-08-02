<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeDocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Services\Employee\EmployeeDocumentManager;
use App\Services\Organization\LegalEntityScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeDocumentController extends Controller
{
    public function store(StoreEmployeeDocumentRequest $request, EmployeeDocumentManager $manager): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $employee = $request->scopedEmployee();
        $file = $request->file('document');
        abort_unless($file !== null, 422);
        $manager->store($actor, $employee, $file, $request->validated());

        return redirect()->route('employees.show', $employee)->with('status', __('employee.status.document_uploaded'));
    }

    public function download(Request $request, string $document): StreamedResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $record = EmployeeDocument::query()
            ->where('public_id', $document)
            ->where(function ($query) use ($actor): void {
                if ($actor->employee_id !== null) {
                    $query->where('employee_id', $actor->employee_id);
                } else {
                    $query->whereRaw('1 = 0');
                }

                $scopeIds = app(LegalEntityScope::class)->idsFor($actor);
                if ($scopeIds !== []) {
                    $query->orWhereIn('legal_entity_id', $scopeIds);
                }
            })
            ->firstOrFail();
        $this->authorize('download', $record);

        $disk = Storage::disk($record->storage_disk);
        abort_unless($disk->exists($record->storage_path), 404);
        $employee = $record->employee;
        abort_unless($employee instanceof Employee, 404);

        activity('employee')
            ->causedBy($actor)
            ->performedOn($employee)
            ->event('employee_document_downloaded')
            ->withProperties([
                'legal_entity_public_id' => $record->legalEntity()->value('public_id'),
                'document_public_id' => $record->public_id,
                'type' => $record->type,
                'classification' => (string) $record->getRawOriginal('classification'),
            ])
            ->log('Private employee document downloaded.');

        return $disk->download($record->storage_path, $record->original_name, [
            'Content-Type' => $record->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
