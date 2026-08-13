<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly LeaveRequest $request)
    {
        $this->afterCommit = true;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $status = $this->request->requestStatus()->value;

        return [
            'kind' => 'leave.request.'.$status,
            'title_key' => 'leave.notifications.reviewed_title',
            'body_key' => 'leave.notifications.reviewed_body',
            'parameters' => ['request' => $this->request->public_id, 'status' => __('leave.statuses.'.$status)],
            'request_public_id' => $this->request->public_id,
            'route' => 'leave.index',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('leave.notifications.reviewed_title'))
            ->line(__('leave.notifications.reviewed_body', [
                'request' => $this->request->public_id,
                'status' => __('leave.statuses.'.$this->request->requestStatus()->value),
            ]))
            ->action(__('leave.title'), route('leave.index'));
    }
}
