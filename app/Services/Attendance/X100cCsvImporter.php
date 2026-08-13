<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceSourceType;
use App\Models\AttendanceImportBatch;
use App\Models\AttendanceSource;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class X100cCsvImporter
{
    public function __construct(private readonly AttendanceManager $attendance) {}

    public function import(User $actor, AttendanceSource $source, UploadedFile $file): AttendanceImportBatch
    {
        if ($source->sourceType() !== AttendanceSourceType::Fingerprint || $source->adapter !== 'x100c_csv_v1') {
            throw ValidationException::withMessages(['source_public_id' => __('attendance.validation.x100c_source_required')]);
        }

        $checksum = hash_file('sha256', $file->getRealPath());
        if (! is_string($checksum)) {
            throw ValidationException::withMessages(['import_file' => __('attendance.validation.import_unreadable')]);
        }
        if (AttendanceImportBatch::query()->where('attendance_source_id', $source->getKey())->where('checksum_sha256', $checksum)->exists()) {
            throw ValidationException::withMessages(['import_file' => __('attendance.validation.import_already_processed')]);
        }

        $batch = AttendanceImportBatch::query()->create([
            'legal_entity_id' => $source->legal_entity_id,
            'attendance_source_id' => $source->getKey(),
            'original_filename' => basename($file->getClientOriginalName()),
            'checksum_sha256' => $checksum,
            'status' => 'processing',
            'imported_by' => $actor->getKey(),
        ]);
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['import_file' => __('attendance.validation.import_unreadable')]);
        }

        $expected = ['employee_number', 'event_type', 'occurred_at', 'external_event_id'];
        $header = fgetcsv($handle);
        if ($header !== $expected) {
            fclose($handle);
            $batch->update(['status' => 'failed', 'sanitized_errors' => [['row' => 1, 'code' => 'invalid_header']], 'completed_at' => now()]);
            throw ValidationException::withMessages(['import_file' => __('attendance.validation.import_header')]);
        }

        $rowCount = $imported = $duplicates = $rejected = 0;
        $errors = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rowCount++;
            try {
                if (count($row) !== 4 || in_array(null, $row, true) || ! in_array($row[1], ['check_in', 'check_out'], true)) {
                    throw new \UnexpectedValueException('invalid_row');
                }
                $employee = Employee::query()->where('legal_entity_id', $source->legal_entity_id)
                    ->where('employee_number', trim($row[0]))->firstOrFail();
                $result = $this->attendance->ingest($actor, $employee, $source, [
                    'external_event_id' => trim($row[3]),
                    'event_type' => $row[1],
                    'occurred_at' => $row[2],
                    'device_recorded_at' => $row[2],
                    'was_offline' => false,
                ]);
                $result['duplicate'] ? $duplicates++ : $imported++;
            } catch (Throwable) {
                $rejected++;
                if (count($errors) < 20) {
                    $errors[] = ['row' => $rowCount + 1, 'code' => 'row_rejected'];
                }
            }
        }
        fclose($handle);

        $batch->update([
            'status' => $rejected > 0 ? 'completed_with_errors' : 'completed',
            'row_count' => $rowCount,
            'imported_count' => $imported,
            'duplicate_count' => $duplicates,
            'rejected_count' => $rejected,
            'sanitized_errors' => $errors,
            'completed_at' => now(),
        ]);

        DB::afterCommit(function () use ($actor, $batch): void {
            activity('attendance')->causedBy($actor)->performedOn($batch)
                ->event('x100c_csv_reconciled')
                ->withProperties([
                    'batch_public_id' => $batch->public_id,
                    'row_count' => $batch->row_count,
                    'imported_count' => $batch->imported_count,
                    'duplicate_count' => $batch->duplicate_count,
                    'rejected_count' => $batch->rejected_count,
                ])->log('Solution X100C canonical CSV PoC batch reconciled.');
        });

        return $batch->refresh();
    }
}
