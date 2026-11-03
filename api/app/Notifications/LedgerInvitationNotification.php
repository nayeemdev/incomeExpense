<?php

namespace App\Notifications;

use App\Models\LedgerInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LedgerInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public LedgerInvitation $invitation,
        public string $token,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ledger = $this->invitation->ledger;
        $inviter = $this->invitation->inviter;

        return (new MailMessage)
            ->subject(__(':name invited you to :ledger', ['name' => $inviter->name, 'ledger' => $ledger->name]))
            ->line(__(':name invited you to join the ledger ":ledger" as :role.', [
                'name' => $inviter->name,
                'ledger' => $ledger->name,
                'role' => __($this->invitation->role->value),
            ]))
            ->action(__('Accept invitation'), url('/invitations/'.$this->token))
            ->line(__('This invitation expires on :date.', ['date' => $this->invitation->expires_at->toFormattedDayDateString()]));
    }
}
