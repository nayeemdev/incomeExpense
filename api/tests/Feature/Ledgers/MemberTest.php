<?php

use App\Enums\LedgerRole;
use App\Models\User;
use App\Notifications\LedgerInvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

it('invites a person and lets them accept', function () {
    Notification::fake();
    $ledger = actingAsMember();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/invitations", ['email' => 'Partner@Example.com', 'role' => 'editor'])
        ->assertCreated()
        ->assertJsonPath('data.email', 'partner@example.com');

    $token = null;
    Notification::assertSentTo(new AnonymousNotifiable, LedgerInvitationNotification::class, function ($notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    $partner = User::factory()->create(['email' => 'partner@example.com']);
    Sanctum::actingAs($partner, ['*']);

    $this->getJson("/api/v1/invitations/{$token}")->assertJsonPath('data.ledger.name', $ledger->name);
    $this->postJson("/api/v1/invitations/{$token}/accept")->assertOk()->assertJsonPath('data.role', 'editor');

    expect($ledger->invitations()->count())->toBe(0);
    $this->getJson("/api/v1/invitations/{$token}")->assertNotFound();
});

it('refuses an invitation meant for someone else', function () {
    Notification::fake();
    $ledger = actingAsMember();
    $this->postJson("/api/v1/ledgers/{$ledger->id}/invitations", ['email' => 'partner@example.com', 'role' => 'viewer']);

    $token = null;
    Notification::assertSentTo(new AnonymousNotifiable, LedgerInvitationNotification::class, function ($notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    Sanctum::actingAs(User::factory()->create(), ['*']);
    $this->postJson("/api/v1/invitations/{$token}/accept")->assertForbidden();
});

it('does not invite existing members', function () {
    $ledger = actingAsMember();
    $member = User::factory()->create();
    $ledger->addMember($member, LedgerRole::Viewer);

    $this->postJson("/api/v1/ledgers/{$ledger->id}/invitations", ['email' => $member->email, 'role' => 'editor'])
        ->assertJsonValidationErrors('email');
});

it('changes member roles but keeps at least one owner', function () {
    $ledger = actingAsMember();
    $owner = $ledger->members()->sole();
    $member = User::factory()->create();
    $ledger->addMember($member, LedgerRole::Viewer);

    $this->patchJson("/api/v1/ledgers/{$ledger->id}/members/{$member->id}", ['role' => 'editor'])
        ->assertOk()
        ->assertJsonPath('data.role', 'editor');

    $this->patchJson("/api/v1/ledgers/{$ledger->id}/members/{$owner->id}", ['role' => 'viewer'])
        ->assertJsonValidationErrors('role');
});

it('lets a member leave but not the last owner', function () {
    $ledger = actingAsMember(LedgerRole::Editor);
    $editor = $ledger->members()->sole();
    $owner = User::factory()->create();
    $ledger->addMember($owner, LedgerRole::Owner);

    $this->deleteJson("/api/v1/ledgers/{$ledger->id}/members/{$owner->id}")->assertForbidden();
    $this->deleteJson("/api/v1/ledgers/{$ledger->id}/members/{$editor->id}")->assertNoContent();

    Sanctum::actingAs($owner, ['*']);
    $this->deleteJson("/api/v1/ledgers/{$ledger->id}/members/{$owner->id}")->assertJsonValidationErrors('role');
});
