<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLedger;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['ledger_id', 'uploaded_by', 'filename', 'path', 'mime_type', 'size', 'title'])]
class Attachment extends Model
{
    use BelongsToLedger, HasUlids;

    protected static function booted(): void
    {
        static::deleted(fn (self $attachment) => Storage::disk(config()->string('ledgerfly.attachments.disk'))->delete($attachment->path));
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
