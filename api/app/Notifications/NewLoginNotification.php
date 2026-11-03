<?php

namespace App\Notifications;

class NewLoginNotification extends LedgerNotification
{
    public function __construct(public ?string $ip, public ?string $userAgent) {}

    public function event(): string
    {
        return 'new_login';
    }

    public function title(): string
    {
        return __('New sign-in to your LedgerFly account');
    }

    public function body(): string
    {
        return __('Someone signed in from :ip using :agent. If this was not you, change your password and turn on two-factor authentication.', [
            'ip' => $this->ip ?? __('an unknown address'),
            'agent' => $this->userAgent ?? __('an unknown browser'),
        ]);
    }

    public function path(): string
    {
        return '/';
    }
}
