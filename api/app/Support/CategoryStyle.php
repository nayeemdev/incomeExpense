<?php

namespace App\Support;

use Illuminate\Support\Str;

class CategoryStyle
{
    /**
     * @return array{icon: string, color: string}
     */
    public static function suggest(string $name): array
    {
        $name = Str::lower($name);

        /** @var array<string, array{0: string, 1: string}> $suggestions */
        $suggestions = config('ledgerfly.categories.suggestions');

        foreach ($suggestions as $keyword => [$icon, $color]) {
            if (str_contains($name, $keyword)) {
                return ['icon' => $icon, 'color' => $color];
            }
        }

        /** @var array{icon: string, color: string} */
        return config('ledgerfly.categories.fallback');
    }
}
