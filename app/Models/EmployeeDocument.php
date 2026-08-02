<?php

namespace App\Models;

use App\Enums\DocumentClassification;
use App\Enums\DocumentStatus;
use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'employee_id', 'type', 'storage_disk', 'storage_path', 'original_name',
        'mime_type', 'size_bytes', 'checksum_sha256', 'issued_date', 'expires_date',
        'classification', 'status', 'uploaded_by',
    ];

    protected $hidden = ['storage_path', 'checksum_sha256'];

    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'expires_date' => 'date',
            'classification' => DocumentClassification::class,
            'status' => DocumentStatus::class,
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
