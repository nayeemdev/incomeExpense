<?php

namespace App\Http\Controllers;

use App\Http\Resources\RuleResource;
use App\Http\Resources\TransactionResource;
use App\Models\Ledger;
use App\Models\Rule;
use App\RuleEngine\ActionType;
use App\RuleEngine\RuleEngine;
use App\RuleEngine\TriggerType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule as ValidationRule;
use Illuminate\Validation\Validator;

class RuleController extends Controller
{
    public function __construct(private RuleEngine $engine) {}

    /**
     * Everything a client needs to build the rule editor.
     */
    public function options(): JsonResponse
    {
        return response()->json(['data' => [
            'triggers' => array_map(fn (TriggerType $type) => ['type' => $type->value, 'needs_value' => $type->needsValue()], TriggerType::cases()),
            'actions' => array_map(fn (ActionType $type) => ['type' => $type->value, 'needs_value' => $type->needsValue()], ActionType::cases()),
        ]]);
    }

    public function store(Request $request, Ledger $ledger): JsonResponse
    {
        $validated = $this->validated($request, $ledger);

        $rule = DB::transaction(function () use ($ledger, $validated) {
            $rule = $ledger->rules()->create(collect($validated)->except(['triggers', 'actions'])->all());
            $this->saveParts($rule, $validated);

            return $rule;
        });

        return (new RuleResource($rule->load('triggers', 'actions')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Ledger $ledger, Rule $rule): RuleResource
    {
        return new RuleResource($rule->load('triggers', 'actions'));
    }

    public function update(Request $request, Ledger $ledger, Rule $rule): RuleResource
    {
        $validated = $this->validated($request, $ledger, $rule);

        DB::transaction(function () use ($rule, $validated) {
            $rule->update(collect($validated)->except(['triggers', 'actions'])->all());
            $this->saveParts($rule, $validated);
        });

        return new RuleResource($rule->load('triggers', 'actions'));
    }

    public function destroy(Ledger $ledger, Rule $rule): Response
    {
        $rule->delete();

        return response()->noContent();
    }

    /**
     * Lists existing transactions the rule would match, without changing anything.
     */
    public function test(Request $request, Ledger $ledger, Rule $rule): JsonResponse
    {
        $period = $this->period($request);
        $rule->load('triggers', 'actions');
        $limit = config()->integer('ledgerfly.pagination.max_per_page');
        $matches = [];

        foreach ($this->engine->candidates($ledger, $period['start'] ?? null, $period['end'] ?? null)->lazy() as $transaction) {
            if ($this->engine->matches($rule, $transaction)) {
                $matches[] = $transaction;
            }

            if (count($matches) >= $limit) {
                break;
            }
        }

        return response()->json(['data' => TransactionResource::collection($matches)]);
    }

    public function apply(Request $request, Ledger $ledger, Rule $rule): JsonResponse
    {
        $period = $this->period($request);
        $rule->load('triggers', 'actions', 'group');
        $applied = 0;

        foreach ($this->engine->candidates($ledger, $period['start'] ?? null, $period['end'] ?? null)->get() as $transaction) {
            if ($this->engine->matches($rule, $transaction)) {
                $this->engine->execute($rule, $transaction);
                $applied++;
            }
        }

        return response()->json(['data' => ['applied' => $applied]]);
    }

    /**
     * @return array{start?: string, end?: string}
     */
    private function period(Request $request): array
    {
        /** @var array{start?: string, end?: string} */
        return $request->validate([
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Ledger $ledger, ?Rule $rule = null): array
    {
        $required = $rule ? 'sometimes' : 'required';

        return validator($request->all(), [
            'rule_group_id' => [$required, 'integer', ValidationRule::exists('rule_groups', 'id')->where('ledger_id', $ledger->id)],
            'title' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65000'],
            'trigger_on' => ['sometimes', ValidationRule::in(['store', 'update', 'both'])],
            'strict' => ['sometimes', 'boolean'],
            'stop_processing' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'triggers' => [$required, 'array', 'min:1', 'max:30'],
            'triggers.*.type' => ['required', ValidationRule::enum(TriggerType::class)],
            'triggers.*.value' => ['nullable', 'string', 'max:255'],
            'triggers.*.negated' => ['sometimes', 'boolean'],
            'actions' => [$required, 'array', 'min:1', 'max:30'],
            'actions.*.type' => ['required', ValidationRule::enum(ActionType::class)],
            'actions.*.value' => ['nullable', 'string', 'max:255'],
        ])->after(function (Validator $validator) use ($request) {
            foreach (['triggers' => TriggerType::class, 'actions' => ActionType::class] as $key => $enum) {
                foreach ((array) $request->input($key, []) as $index => $part) {
                    $type = $enum::tryFrom((string) ($part['type'] ?? ''));

                    if ($type?->needsValue() && trim((string) ($part['value'] ?? '')) === '') {
                        $validator->errors()->add("{$key}.{$index}.value", __('This needs a value.'));
                    }
                }
            }
        })->validate();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function saveParts(Rule $rule, array $validated): void
    {
        if (isset($validated['triggers'])) {
            $rule->triggers()->delete();
            foreach (array_values((array) $validated['triggers']) as $index => $trigger) {
                $rule->triggers()->create([...$trigger, 'order' => $index]);
            }
        }

        if (isset($validated['actions'])) {
            $rule->actions()->delete();
            foreach (array_values((array) $validated['actions']) as $index => $action) {
                $rule->actions()->create([...$action, 'order' => $index]);
            }
        }
    }
}
