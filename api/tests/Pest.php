<?php

use App\Enums\LedgerRole;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function actingAsMember(LedgerRole $role = LedgerRole::Owner, ?Ledger $ledger = null): Ledger
{
    $user = User::factory()->create();
    $ledger ??= Ledger::factory()->create();
    $ledger->addMember($user, $role);
    Sanctum::actingAs($user, ['*']);

    return $ledger;
}
