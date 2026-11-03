<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\Channels\NtfyChannel;
use App\Support\NotificationPreferences;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function index(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $page = $user->notifications()
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->paginate($this->perPage($request));

        return response()->json([
            'data' => collect($page->items())->map(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                ...$notification->data,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
            ]),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'unread' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    public function read(#[CurrentUser] User $user, string $notification): Response
    {
        $user->notifications()->findOrFail($notification)->markAsRead();

        return response()->noContent();
    }

    public function readAll(#[CurrentUser] User $user): Response
    {
        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function destroy(#[CurrentUser] User $user, string $notification): Response
    {
        $user->notifications()->findOrFail($notification)->delete();

        return response()->noContent();
    }

    public function settings(#[CurrentUser] User $user): JsonResponse
    {
        $preferences = $user->resolvedPreferences();

        return response()->json(['data' => [
            'channels' => config('ledgerfly.notifications.channels'),
            'events' => NotificationPreferences::for($user)->channels(),
            'quiet_hours' => $preferences['quiet_hours'] ?? null,
            'large_transaction_amount' => $preferences['large_transaction_amount'] ?? null,
            'push_public_key' => config('webpush.vapid.public_key'),
            'ntfy' => ($ntfy = NtfyChannel::settings($user)) === null ? null : [
                'server' => $ntfy['server'],
                'topic' => $ntfy['topic'],
                'has_token' => $ntfy['token'] !== null,
                'private' => $ntfy['private'],
            ],
            'ntfy_default_server' => config('ledgerfly.notifications.ntfy_server'),
        ]]);
    }

    public function updateSettings(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $channels = config()->array('ledgerfly.notifications.channels');
        $events = array_keys(config()->array('ledgerfly.notifications.events'));

        $validated = $request->validate([
            'events' => ['sometimes', 'array'],
            'events.*' => ['array'],
            'events.*.*' => [Rule::in($channels)],
            'quiet_hours' => ['nullable', 'array'],
            'quiet_hours.start' => ['required_with:quiet_hours', 'date_format:H:i'],
            'quiet_hours.end' => ['required_with:quiet_hours', 'date_format:H:i'],
            'large_transaction_amount' => ['nullable', 'numeric', 'gt:0'],
            'ntfy' => ['nullable', 'array'],
            'ntfy.server' => ['required_with:ntfy', 'url:http,https', 'max:255'],
            'ntfy.topic' => ['required_with:ntfy', 'regex:/^[A-Za-z0-9_-]{1,64}$/'],
            'ntfy.token' => ['nullable', 'string', 'max:255'],
            'ntfy.private' => ['sometimes', 'boolean'],
        ]);

        $preferences = $user->preferences ?? [];

        if (isset($validated['events'])) {
            $preferences['notifications'] = array_merge(
                (array) ($preferences['notifications'] ?? []),
                array_intersect_key($validated['events'], array_flip($events)),
            );
        }

        foreach (['quiet_hours', 'large_transaction_amount'] as $key) {
            if ($request->exists($key)) {
                $preferences[$key] = $validated[$key] ?? null;
            }
        }

        if ($request->exists('ntfy')) {
            $preferences['ntfy'] = $this->ntfyPreferences($validated['ntfy'] ?? null, $request->has('ntfy.token'), $preferences['ntfy'] ?? null);
        }

        $user->update(['preferences' => $preferences]);

        return $this->settings($user->refresh());
    }

    public function testNtfy(#[CurrentUser] User $user): JsonResponse
    {
        try {
            $response = NtfyChannel::publish($user, __('LedgerFly is connected'), __('Alerts will arrive here from now on.'), url('/'));
        } catch (ConnectionException) {
            return response()->json(['message' => __('Could not reach the ntfy server. Check its address.')], 422);
        }

        if ($response === null) {
            return response()->json(['message' => __('Set up ntfy first.')], 422);
        }

        if ($response->failed()) {
            return response()->json(['message' => __('ntfy answered with :status. Check the server, topic and token.', ['status' => $response->status()])], 422);
        }

        return response()->json(['data' => ['sent' => true]]);
    }

    /**
     * Keeps the saved token when a change leaves it out, and stores tokens encrypted.
     *
     * @param  array<string, mixed>|null  $input
     * @return array<string, mixed>|null
     */
    private function ntfyPreferences(?array $input, bool $tokenGiven, mixed $current): ?array
    {
        if ($input === null) {
            return null;
        }

        $token = $tokenGiven
            ? (is_string($input['token'] ?? null) && $input['token'] !== '' ? Crypt::encryptString($input['token']) : null)
            : (is_array($current) ? ($current['token'] ?? null) : null);

        return [
            'server' => rtrim((string) $input['server'], '/'),
            'topic' => (string) $input['topic'],
            'token' => $token,
            'private' => (bool) ($input['private'] ?? true),
        ];
    }

    public function subscribe(Request $request, #[CurrentUser] User $user): Response
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url', 'max:2048'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'content_encoding' => ['nullable', 'string'],
        ]);

        $user->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['content_encoding'] ?? 'aesgcm',
        );

        return response()->noContent();
    }

    public function unsubscribe(Request $request, #[CurrentUser] User $user): Response
    {
        $user->deletePushSubscription((string) $request->validate(['endpoint' => ['required', 'string']])['endpoint']);

        return response()->noContent();
    }
}
