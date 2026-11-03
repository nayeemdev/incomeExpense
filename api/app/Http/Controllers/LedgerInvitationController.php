<?php

namespace App\Http\Controllers;

use App\Enums\LedgerRole;
use App\Http\Resources\LedgerInvitationResource;
use App\Models\Ledger;
use App\Models\LedgerInvitation;
use App\Models\User;
use App\Notifications\LedgerInvitationNotification;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LedgerInvitationController extends Controller
{
    public function index(Ledger $ledger): AnonymousResourceCollection
    {
        return LedgerInvitationResource::collection($ledger->invitations()->latest()->get());
    }

    public function store(Request $request, Ledger $ledger, #[CurrentUser] User $user): LedgerInvitationResource
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::enum(LedgerRole::class)],
        ]);
        $email = Str::lower($validated['email']);

        if ($ledger->members()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => __('This person is already a member.')]);
        }

        $token = Str::random(48);
        $invitation = $ledger->invitations()->updateOrCreate(['email' => $email], [
            'role' => $validated['role'],
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDays(config()->integer('ledgerfly.invitation_expiry_days')),
            'invited_by' => $user->id,
        ]);

        Notification::route('mail', $email)->notify(new LedgerInvitationNotification($invitation, $token));

        return new LedgerInvitationResource($invitation);
    }

    public function destroy(Ledger $ledger, LedgerInvitation $invitation): Response
    {
        $invitation->delete();

        return response()->noContent();
    }
}
