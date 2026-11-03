<?php

namespace App\Http\Controllers;

use App\Enums\LedgerRole;
use App\Http\Requests\LedgerRequest;
use App\Http\Resources\LedgerResource;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class LedgerController extends Controller
{
    public function index(#[CurrentUser] User $user): AnonymousResourceCollection
    {
        return LedgerResource::collection(
            $user->ledgers()->with('primaryCurrency')->orderBy('name')->get(),
        );
    }

    public function store(LedgerRequest $request, #[CurrentUser] User $user): LedgerResource
    {
        $ledger = DB::transaction(function () use ($request, $user) {
            $ledger = Ledger::create($request->validated());
            $ledger->addMember($user, LedgerRole::Owner);

            return $ledger;
        });

        return new LedgerResource($ledger->load('primaryCurrency'));
    }

    public function show(Ledger $ledger): LedgerResource
    {
        return new LedgerResource($ledger->load('primaryCurrency'));
    }

    public function update(LedgerRequest $request, Ledger $ledger): LedgerResource
    {
        $ledger->update($request->validated());

        return new LedgerResource($ledger->load('primaryCurrency'));
    }

    public function destroy(Ledger $ledger): Response
    {
        $ledger->delete();

        return response()->noContent();
    }
}
