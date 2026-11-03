<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLedger;
use App\Support\CategoryStyle;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'icon', 'color', 'notes'])]
class Category extends Model
{
    use BelongsToLedger, HasUlids;

    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            $suggested = CategoryStyle::suggest($category->name);
            $category->icon ??= $suggested['icon'];
            $category->color ??= $suggested['color'];
        });
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
