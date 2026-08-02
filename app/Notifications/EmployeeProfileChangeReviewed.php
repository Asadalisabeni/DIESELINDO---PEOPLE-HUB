<?php

namespace App\Notifications;

use App\Models\EmployeeProfileChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EmployeeProfileChangeReviewed extends Notification
{
    use Queueable;

    public function __construct(private readonly EmployeeProfileChangeRequest $changeRequest) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $status = $this->changeRequest->changeStatus()->value;

        return [
            'kind' => 'ess.profile_change.'.$status,
            'title_key' => 'ess.notifications.'.$status.'_title',
            'body_key' => 'ess.notifications.'.$status.'_body',
            'parameters' => ['request' => $this->changeRequest->public_id],
            'request_public_id' => $this->changeRequest->public_id,
            'route' => 'ess.requests.show',
        ];
    }
}
