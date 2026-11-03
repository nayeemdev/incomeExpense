<?php

use App\Models\Account;
use App\Models\Bill;
use App\Notifications\BillReminderNotification;
use App\Notifications\BudgetAlertNotification;
use App\Notifications\LargeTransactionNotification;
use App\Notifications\LowBalanceNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

it('warns when a budget is almost used and when it is exceeded, once each', function () {
    Notification::fake();
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();
    $budget = $this->postJson("/api/v1/ledgers/{$ledger->id}/budgets", ['name' => 'Food'])->json('data.id');
    $this->postJson("/api/v1/ledgers/{$ledger->id}/budgets/{$budget}/limits", [
        'currency_id' => $ledger->primary_currency_id, 'start_date' => '2026-03-01', 'end_date' => '2026-03-31', 'amount' => '100',
    ]);

    foreach (['50', '35', '5', '20'] as $amount) {
        $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
            'type' => 'withdrawal', 'date' => '2026-03-10', 'description' => 'Food', 'amount' => $amount,
            'source_id' => $checking->id, 'destination_name' => 'Market', 'budget_id' => $budget,
        ]]]);
    }

    $user = $ledger->members()->sole();
    Notification::assertSentToTimes($user, BudgetAlertNotification::class, 2);
});

it('alerts on large transactions above the personal threshold', function () {
    Notification::fake();
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();
    $this->patchJson('/api/v1/notification-settings', ['large_transaction_amount' => 500])->assertOk();

    foreach (['100', '750'] as $amount) {
        $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
            'type' => 'withdrawal', 'date' => '2026-03-10', 'description' => 'Thing', 'amount' => $amount,
            'source_id' => $checking->id, 'destination_name' => 'Shop',
        ]]]);
    }

    Notification::assertSentToTimes($ledger->members()->sole(), LargeTransactionNotification::class, 1);
});

it('alerts when an account drops below its low balance threshold', function () {
    Notification::fake();
    $ledger = actingAsMember();
    $id = $this->postJson("/api/v1/ledgers/{$ledger->id}/accounts", [
        'type' => 'asset', 'name' => 'Checking', 'currency_id' => $ledger->primary_currency_id, 'asset_role' => 'default',
        'opening_balance' => '300', 'opening_balance_date' => '2026-01-01', 'low_balance_threshold' => '100',
    ])->assertJsonPath('data.low_balance_threshold', '100.00')->json('data.id');

    $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
        'type' => 'withdrawal', 'date' => '2026-03-10', 'description' => 'Rent', 'amount' => '250',
        'source_id' => $id, 'destination_name' => 'Landlord',
    ]]]);

    Notification::assertSentTo($ledger->members()->sole(), LowBalanceNotification::class);
});

it('reminds of bills that are due soon or overdue and not paid', function () {
    Notification::fake();
    Date::setTestNow('2026-03-08');
    $ledger = actingAsMember();
    Bill::factory()->for($ledger)->create(['name' => 'Internet', 'date' => '2026-01-10', 'repeat_freq' => 'monthly']);
    Bill::factory()->for($ledger)->create(['name' => 'Gym', 'date' => '2026-01-05', 'repeat_freq' => 'monthly']);

    $this->artisan('notifications:bills')->assertSuccessful();
    $this->artisan('notifications:bills')->assertSuccessful();

    $user = $ledger->members()->sole();
    Notification::assertSentToTimes($user, BillReminderNotification::class, 2);
    Notification::assertSentTo($user, BillReminderNotification::class, fn ($notification) => $notification->overdue && $notification->bill->name === 'Gym');
});

it('lists and marks in-app notifications as read', function () {
    $ledger = actingAsMember();
    $user = $ledger->members()->sole();
    $user->notify(new LowBalanceNotification(Account::factory()->for($ledger)->create(['low_balance_threshold' => '10']), '5'));

    $this->getJson('/api/v1/notifications')
        ->assertJsonPath('meta.unread', 1)
        ->assertJsonPath('data.0.event', 'low_balance');
    $this->postJson('/api/v1/notifications/read-all')->assertNoContent();
    $this->getJson('/api/v1/notifications?unread=1')->assertJsonCount(0, 'data');
});

it('respects channel preferences and quiet hours', function () {
    Date::setTestNow('2026-03-08 23:30:00');
    $ledger = actingAsMember();
    $user = $ledger->members()->sole();

    $this->patchJson('/api/v1/notification-settings', [
        'events' => ['low_balance' => ['database', 'webpush']],
        'quiet_hours' => ['start' => '22:00', 'end' => '07:00'],
    ])->assertJsonPath('data.events.low_balance', ['database', 'webpush']);

    $notification = new LowBalanceNotification(Account::factory()->for($ledger)->create(), '1');

    expect($notification->via($user->refresh()))->toBe(['database']);
});
