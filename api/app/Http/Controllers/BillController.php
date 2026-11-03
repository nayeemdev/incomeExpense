<?php

namespace App\Http\Controllers;

use App\Actions\ResolveObjectGroup;
use App\Enums\RepeatFrequency;
use App\Http\Resources\BillResource;
use App\Models\Bill;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Rules\Amount;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class BillController extends Controller
{
    public function __construct(private ResolveObjectGroup $objectGroup) {}

    public function index(Request $request, Ledger $ledger): AnonymousResourceCollection
    {
        [$start, $end] = $this->period($request);

        $bills = $ledger->bills()
            ->with('currency', 'objectGroup')
            ->when($request->has('active'), fn ($query) => $query->where('active', $request->boolean('active')))
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        $bills->each(fn (Bill $bill) => $this->attachSchedule($bill, $start, $end));

        return BillResource::collection($bills);
    }

    public function store(Request $request, Ledger $ledger): JsonResponse
    {
        $bill = $ledger->bills()->create($this->objectGroup->handle($ledger, $this->validated($request, $ledger)));

        return (new BillResource($bill->load('currency', 'objectGroup')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Ledger $ledger, Bill $bill): BillResource
    {
        [$start, $end] = $this->period($request);
        $this->attachSchedule($bill->load('currency', 'objectGroup'), $start, $end);

        return new BillResource($bill);
    }

    public function update(Request $request, Ledger $ledger, Bill $bill): BillResource
    {
        $bill->update($this->objectGroup->handle($ledger, $this->validated($request, $ledger, $bill)));

        return new BillResource($bill->load('currency', 'objectGroup'));
    }

    public function destroy(Ledger $ledger, Bill $bill): Response
    {
        $bill->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Ledger $ledger, ?Bill $bill = null): array
    {
        $required = $bill ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:255', Rule::unique('bills')->where('ledger_id', $ledger->id)->ignore($bill?->id)],
            'currency_id' => [$required, Rule::exists('currencies', 'id')],
            'amount_min' => [$required, Amount::positive()],
            'amount_max' => [$required, Amount::positive(), 'gte:amount_min'],
            'date' => [$required, 'date'],
            'repeat_freq' => [$required, Rule::enum(RepeatFrequency::class)],
            'skip' => ['sometimes', 'integer', 'min:0', 'max:31'],
            'end_date' => ['nullable', 'date', 'after:date'],
            'extension_date' => ['nullable', 'date'],
            'active' => ['sometimes', 'boolean'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:65000'],
            'object_group' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function period(Request $request): array
    {
        $validated = $request->validate([
            'start' => ['nullable', 'date', 'required_with:end'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
        ]);
        $start = isset($validated['start']) ? CarbonImmutable::parse($validated['start']) : CarbonImmutable::today()->startOfMonth();

        return [$start, isset($validated['end']) ? CarbonImmutable::parse($validated['end']) : $start->endOfMonth()];
    }

    private function attachSchedule(Bill $bill, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $decimals = $bill->currency->decimal_places;

        $bill->setAttribute('expected_dates', array_map(fn (CarbonImmutable $date) => $date->toDateString(), $bill->expectedDates($start, $end)));
        $bill->setAttribute('next_expected_date', $bill->active ? $bill->nextExpectedDate(CarbonImmutable::today())?->toDateString() : null);
        $bill->setAttribute('payments', $bill->transactions()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->get(['id', 'transaction_group_id', 'date', 'amount', 'description'])
            ->map(fn (Transaction $transaction) => [
                'transaction_id' => $transaction->id,
                'group_id' => $transaction->transaction_group_id,
                'date' => $transaction->date->toDateString(),
                'description' => $transaction->description,
                'amount' => Money::round($transaction->amount, $decimals),
            ])
            ->all());
    }
}
