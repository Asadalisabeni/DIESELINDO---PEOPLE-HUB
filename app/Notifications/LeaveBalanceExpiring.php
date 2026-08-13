<?php

namespace App\Notifications;

use App\Models\LeaveEntitlement;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveBalanceExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly LeaveEntitlement $entitlement)
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
            'kind' => 'leave.balance.expiring',
            'title_key' => 'leave.notifications.expiring_title',
            'body_key' => 'leave.notifications.expiring_body',
            'parameters' => ['date' => $this->expiryDate()],
            'route' => 'leave.index',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('leave.notifications.expiring_title'))
            ->line(__('leave.notifications.expiring_body', ['date' => $this->expiryDate()]))
            ->action(__('leave.title'), route('leave.index'));
    }

    private function expiryDate(): string
    {
        $value = $this->entitlement->getRawOriginal('valid_to');

        return is_string($value) ? CarbonImmutable::parse($value)->format('d M Y') : '—';
    }
}
