<?php

namespace App\Http\Controllers;

use App\Http\Resources\LedgerResource;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MembershipController extends Controller
{
    public function update(Request $request, Ledger $ledger, #[CurrentUser] User $user): LedgerResource
    {
        $ownAccount = ['sometimes', 'nullable', Rule::exists('accounts', 'id')->where('ledger_id', $ledger->id)->where('type', 'asset')->whereNull('deleted_at')];

        $validated = $request->validate([
            'default_account_id' => $ownAccount,
            'default_income_account_id' => $ownAccount,
        ]);

        $ledger->members()->updateExistingPivot($user->id, $validated);

        return new LedgerResource(Ledger::query()->with('primaryCurrency')->findOrFail($ledger->id));
    }
}
