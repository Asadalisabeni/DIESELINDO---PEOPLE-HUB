<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLegalEntity;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceImportBatch extends Model
{
    use BelongsToLegalEntity, HasPublicId;

    protected $fillable = [
        'legal_entity_id', 'attendance_source_id', 'original_filename', 'checksum_sha256',
        'status', 'row_count', 'imported_count', 'duplicate_count', 'rejected_count',
        'sanitized_errors', 'imported_by', 'completed_at',
    ];

    protected $hidden = ['checksum_sha256'];

    protected function casts(): array
    {
        return ['sanitized_errors' => 'array', 'completed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<AttendanceSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(AttendanceSource::class, 'attendance_source_id');
    }
}
