<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Instance settings the admin changes from the web, stored in the database and applied over
 * config. The .env file only needs what the server must know before it can read the database.
 */
final class SystemSettings
{
    private const CACHE_KEY = 'system_settings';

    /**
     * Each setting's config path, validation rules and whether it is a secret, which is stored
     * encrypted and never sent back.
     *
     * @return array<string, array{config: string, rules: list<mixed>, secret?: bool}>
     */
    public static function definitions(): array
    {
        return [
            'registration_enabled' => ['config' => 'ledgerfly.registration_enabled', 'rules' => ['boolean']],
            'invitation_expiry_days' => ['config' => 'ledgerfly.invitation_expiry_days', 'rules' => ['integer', 'between:1,90']],
            'exchange_rates_enabled' => ['config' => 'ledgerfly.exchange_rates.enabled', 'rules' => ['boolean']],
            'exchange_rates_url' => ['config' => 'ledgerfly.exchange_rates.url', 'rules' => ['url:http,https', 'max:255', 'regex:/\{base\}/']],
            'bill_reminder_days' => ['config' => 'ledgerfly.notifications.bill_reminder_days', 'rules' => ['integer', 'between:0,30']],
            'budget_warning_percentage' => ['config' => 'ledgerfly.notifications.budget_warning_percentage', 'rules' => ['integer', 'between:1,100']],
            'ntfy_server' => ['config' => 'ledgerfly.notifications.ntfy_server', 'rules' => ['url:http,https', 'max:255']],
            'attachments_max_size_kb' => ['config' => 'ledgerfly.attachments.max_size_kb', 'rules' => ['integer', 'between:100,102400']],
            'mail_mailer' => ['config' => 'mail.default', 'rules' => ['in:smtp,log']],
            'mail_host' => ['config' => 'mail.mailers.smtp.host', 'rules' => ['nullable', 'string', 'max:255']],
            'mail_port' => ['config' => 'mail.mailers.smtp.port', 'rules' => ['nullable', 'integer', 'between:1,65535']],
            'mail_scheme' => ['config' => 'mail.mailers.smtp.scheme', 'rules' => ['nullable', 'in:smtp,smtps']],
            'mail_username' => ['config' => 'mail.mailers.smtp.username', 'rules' => ['nullable', 'string', 'max:255']],
            'mail_password' => ['config' => 'mail.mailers.smtp.password', 'rules' => ['nullable', 'string', 'max:255'], 'secret' => true],
            'mail_from_address' => ['config' => 'mail.from.address', 'rules' => ['nullable', 'email', 'max:255']],
            'mail_from_name' => ['config' => 'mail.from.name', 'rules' => ['nullable', 'string', 'max:255']],
        ];
    }

    /** Overrides config with the stored settings. Safe before the settings table exists. */
    public static function apply(): void
    {
        foreach (self::stored() as $key => $value) {
            config([self::definitions()[$key]['config'] => $value]);
        }

        if (app()->resolved('mail.manager')) {
            app('mail.manager')->forgetMailers();
        }
    }

    /**
     * Current values for the admin page, with secrets replaced by whether one is set.
     *
     * @return array<string, mixed>
     */
    public static function forDisplay(): array
    {
        $values = [];

        foreach (self::definitions() as $key => $definition) {
            $value = config($definition['config']);
            $values[$key] = ($definition['secret'] ?? false) ? filled($value) : $value;
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public static function save(array $changes): void
    {
        foreach ($changes as $key => $value) {
            $secret = self::definitions()[$key]['secret'] ?? false;
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode($secret && $value !== null ? Crypt::encryptString((string) $value) : $value), 'updated_at' => now(), 'created_at' => now()],
            );
        }

        Cache::forget(self::CACHE_KEY);
        self::apply();
    }

    /**
     * @return array<string, mixed>
     */
    private static function stored(): array
    {
        try {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            /** @var array<string, mixed> */
            return Cache::rememberForever(self::CACHE_KEY, function () {
                $values = [];

                foreach (DB::table('settings')->get(['key', 'value']) as $row) {
                    /** @var object{key: string, value: ?string} $row */
                    $definition = self::definitions()[$row->key] ?? null;
                    if ($definition === null) {
                        continue;
                    }
                    $value = json_decode((string) $row->value, true);
                    $values[$row->key] = ($definition['secret'] ?? false) && is_string($value) ? Crypt::decryptString($value) : $value;
                }

                return $values;
            });
        } catch (Throwable) {
            return [];
        }
    }
}
