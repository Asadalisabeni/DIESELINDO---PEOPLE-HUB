<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveApprovalPending extends Notification implements ShouldQueue
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
        return [
            'kind' => 'leave.approval.pending',
            'title_key' => 'leave.notifications.pending_title',
            'body_key' => 'leave.notifications.pending_body',
            'parameters' => ['request' => $this->request->public_id],
            'request_public_id' => $this->request->public_id,
            'route' => 'leave.review.index',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('leave.notifications.pending_title'))
            ->line(__('leave.notifications.pending_body', ['request' => $this->request->public_id]))
            ->action(__('leave.review'), route('leave.review.index'));
    }
}
