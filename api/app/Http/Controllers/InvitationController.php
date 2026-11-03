<?php

namespace App\Http\Controllers;

use App\Http\Resources\LedgerInvitationResource;
use App\Http\Resources\LedgerResource;
use App\Models\LedgerInvitation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    public function show(string $token): LedgerInvitationResource
    {
        $invitation = LedgerInvitation::findByPlainToken($token) ?? abort(404);

        return new LedgerInvitationResource($invitation->load('ledger', 'inviter'));
    }

    public function accept(string $token, #[CurrentUser] User $user): LedgerResource
    {
        $invitation = LedgerInvitation::findByPlainToken($token) ?? abort(404);

        abort_unless(Str::lower($user->email) === $invitation->email, 403, __('This invitation was sent to a different email address.'));

        DB::transaction(function () use ($invitation, $user) {
            $invitation->ledger->addMember($user, $invitation->role);
            $invitation->delete();
        });

        return new LedgerResource($invitation->ledger->load('primaryCurrency'));
    }
}
