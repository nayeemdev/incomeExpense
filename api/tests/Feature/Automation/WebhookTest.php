<?php

use App\Enums\LedgerRole;
use App\Jobs\DeliverWebhook;
use App\Models\Account;
use App\Models\WebhookDelivery;
use App\Notifications\WebhookFailingNotification;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

it('delivers signed webhooks for transaction events', function () {
    Http::fake(['hooks.example.com/*' => Http::response('ok')]);
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();

    $secret = $this->postJson("/api/v1/ledgers/{$ledger->id}/webhooks", [
        'title' => 'Sync', 'url' => 'https://hooks.example.com/ledger', 'events' => ['transaction.created', 'transaction.deleted'],
    ])->assertCreated()->json('data.secret');

    $group = $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
        'type' => 'withdrawal', 'date' => '2026-03-10', 'description' => 'Lunch', 'amount' => '12',
        'source_id' => $checking->id, 'destination_name' => 'Cafe',
    ]]])->json('data.id');
    $this->deleteJson("/api/v1/ledgers/{$ledger->id}/transactions/{$group}")->assertNoContent();

    Http::assertSentCount(2);
    Http::assertSent(function (Request $request) use ($secret) {
        [$timestamp, $signature] = array_map(fn ($part) => explode('=', $part, 2)[1], explode(',', $request->header('X-LedgerFly-Signature')[0]));

        return $request['event'] === 'transaction.created'
            && $request['data']['transactions'][0]['description'] === 'Lunch'
            && hash_equals(hash_hmac('sha256', "{$timestamp}.{$request->body()}", $secret), $signature);
    });

    expect(WebhookDelivery::query()->whereNotNull('delivered_at')->count())->toBe(2);
});

it('records failures and tells owners once retries are used up', function () {
    Notification::fake();
    Queue::fake();
    Http::fake(['*' => Http::response('nope', 500)]);
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();
    $this->postJson("/api/v1/ledgers/{$ledger->id}/webhooks", ['title' => 'Broken', 'url' => 'https://hooks.example.com/x', 'events' => ['transaction.created']]);

    $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
        'type' => 'withdrawal', 'date' => '2026-03-10', 'description' => 'Lunch', 'amount' => '12',
        'source_id' => $checking->id, 'destination_name' => 'Cafe',
    ]]])->assertCreated();

    Queue::assertPushed(DeliverWebhook::class);
    $job = new DeliverWebhook(WebhookDelivery::sole());

    expect(fn () => $job->handle())->toThrow(RuntimeException::class);
    $job->failed(null);

    expect(WebhookDelivery::sole())->status_code->toBe(500)->failed_at->not->toBeNull();
    Notification::assertSentTo($ledger->members()->sole(), WebhookFailingNotification::class);
});

it('lets only owners manage webhooks', function () {
    $ledger = actingAsMember(LedgerRole::Editor);

    $this->getJson("/api/v1/ledgers/{$ledger->id}/webhooks")->assertForbidden();
});
