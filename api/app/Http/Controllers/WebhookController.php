<?php

namespace App\Http\Controllers;

use App\Jobs\DeliverWebhook;
use App\Models\Ledger;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WebhookController extends Controller
{
    public function index(Ledger $ledger): JsonResponse
    {
        return response()->json(['data' => $ledger->webhooks()->latest()->get()->map(fn (Webhook $webhook) => $this->present($webhook))]);
    }

    public function store(Request $request, Ledger $ledger): JsonResponse
    {
        $webhook = $ledger->webhooks()->create([...$this->validated($request, true), 'secret' => Str::random(40)]);

        return response()->json(['data' => [...$this->present($webhook), 'secret' => $webhook->secret]], Response::HTTP_CREATED);
    }

    public function update(Request $request, Ledger $ledger, Webhook $webhook): JsonResponse
    {
        $webhook->update($this->validated($request, false));

        return response()->json(['data' => $this->present($webhook)]);
    }

    public function destroy(Ledger $ledger, Webhook $webhook): Response
    {
        $webhook->delete();

        return response()->noContent();
    }

    public function secret(Ledger $ledger, Webhook $webhook): JsonResponse
    {
        $webhook->update(['secret' => Str::random(40)]);

        return response()->json(['data' => ['secret' => $webhook->secret]]);
    }

    public function deliveries(Ledger $ledger, Webhook $webhook): JsonResponse
    {
        return response()->json(['data' => $webhook->deliveries()->limit(50)->get()->map(fn (WebhookDelivery $delivery) => [
            'id' => $delivery->id,
            'event' => $delivery->event,
            'status_code' => $delivery->getAttribute('status_code'),
            'attempts' => $delivery->getAttribute('attempts'),
            'response' => $delivery->getAttribute('response'),
            'delivered_at' => $delivery->delivered_at,
            'failed_at' => $delivery->failed_at,
            'created_at' => $delivery->created_at,
        ])]);
    }

    public function redeliver(Ledger $ledger, Webhook $webhook, string $delivery): Response
    {
        $original = $webhook->deliveries()->findOrFail($delivery);
        DeliverWebhook::dispatch($webhook->deliveries()->create(['event' => $original->event, 'payload' => $original->payload]));

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'url' => [$required, 'url:https,http', 'max:2048'],
            'events' => [$required, 'array', 'min:1'],
            'events.*' => [Rule::in(Webhook::EVENTS)],
            'active' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Webhook $webhook): array
    {
        return [
            'id' => $webhook->id,
            'title' => $webhook->title,
            'url' => $webhook->url,
            'events' => $webhook->events,
            'active' => $webhook->active,
            'created_at' => $webhook->created_at,
        ];
    }
}
