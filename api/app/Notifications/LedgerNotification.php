<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Base for every user-facing notification. Delivery channels come from the user's
 * per-event preferences; push is held back during their quiet hours.
 */
abstract class LedgerNotification extends Notification implements ShouldQueue
{
    use Queueable;

    abstract public function event(): string;

    abstract public function title(): string;

    abstract public function body(): string;

    abstract public function path(): string;

    public function ledgerId(): ?int
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        return array_map(
            fn (string $channel) => $channel === 'webpush' ? WebPushChannel::class : $channel,
            NotificationPreferences::for($notifiable)->channelsNow($this->event()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->event(),
            'title' => $this->title(),
            'body' => $this->body(),
            'path' => $this->path(),
            'ledger_id' => $this->ledgerId(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line($this->body())
            ->action(__('Open LedgerFly'), url($this->path()));
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->body($this->body())
            ->icon('/favicon.svg')
            ->tag($this->event())
            ->data(['path' => $this->path()]);
    }
}
