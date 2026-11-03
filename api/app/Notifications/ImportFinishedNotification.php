<?php

namespace App\Notifications;

use App\Models\ImportJob;

class ImportFinishedNotification extends LedgerNotification
{
    public function __construct(public ImportJob $job) {}

    public function event(): string
    {
        return 'import_finished';
    }

    public function ledgerId(): int
    {
        return $this->job->ledger_id;
    }

    public function title(): string
    {
        return $this->job->status === 'failed'
            ? __('Import of :file failed', ['file' => $this->job->filename])
            : __('Import of :file finished', ['file' => $this->job->filename]);
    }

    public function body(): string
    {
        if ($this->job->status === 'failed') {
            return (string) $this->job->getAttribute('error');
        }

        $stats = $this->job->stats ?? ['created' => 0, 'duplicates' => 0, 'failed' => 0];

        return __(':created added, :duplicates skipped as duplicates, :failed could not be read.', [
            'created' => $stats['created'],
            'duplicates' => $stats['duplicates'],
            'failed' => $stats['failed'],
        ]);
    }

    public function path(): string
    {
        return "/{$this->job->ledger_id}/import?job={$this->job->id}";
    }
}
