<?php

namespace App\Notifications;

use App\Models\Ledger;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;

class WeeklySummaryNotification extends LedgerNotification
{
    /**
     * @param  list<array{currency: string, amount: string}>  $spent
     * @param  list<array{currency: string, amount: string}>  $earned
     */
    public function __construct(public Ledger $ledger, public CarbonImmutable $start, public array $spent, public array $earned) {}

    public function event(): string
    {
        return 'weekly_summary';
    }

    public function ledgerId(): string
    {
        return $this->ledger->id;
    }

    public function title(): string
    {
        return __('Your week in :ledger', ['ledger' => $this->ledger->name]);
    }

    public function body(): string
    {
        $format = fn (array $amounts) => $amounts === []
            ? '0'
            : implode(', ', array_map(fn (array $item) => "{$item['amount']} {$item['currency']}", $amounts));

        return __('Since :date you spent :spent and earned :earned.', [
            'date' => $this->start->toFormattedDayDateString(),
            'spent' => $format($this->spent),
            'earned' => $format($this->earned),
        ]);
    }

    public function path(): string
    {
        return "/{$this->ledger->id}";
    }

    public function toMail(object $notifiable): MailMessage
    {
        return parent::toMail($notifiable)->greeting(__('Hi :name,', ['name' => $notifiable instanceof User ? $notifiable->name : '']));
    }
}
