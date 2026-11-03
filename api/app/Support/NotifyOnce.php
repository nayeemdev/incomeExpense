<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\LedgerNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class NotifyOnce
{
    /**
     * Sends the notification unless this user already got one with the same key,
     * so scheduled checks can run repeatedly without repeating themselves.
     *
     * @param  iterable<User>  $users
     */
    public static function send(iterable $users, string $key, LedgerNotification $notification): void
    {
        foreach ($users as $user) {
            try {
                DB::table('sent_notifications')->insert(['id' => strtolower((string) Str::ulid()), 'user_id' => $user->id, 'key' => $key, 'created_at' => now()]);
            } catch (UniqueConstraintViolationException) {
                continue;
            }

            $user->notify($notification);
        }
    }
}
