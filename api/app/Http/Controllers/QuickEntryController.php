<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\RecordQuickEntry;
use App\Http\Requests\QuickEntryRequest;
use App\Http\Resources\TransactionGroupResource;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class QuickEntryController extends Controller
{
    public function __invoke(QuickEntryRequest $request, Ledger $ledger, #[CurrentUser] User $user, RecordQuickEntry $record): JsonResponse
    {
        /** @var array{amount: string, description: string, type?: ?string, account_id?: ?int, counterparty?: ?string, category?: ?string, date?: ?string, notes?: ?string} $input */
        $input = $request->validated();

        $group = $record->handle($ledger, $user, $input)->load([
            'transactions.sourceAccount.currency',
            'transactions.destinationAccount.currency',
            'transactions.currency',
            'transactions.foreignCurrency',
            'transactions.category',
            'transactions.budget',
            'transactions.bill',
            'transactions.tags',
        ]);

        return (new TransactionGroupResource($group))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
