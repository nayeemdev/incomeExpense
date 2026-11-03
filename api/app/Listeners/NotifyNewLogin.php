<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\NewLoginNotification;
use App\Support\NotifyOnce;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotifyNewLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $ip = request()->ip();
        $agent = request()->userAgent();
        $key = 'login:'.sha1($ip.'|'.$agent);

        if ($user->created_at?->greaterThan(now()->subMinutes(10))) {
            DB::table('sent_notifications')->insertOrIgnore(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'key' => $key, 'created_at' => now()]);

            return;
        }

        NotifyOnce::send([$user], $key, new NewLoginNotification($ip, $agent));
    }
}
