<?php

return [

    'registration_enabled' => (bool) env('REGISTRATION_ENABLED', true),

    'default_preferences' => [
        'locale' => env('APP_LOCALE', 'en'),
        'timezone' => env('DEFAULT_TIMEZONE', 'UTC'),
        'theme' => 'system',
        'first_day_of_week' => 1,
        'view_range' => 'month',
    ],

];
