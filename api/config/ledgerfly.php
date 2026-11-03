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
