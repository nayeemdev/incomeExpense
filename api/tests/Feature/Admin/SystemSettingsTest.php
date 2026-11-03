<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

it('is only for admins', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => false]), ['*']);

    $this->getJson('/api/v1/admin/settings')->assertForbidden();
    $this->patchJson('/api/v1/admin/settings', ['registration_enabled' => true])->assertForbidden();
});

it('changes how the instance behaves without touching .env', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]), ['*']);

    $this->getJson('/api/v1/auth/config')->assertJsonPath('data.registration_open', false);
    $this->patchJson('/api/v1/admin/settings', ['registration_enabled' => true, 'bill_reminder_days' => 5, 'ntfy_server' => 'https://ntfy.example.com'])
        ->assertOk()
        ->assertJsonPath('data.registration_enabled', true)
        ->assertJsonPath('data.bill_reminder_days', 5);

    $this->getJson('/api/v1/auth/config')->assertJsonPath('data.registration_open', true);
    $this->getJson('/api/v1/notification-settings')->assertJsonPath('data.ntfy_default_server', 'https://ntfy.example.com');
    expect(config('ledgerfly.notifications.bill_reminder_days'))->toBe(5);
});

it('stores the mail password encrypted and never sends it back', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]), ['*']);

    $this->patchJson('/api/v1/admin/settings', ['mail_mailer' => 'smtp', 'mail_host' => 'smtp.example.com', 'mail_password' => 'hunter2'])
        ->assertJsonPath('data.mail_password', true)
        ->assertJsonPath('data.mail_host', 'smtp.example.com');

    $stored = (string) DB::table('settings')->where('key', 'mail_password')->value('value');
    expect($stored)->not->toContain('hunter2')
        ->and(config('mail.mailers.smtp.password'))->toBe('hunter2');
});

it('rejects values outside the allowed range', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]), ['*']);

    $this->patchJson('/api/v1/admin/settings', [
        'budget_warning_percentage' => 150,
        'mail_mailer' => 'sendgrid',
        'exchange_rates_url' => 'https://rates.example.com/latest',
    ])->assertJsonValidationErrors(['budget_warning_percentage', 'mail_mailer', 'exchange_rates_url']);
});

it('sends a test email to the admin', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'email' => 'admin@example.com']), ['*']);

    $this->postJson('/api/v1/admin/settings/test-mail')->assertOk()->assertJsonPath('data.sent_to', 'admin@example.com');
});
