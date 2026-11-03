<?php

return [

    'registration_enabled' => (bool) env('REGISTRATION_ENABLED', true),

    'invitation_expiry_days' => (int) env('INVITATION_EXPIRY_DAYS', 7),

    'attachments' => [
        'disk' => env('ATTACHMENTS_DISK', 'local'),
        'max_size_kb' => (int) env('ATTACHMENTS_MAX_SIZE_KB', 20480),
        'mimes' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'pdf', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods'],
    ],

    'summary' => [
        'top_categories' => 5,
    ],

    'autocomplete' => [
        'limit' => 10,
    ],

    'exchange_rates' => [
        'enabled' => (bool) env('EXCHANGE_RATES_ENABLED', true),
        'url' => env('EXCHANGE_RATES_URL', 'https://open.er-api.com/v6/latest/{base}'),
    ],

    'notifications' => [
        'channels' => ['database', 'mail', 'webpush'],
        'push_channels' => ['webpush'],
        'bill_reminder_days' => (int) env('BILL_REMINDER_DAYS', 3),
        'budget_warning_percentage' => (int) env('BUDGET_WARNING_PERCENTAGE', 80),
        'events' => [
            'bill_due' => ['database', 'webpush'],
            'bill_overdue' => ['database', 'webpush', 'mail'],
            'budget_warning' => ['database', 'webpush'],
            'budget_exceeded' => ['database', 'webpush'],
            'large_transaction' => ['database', 'webpush'],
            'low_balance' => ['database', 'webpush', 'mail'],
            'recurring_created' => ['database'],
            'piggy_bank_reached' => ['database', 'webpush'],
            'rule_matched' => ['database'],
            'webhook_failing' => ['database', 'mail'],
            'weekly_summary' => ['mail'],
            'new_login' => ['mail'],
            'token_created' => ['mail'],
        ],
    ],

    'pagination' => [
        'per_page' => 50,
        'max_per_page' => 200,
    ],

    'default_preferences' => [
        'locale' => env('APP_LOCALE', 'en'),
        'timezone' => env('DEFAULT_TIMEZONE', 'UTC'),
        'theme' => 'system',
        'first_day_of_week' => 1,
        'view_range' => 'month',
    ],

];
