<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Notifications\WebhookFailingNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [30, 300, 1800];

    public function __construct(public WebhookDelivery $delivery) {}

    public function handle(): void
    {
        $webhook = $this->delivery->webhook;
        $body = (string) json_encode($this->delivery->payload);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $webhook->secret);

        $this->delivery->increment('attempts');

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => config('app.name').'-Webhooks',
                    'X-LedgerFly-Event' => $this->delivery->event,
                    'X-LedgerFly-Delivery' => (string) $this->delivery->id,
                    'X-LedgerFly-Signature' => "t={$timestamp},v1={$signature}",
                ])
                ->withBody($body, 'application/json')
                ->post($webhook->url);
        } catch (ConnectionException $exception) {
            $this->delivery->update(['response' => $exception->getMessage()]);

            throw $exception;
        }

        $this->delivery->update([
            'status_code' => $response->status(),
            'response' => mb_substr($response->body(), 0, 2000),
            'delivered_at' => $response->successful() ? now() : null,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("Webhook answered with status {$response->status()}.");
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->delivery->update(['failed_at' => now()]);
        $webhook = $this->delivery->webhook;

        Notification::send(
            $webhook->ledger->members()->wherePivot('role', 'owner')->get(),
            new WebhookFailingNotification($webhook, $this->delivery->getAttribute('status_code')),
        );
    }
}
