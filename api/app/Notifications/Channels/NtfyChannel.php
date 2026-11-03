<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\LedgerNotification;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends a notification to the user's ntfy topic, so it reaches their phone through the ntfy app.
 * Does nothing for users who have not set up ntfy.
 */
class NtfyChannel
{
    public function send(object $notifiable, LedgerNotification $notification): void
    {
        if (! $notifiable instanceof User) {
            return;
        }

        $private = self::isPrivate($notifiable);

        try {
            $response = self::publish(
                $notifiable,
                $notification->ntfyTitle($private),
                $private ? __('Open LedgerFly to see the details.') : $notification->body(),
                url($notification->path()),
            );
        } catch (ConnectionException $exception) {
            Log::warning('ntfy server unreachable', ['message' => $exception->getMessage()]);

            return;
        }

        if ($response?->failed()) {
            Log::warning('ntfy notification failed', ['status' => $response->status()]);
        }
    }

    /**
     * @return array{server: string, topic: string, token: ?string, private: bool}|null
     */
    public static function settings(User $user): ?array
    {
        $ntfy = $user->preferences['ntfy'] ?? null;

        if (! is_array($ntfy) || ! isset($ntfy['server'], $ntfy['topic'])) {
            return null;
        }

        return [
            'server' => (string) $ntfy['server'],
            'topic' => (string) $ntfy['topic'],
            'token' => isset($ntfy['token']) && is_string($ntfy['token']) ? Crypt::decryptString($ntfy['token']) : null,
            'private' => (bool) ($ntfy['private'] ?? true),
        ];
    }

    public static function publish(User $user, string $title, string $message, ?string $click = null): ?Response
    {
        $settings = self::settings($user);

        if ($settings === null) {
            return null;
        }

        $request = Http::timeout(10)->acceptJson();

        if ($settings['token'] !== null) {
            $request = $request->withToken($settings['token']);
        }

        return $request->post(rtrim($settings['server'], '/'), array_filter([
            'topic' => $settings['topic'],
            'title' => $title,
            'message' => $message,
            'click' => $click,
            'tags' => ['moneybag'],
        ]));
    }

    private static function isPrivate(User $user): bool
    {
        return self::settings($user)['private'] ?? true;
    }
}
