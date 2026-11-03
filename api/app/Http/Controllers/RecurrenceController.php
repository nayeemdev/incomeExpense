<?php

namespace App\Http\Controllers;

use App\Actions\Recurrences\CreateRecurringTransaction;
use App\Actions\Recurrences\RecurrenceSchedule;
use App\Actions\Transactions\ResolveSplitReferences;
use App\Enums\RepetitionType;
use App\Enums\TransactionType;
use App\Enums\WeekendHandling;
use App\Http\Requests\RecurrenceRequest;
use App\Http\Resources\RecurrenceResource;
use App\Http\Resources\TransactionGroupResource;
use App\Models\Ledger;
use App\Models\Recurrence;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class RecurrenceController extends Controller
{
    public function __construct(
        private RecurrenceSchedule $schedule,
        private ResolveSplitReferences $references,
    ) {}

    public function index(Ledger $ledger): AnonymousResourceCollection
    {
        $recurrences = $ledger->recurrences()->with($this->relations())->orderByDesc('active')->orderBy('title')->get();
        $recurrences->each(fn (Recurrence $recurrence) => $this->attachUpcoming($recurrence, 3));

        return RecurrenceResource::collection($recurrences);
    }

    public function store(RecurrenceRequest $request, Ledger $ledger, #[CurrentUser] User $user, CreateRecurringTransaction $create): JsonResponse
    {
        $recurrence = DB::transaction(function () use ($request, $ledger, $user) {
            $recurrence = $ledger->recurrences()->create([...$this->attributes($request), 'created_by' => $user->id]);
            $this->saveDetails($recurrence, $request);

            return $recurrence;
        });

        $create->createDue($recurrence->refresh()->load('repetitions'), CarbonImmutable::today());

        return (new RecurrenceResource($this->present($recurrence)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Ledger $ledger, Recurrence $recurrence): RecurrenceResource
    {
        return new RecurrenceResource($this->present($recurrence, 10));
    }

    public function update(RecurrenceRequest $request, Ledger $ledger, Recurrence $recurrence): RecurrenceResource
    {
        DB::transaction(function () use ($request, $recurrence) {
            $recurrence->update($this->attributes($request));
            $this->saveDetails($recurrence, $request);
        });

        return new RecurrenceResource($this->present($recurrence));
    }

    public function destroy(Ledger $ledger, Recurrence $recurrence): Response
    {
        $recurrence->delete();

        return response()->noContent();
    }

    public function trigger(Request $request, Ledger $ledger, Recurrence $recurrence, CreateRecurringTransaction $create): JsonResponse
    {
        $date = CarbonImmutable::parse($request->validate(['date' => ['nullable', 'date']])['date'] ?? 'today');
        $group = $create->createFor($recurrence, $date);

        abort_if($group === null, 409, __('A transaction already exists for this date.'));

        return (new TransactionGroupResource($group->load('transactions.sourceAccount', 'transactions.destinationAccount', 'transactions.currency', 'transactions.foreignCurrency')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(RecurrenceRequest $request): array
    {
        return collect($request->validated())->except(['repetition', 'transactions'])->all();
    }

    private function saveDetails(Recurrence $recurrence, RecurrenceRequest $request): void
    {
        if ($request->has('repetition')) {
            $repetition = (array) $request->input('repetition');
            $recurrence->repetitions()->delete();
            $recurrence->repetitions()->create([
                'type' => RepetitionType::from((string) $repetition['type']),
                'moment' => (string) ($repetition['moment'] ?? ''),
                'skip' => (int) ($repetition['skip'] ?? 0),
                'weekend' => WeekendHandling::tryFrom((string) ($repetition['weekend'] ?? '')) ?? WeekendHandling::Keep,
            ]);
        }

        if (! $request->has('transactions')) {
            return;
        }

        $ledger = $recurrence->ledger;
        $type = TransactionType::from($request->string('type', $recurrence->type->value)->value());
        $recurrence->transactions()->delete();

        foreach ((array) $request->input('transactions') as $index => $split) {
            $errorKey = "transactions.{$index}";
            $recurrence->transactions()->create([
                'description' => $split['description'],
                'amount' => $split['amount'],
                'foreign_amount' => $split['foreign_amount'] ?? null,
                'foreign_currency_id' => $split['foreign_currency_id'] ?? null,
                'source_account_id' => $this->references->account($ledger, $type, $split, 'source', $errorKey)->id,
                'destination_account_id' => $this->references->account($ledger, $type, $split, 'destination', $errorKey)->id,
                'category_id' => $this->references->categoryId($ledger, $split),
                'budget_id' => $this->references->ownedId($ledger->budgets(), $split['budget_id'] ?? null, "{$errorKey}.budget_id"),
                'bill_id' => $this->references->ownedId($ledger->bills(), $split['bill_id'] ?? null, "{$errorKey}.bill_id"),
                'tags' => array_values(array_filter(array_map(fn ($tag) => trim((string) $tag), $split['tags'] ?? []))),
                'notes' => $split['notes'] ?? null,
                'order' => $index,
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'repetitions',
            'transactions.sourceAccount.currency',
            'transactions.destinationAccount.currency',
            'transactions.category',
            'transactions.budget',
            'transactions.bill',
        ];
    }

    private function present(Recurrence $recurrence, int $upcoming = 3): Recurrence
    {
        $recurrence->refresh()->load($this->relations());
        $this->attachUpcoming($recurrence, $upcoming);

        return $recurrence;
    }

    private function attachUpcoming(Recurrence $recurrence, int $count): void
    {
        $from = CarbonImmutable::tomorrow()->max($recurrence->first_date);
        $recurrence->setAttribute('upcoming', $recurrence->active ? $this->schedule->upcoming($recurrence, $from, $count) : []);
    }
}
