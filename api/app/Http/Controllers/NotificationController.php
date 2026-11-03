<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\NotificationPreferences;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;
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

        $user->update(['preferences' => $preferences]);

        return $this->settings($user->refresh());
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
