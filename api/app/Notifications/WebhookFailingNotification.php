<?php

namespace App\Notifications;

use App\Models\Webhook;

class WebhookFailingNotification extends LedgerNotification
{
    public function __construct(public Webhook $webhook, public ?int $statusCode) {}

    public function event(): string
    {
        return 'webhook_failing';
    }

    public function ledgerId(): int
    {
        return $this->webhook->ledger_id;
    }

    public function title(): string
    {
        return __('Webhook ":title" is failing', ['title' => $this->webhook->title]);
    }

    public function body(): string
    {
        return $this->statusCode
            ? __('The last delivery was answered with status :status after all retries.', ['status' => $this->statusCode])
            : __('The last delivery could not reach the server after all retries.');
    }

    public function path(): string
    {
        return "/{$this->webhook->ledger_id}/settings?tab=webhooks";
    }
}
