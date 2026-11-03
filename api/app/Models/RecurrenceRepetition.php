<?php

namespace App\Models;

use App\Enums\RepetitionType;
use App\Enums\WeekendHandling;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property RepetitionType $type
 * @property WeekendHandling $weekend
 * @property string $moment
 * @property int $skip
 */
#[Fillable(['type', 'moment', 'skip', 'weekend'])]
class RecurrenceRepetition extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RepetitionType::class,
            'weekend' => WeekendHandling::class,
        ];
    }
}
