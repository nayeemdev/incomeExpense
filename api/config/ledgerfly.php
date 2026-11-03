<?php

return [

    'registration_enabled' => (bool) env('REGISTRATION_ENABLED', false),

    'invitation_expiry_days' => (int) env('INVITATION_EXPIRY_DAYS', 7),

    'attachments' => [
        'disk' => env('ATTACHMENTS_DISK', 'local'),
        'max_size_kb' => (int) env('ATTACHMENTS_MAX_SIZE_KB', 20480),
        'mimes' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'pdf', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods'],
    ],

    /*
     * Category icons are Lucide icon names and colors are palette names, so every client can
     * draw them the same way. Suggestions give new categories a fitting style from their name:
     * the first keyword found in the lowercased name wins.
     */
    'categories' => [
        'icons' => [
            'utensils', 'coffee', 'shopping-cart', 'shopping-bag', 'shirt', 'house', 'receipt', 'zap', 'droplet', 'wifi',
            'smartphone', 'bus', 'car', 'fuel', 'train-front', 'plane', 'hotel', 'clapperboard', 'gamepad-2', 'music',
            'dumbbell', 'heart-pulse', 'pill', 'stethoscope', 'sparkles', 'scissors', 'graduation-cap', 'book-open', 'baby',
            'paw-print', 'users', 'gift', 'wrench', 'shield', 'landmark', 'percent', 'repeat', 'briefcase', 'banknote',
            'building-2', 'trending-up', 'hand-coins', 'piggy-bank', 'wallet', 'credit-card', 'tag', 'circle-ellipsis',
        ],
        'colors' => ['red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'pink', 'rose', 'slate'],
        'fallback' => ['icon' => 'tag', 'color' => 'slate'],
        // Every new ledger starts with these, so the first expense can be categorised without setup.
        'defaults' => [
            'Groceries', 'Eating out', 'Transport', 'Rent', 'Utilities', 'Internet & phone', 'Shopping', 'Clothing',
            'Health', 'Entertainment', 'Travel', 'Education', 'Gifts', 'Subscriptions', 'Salary', 'Freelance', 'Investments',
        ],
        'suggestions' => [
            'grocer' => ['shopping-cart', 'green'],
            'supermarket' => ['shopping-cart', 'green'],
            'coffee' => ['coffee', 'amber'],
            'cafe' => ['coffee', 'amber'],
            'restaurant' => ['utensils', 'orange'],
            'dining' => ['utensils', 'orange'],
            'eating' => ['utensils', 'orange'],
            'food' => ['utensils', 'orange'],
            'drink' => ['utensils', 'orange'],
            'cloth' => ['shirt', 'pink'],
            'shopping' => ['shopping-bag', 'pink'],
            'rent' => ['house', 'indigo'],
            'mortgage' => ['house', 'indigo'],
            'housing' => ['house', 'indigo'],
            'home' => ['house', 'indigo'],
            'house' => ['house', 'teal'],
            'electric' => ['zap', 'yellow'],
            'utilit' => ['zap', 'yellow'],
            'water' => ['droplet', 'sky'],
            'internet' => ['wifi', 'cyan'],
            'phone' => ['smartphone', 'cyan'],
            'subscription' => ['repeat', 'violet'],
            'bill' => ['receipt', 'slate'],
            'fee' => ['receipt', 'slate'],
            'card' => ['credit-card', 'slate'],
            'fuel' => ['fuel', 'red'],
            'petrol' => ['fuel', 'red'],
            'car' => ['car', 'red'],
            'taxi' => ['car', 'red'],
            'transport' => ['bus', 'blue'],
            'transit' => ['bus', 'blue'],
            'rickshaw' => ['bus', 'blue'],
            'train' => ['train-front', 'blue'],
            'travel' => ['plane', 'sky'],
            'holiday' => ['plane', 'sky'],
            'vacation' => ['plane', 'sky'],
            'hotel' => ['hotel', 'sky'],
            'entertain' => ['clapperboard', 'purple'],
            'movie' => ['clapperboard', 'purple'],
            'cinema' => ['clapperboard', 'purple'],
            'fun' => ['gamepad-2', 'purple'],
            'game' => ['gamepad-2', 'purple'],
            'music' => ['music', 'purple'],
            'sport' => ['dumbbell', 'lime'],
            'gym' => ['dumbbell', 'lime'],
            'fitness' => ['dumbbell', 'lime'],
            'health' => ['heart-pulse', 'rose'],
            'medic' => ['pill', 'rose'],
            'pharmac' => ['pill', 'rose'],
            'doctor' => ['stethoscope', 'rose'],
            'beauty' => ['sparkles', 'pink'],
            'hair' => ['scissors', 'pink'],
            'educat' => ['graduation-cap', 'indigo'],
            'school' => ['graduation-cap', 'indigo'],
            'book' => ['book-open', 'indigo'],
            'child' => ['baby', 'pink'],
            'kid' => ['baby', 'pink'],
            'pet' => ['paw-print', 'amber'],
            'family' => ['users', 'teal'],
            'gift' => ['gift', 'rose'],
            'donation' => ['gift', 'rose'],
            'repair' => ['wrench', 'slate'],
            'maintenance' => ['wrench', 'slate'],
            'insurance' => ['shield', 'teal'],
            'tax' => ['landmark', 'slate'],
            'interest' => ['percent', 'emerald'],
            'salary' => ['banknote', 'emerald'],
            'wage' => ['banknote', 'emerald'],
            'payroll' => ['banknote', 'emerald'],
            'freelance' => ['briefcase', 'teal'],
            'side' => ['briefcase', 'teal'],
            'business' => ['building-2', 'teal'],
            'work' => ['briefcase', 'teal'],
            'invest' => ['trending-up', 'emerald'],
            'dividend' => ['trending-up', 'emerald'],
            'refund' => ['hand-coins', 'emerald'],
            'loan' => ['hand-coins', 'orange'],
            'saving' => ['piggy-bank', 'emerald'],
            'electronic' => ['smartphone', 'cyan'],
        ],
    ],

    'summary' => [
        'top_categories' => 5,
    ],

    'autocomplete' => [
        'limit' => 10,
        'frequent_days' => 90,
    ],

    'exchange_rates' => [
        'enabled' => (bool) env('EXCHANGE_RATES_ENABLED', true),
        'url' => env('EXCHANGE_RATES_URL', 'https://open.er-api.com/v6/latest/{base}'),
    ],

    'notifications' => [
        'channels' => ['database', 'mail', 'webpush', 'ntfy'],
        'push_channels' => ['webpush', 'ntfy'],
        'ntfy_server' => env('NTFY_SERVER', 'https://ntfy.sh'),
        'bill_reminder_days' => (int) env('BILL_REMINDER_DAYS', 3),
        'budget_warning_percentage' => (int) env('BUDGET_WARNING_PERCENTAGE', 80),
        'events' => [
            'bill_due' => ['database', 'webpush', 'ntfy'],
            'bill_overdue' => ['database', 'webpush', 'ntfy', 'mail'],
            'budget_warning' => ['database', 'webpush', 'ntfy'],
            'budget_exceeded' => ['database', 'webpush', 'ntfy'],
            'large_transaction' => ['database', 'webpush', 'ntfy'],
            'low_balance' => ['database', 'webpush', 'ntfy', 'mail'],
            'recurring_created' => ['database'],
            'piggy_bank_reached' => ['database', 'webpush', 'ntfy'],
            'rule_matched' => ['database'],
            'webhook_failing' => ['database', 'mail'],
            'import_finished' => ['database'],
            'weekly_summary' => ['mail'],
            'new_login' => ['mail'],
            'token_created' => ['mail'],
        ],
    ],

    'pagination' => [
        'per_page' => 50,
        'max_per_page' => 200,
    ],

    'demo' => [
        'email' => env('DEMO_EMAIL', 'demo@ledgerfly.test'),
        'currency' => env('DEMO_CURRENCY', 'BDT'),
        // Demo amounts are written in dollar terms; this multiplies them to fit the demo currency.
        'scale' => env('DEMO_SCALE', '100'),
        'password' => env('DEMO_PASSWORD'),
    ],

    'dashboard_widgets' => ['trend', 'recent', 'accounts', 'bills', 'top_spending'],

    'default_preferences' => [
        'locale' => env('APP_LOCALE', 'en'),
        'timezone' => env('DEFAULT_TIMEZONE', 'UTC'),
        'theme' => 'system',
        'first_day_of_week' => 1,
        'view_range' => 'month',
        'dashboard_hidden' => [],
    ],

];
