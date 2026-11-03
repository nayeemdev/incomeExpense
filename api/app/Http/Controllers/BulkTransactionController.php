<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Events\TransactionGroupDeleted;
use App\Events\TransactionGroupSaved;
use App\Listeners\SendTransactionWebhooks;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\TransactionGroup;
use App\RuleEngine\ActionType;
use App\RuleEngine\RuleEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BulkTransactionController extends Controller
{
    private const array ACTIONS = [
        ActionType::SetCategory, ActionType::ClearCategory, ActionType::SetBudget, ActionType::ClearBudget,
        ActionType::LinkToBill, ActionType::AddTag, ActionType::RemoveTag, ActionType::RemoveAllTags,
        ActionType::SetNotes, ActionType::AppendNotes, ActionType::ClearNotes, ActionType::DeleteTransaction,
    ];

    /**
     * Applies one change to many transactions, reusing the rule actions.
     */
    public function __invoke(Request $request, Ledger $ledger): JsonResponse
    {
        $validated = $request->validate([
            'transaction_ids' => ['required', 'array', 'min:1', 'max:'.config()->integer('ledgerfly.pagination.max_per_page')],
            'transaction_ids.*' => ['ulid'],
            'action' => ['required', Rule::in(array_map(fn (ActionType $action) => $action->value, self::ACTIONS))],
            'value' => ['nullable', 'string', 'max:255'],
        ]);
        $action = ActionType::from($validated['action']);

        if ($action->needsValue() && trim((string) ($validated['value'] ?? '')) === '') {
            throw ValidationException::withMessages(['value' => __('This needs a value.')]);
        }

        $transactions = Transaction::query()
            ->whereBelongsTo($ledger)
            ->whereIn('type', TransactionType::userManaged())
            ->whereKey($validated['transaction_ids'])
            ->with(RuleEngine::TRANSACTION_RELATIONS)
            ->get();

        $groupIds = $transactions->pluck('transaction_group_id')->unique();
        $snapshots = $action === ActionType::DeleteTransaction
            ? TransactionGroup::query()->whereKey($groupIds)->get()->mapWithKeys(fn (TransactionGroup $group) => [$group->id => SendTransactionWebhooks::snapshot($group)])
            : collect();

        DB::transaction(function () use ($transactions, $action, $validated) {
            foreach ($transactions as $transaction) {
                if ($transaction->trashed()) {
                    continue;
                }

                $action->apply($transaction, $validated['value'] ?? null);
            }
        });

        foreach ($groupIds as $groupId) {
            if ($action === ActionType::DeleteTransaction) {
                TransactionGroupDeleted::dispatch($ledger->id, $groupId, $snapshots[$groupId] ?? []);
            } elseif ($group = TransactionGroup::query()->whereKey($groupId)->first()) {
                TransactionGroupSaved::dispatch($group, false);
            }
        }

        return response()->json(['data' => ['updated' => $transactions->count()]]);
    }
}
