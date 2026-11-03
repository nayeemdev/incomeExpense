<?php

namespace App\Listeners;

use App\Events\TransactionGroupDeleted;
use App\Events\TransactionGroupSaved;
use App\Http\Resources\TransactionGroupResource;
use App\Jobs\DeliverWebhook;
use App\Models\TransactionGroup;
use App\Models\Webhook;

class SendTransactionWebhooks
{
    public function handle(TransactionGroupSaved|TransactionGroupDeleted $event): void
    {
        if ($event instanceof TransactionGroupSaved) {
            $ledgerId = $event->group->ledger_id;
            $name = $event->created ? 'transaction.created' : 'transaction.updated';
            $data = null;
        } else {
            $ledgerId = $event->ledgerId;
            $name = 'transaction.deleted';
            $data = $event->snapshot;
        }

        $webhooks = Webhook::query()->where('ledger_id', $ledgerId)->where('active', true)->whereJsonContains('events', $name)->get();

        if ($webhooks->isEmpty()) {
            return;
        }

        $data ??= self::snapshot($event->group);

        foreach ($webhooks as $webhook) {
            $delivery = $webhook->deliveries()->create([
                'event' => $name,
                'payload' => ['event' => $name, 'ledger_id' => $ledgerId, 'created_at' => now()->toIso8601String(), 'data' => $data],
            ]);

            DeliverWebhook::dispatch($delivery);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(TransactionGroup $group): array
    {
        $group->load([
            'transactions.sourceAccount.currency',
            'transactions.destinationAccount.currency',
            'transactions.currency',
            'transactions.foreignCurrency',
            'transactions.category',
            'transactions.budget',
            'transactions.bill',
            'transactions.tags',
        ]);

        return (array) json_decode((string) json_encode(new TransactionGroupResource($group)), true);
    }
}
