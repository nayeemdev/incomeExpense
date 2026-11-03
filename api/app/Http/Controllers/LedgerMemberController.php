<?php

namespace App\Http\Controllers;

use App\Enums\LedgerRole;
use App\Http\Resources\LedgerMemberResource;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LedgerMemberController extends Controller
{
    public function index(Ledger $ledger): AnonymousResourceCollection
    {
        return LedgerMemberResource::collection($ledger->members()->orderBy('name')->get());
    }

    public function update(Request $request, Ledger $ledger, User $member): LedgerMemberResource
    {
        Gate::authorize('manage', $ledger);

        $role = LedgerRole::from($request->validate([
            'role' => ['required', Rule::enum(LedgerRole::class)],
        ])['role']);

        if ($role !== LedgerRole::Owner) {
            $this->ensureAnotherOwnerRemains($ledger, $member);
        }

        $ledger->members()->updateExistingPivot($member->id, ['role' => $role->value]);

        return new LedgerMemberResource($ledger->members()->findOrFail($member->id));
    }

    public function destroy(Ledger $ledger, User $member, #[CurrentUser] User $user): Response
    {
        if ($member->isNot($user)) {
            Gate::authorize('manage', $ledger);
        }

        $this->ensureAnotherOwnerRemains($ledger, $member);
        $ledger->members()->detach($member->id);

        return response()->noContent();
    }

    private function ensureAnotherOwnerRemains(Ledger $ledger, User $member): void
    {
        if ($ledger->roleOf($member) !== LedgerRole::Owner) {
            return;
        }

        $otherOwners = $ledger->members()
            ->wherePivot('role', LedgerRole::Owner->value)
            ->whereKeyNot($member->id)
            ->exists();

        if (! $otherOwners) {
            throw ValidationException::withMessages([
                'role' => __('A ledger needs at least one owner.'),
            ]);
        }
    }
}
