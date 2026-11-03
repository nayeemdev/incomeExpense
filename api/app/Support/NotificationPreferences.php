<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;

final class NotificationPreferences
{
    /**
     * @param  array<string, mixed>  $preferences
     */
    private function __construct(private array $preferences) {}

    public static function for(User $user): self
    {
        return new self($user->resolvedPreferences());
    }

    /**
     * @return array<string, list<string>>
     */
    public function channels(): array
    {
        /** @var array<string, list<string>> $defaults */
        $defaults = config('ledgerfly.notifications.events');
        $custom = is_array($this->preferences['notifications'] ?? null) ? $this->preferences['notifications'] : [];

        return collect($defaults)
            ->map(fn (array $channels, string $event) => array_values(array_intersect(
                (array) ($custom[$event] ?? $channels),
                config()->array('ledgerfly.notifications.channels'),
            )))
            ->all();
    }

    /**
     * Channels to use right now, leaving out push during quiet hours.
     *
     * @return list<string>
     */
    public function channelsNow(string $event): array
    {
        $channels = $this->channels()[$event] ?? [];

        if ($this->inQuietHours()) {
            $channels = array_values(array_diff($channels, config()->array('ledgerfly.notifications.push_channels')));
        }

        return $channels;
    }

    public function largeTransactionAmount(): ?string
    {
        $amount = $this->preferences['large_transaction_amount'] ?? null;

        return is_numeric($amount) ? (string) $amount : null;
    }

    public function inQuietHours(): bool
    {
        $quiet = $this->preferences['quiet_hours'] ?? null;

        if (! is_array($quiet) || ! isset($quiet['start'], $quiet['end'])) {
            return false;
        }

        $now = Carbon::now((string) $this->preferences['timezone'])->format('H:i');
        [$start, $end] = [(string) $quiet['start'], (string) $quiet['end']];

        return $start <= $end ? $now >= $start && $now < $end : $now >= $start || $now < $end;
    }
}
