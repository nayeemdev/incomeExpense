<?php

return [

    'registration_enabled' => (bool) env('REGISTRATION_ENABLED', true),

    'invitation_expiry_days' => (int) env('INVITATION_EXPIRY_DAYS', 7),

    'default_preferences' => [
        'locale' => env('APP_LOCALE', 'en'),
        'timezone' => env('DEFAULT_TIMEZONE', 'UTC'),
        'theme' => 'system',
        'first_day_of_week' => 1,
        'view_range' => 'month',
    ],

];
