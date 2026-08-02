<?php

namespace App\Services\Employee;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class EmployeeDocumentManager
{
    /** @param array<string, mixed> $data */
    public function store(User $actor, Employee $employee, UploadedFile $file, array $data): EmployeeDocument
    {
        $documentPublicId = (string) Str::ulid();
        $extension = $file->guessExtension() ?: 'bin';
        $directory = 'employee-documents/'.$employee->public_id;
        $path = $directory.'/'.$documentPublicId.'.'.$extension;
        $checksum = hash_file('sha256', $file->getRealPath());
        $stored = Storage::disk('local')->putFileAs($directory, $file, $documentPublicId.'.'.$extension);

        if ($stored !== $path || ! is_string($checksum)) {
            if (is_string($stored)) {
                Storage::disk('local')->delete($stored);
            }

            throw new RuntimeException('Private document storage failed.');
        }

        try {
            return DB::transaction(function () use ($actor, $employee, $file, $data, $documentPublicId, $path, $checksum): EmployeeDocument {
                $document = EmployeeDocument::query()->create([
                    'public_id' => $documentPublicId,
                    'legal_entity_id' => $employee->legal_entity_id,
                    'employee_id' => $employee->getKey(),
                    'type' => (string) $data['type'],
                    'storage_disk' => 'local',
                    'storage_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => (string) $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                    'checksum_sha256' => $checksum,
                    'issued_date' => $data['issued_date'] ?? null,
                    'expires_date' => $data['expires_date'] ?? null,
                    'classification' => (string) $data['classification'],
                    'status' => 'valid',
                    'uploaded_by' => $actor->getKey(),
                ]);

                activity('employee')
                    ->causedBy($actor)
                    ->performedOn($employee)
                    ->event('employee_document_uploaded')
                    ->withProperties([
                        'legal_entity_public_id' => $employee->legalEntity()->value('public_id'),
                        'document_public_id' => $document->public_id,
                        'type' => $document->type,
                        'classification' => (string) $data['classification'],
                        'mime_type' => $document->mime_type,
                        'size_bytes' => $document->size_bytes,
                    ])
                    ->log('Private employee document uploaded.');

                return $document;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }
}
