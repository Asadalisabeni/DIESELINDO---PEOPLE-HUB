<?php

use App\Models\AuditActivity;

return [
    'activity_model' => AuditActivity::class,
    'default_log_name' => 'security',
    'delete_records_older_than_days' => 3650,
];
