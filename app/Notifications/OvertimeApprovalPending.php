<?php

namespace App\Notifications;

use App\Models\OvertimeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OvertimeApprovalPending extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly OvertimeRequest $request)
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
            'kind' => 'overtime.approval.pending', 'title_key' => 'overtime.notifications.pending_title',
            'body_key' => 'overtime.notifications.pending_body',
            'parameters' => ['request' => $this->request->public_id],
            'request_public_id' => $this->request->public_id, 'route' => 'overtime.review.index',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(__('overtime.notifications.pending_title'))
            ->line(__('overtime.notifications.pending_body', ['request' => $this->request->public_id]))
            ->action(__('overtime.review'), route('overtime.review.index'));
    }
}
