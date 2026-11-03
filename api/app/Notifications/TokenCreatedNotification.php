<?php

namespace App\Notifications;

class TokenCreatedNotification extends LedgerNotification
{
    public function __construct(public string $name) {}

    public function event(): string
    {
        return 'token_created';
    }

    public function title(): string
    {
        return __('A new access token was created');
    }

    public function body(): string
    {
        return __('The token ":name" can now use your account. Revoke it in your settings if you did not create it.', ['name' => $this->name]);
    }

    public function path(): string
    {
        return '/';
    }
}
