<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ImportAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('attendance.import');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source_public_id' => ['required', 'string', 'size:26'],
            'import_file' => ['required', 'file', 'max:10240', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel'],
        ];
    }
}
