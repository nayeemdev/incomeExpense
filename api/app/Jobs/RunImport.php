<?php

namespace App\Jobs;

use App\Actions\Transactions\SaveTransactionGroup;
use App\Enums\TransactionType;
use App\Import\ImportPlanner;
use App\Models\Account;
use App\Models\ImportJob;
use App\Notifications\ImportFinishedNotification;
use App\Support\Money;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Validation\ValidationException;
use Throwable;

class RunImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public ImportJob $import) {}

    public function handle(ImportPlanner $planner, SaveTransactionGroup $saveGroup): void
    {
        $job = $this->import;
        $config = $job->config ?? [];
        $account = Account::query()->with('currency', 'ledger')->findOrFail((string) ($config['account_id'] ?? ''));
        $job->update(['status' => 'running']);

        $stats = ['created' => 0, 'duplicates' => 0, 'failed' => 0, 'failures' => []];

        foreach ($planner->plan($job, $account, $config) as $row) {
            if ($row['errors'] !== []) {
                $stats['failed']++;
                $stats['failures'][] = ['line' => $row['line'], 'message' => __('Missing or unreadable: :fields', ['fields' => implode(', ', $row['errors'])])];

                continue;
            }

            if ($row['duplicate'] && ($config['skip_duplicates'] ?? true)) {
                $stats['duplicates']++;

                continue;
            }

            try {
                $this->create($saveGroup, $job, $account, $row, (bool) ($config['apply_rules'] ?? true));
                $stats['created']++;
            } catch (ValidationException $exception) {
                $stats['failed']++;
                $stats['failures'][] = ['line' => $row['line'], 'message' => collect($exception->errors())->flatten()->first() ?? $exception->getMessage()];
            }
        }

        $job->update(['status' => 'completed', 'stats' => $stats, 'completed_at' => now()]);
        $job->user?->notify(new ImportFinishedNotification($job));
    }

    public function failed(?Throwable $exception): void
    {
        $this->import->update(['status' => 'failed', 'error' => $exception?->getMessage(), 'completed_at' => now()]);
        $this->import->user?->notify(new ImportFinishedNotification($this->import));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function create(SaveTransactionGroup $saveGroup, ImportJob $job, Account $account, array $row, bool $applyRules): void
    {
        $amount = (string) $row['amount'];
        $deposit = ! Money::isNegative($amount);
        $counterparty = $row['counterparty'] ?: $row['description'];

        $group = $saveGroup->handle(
            $account->ledger,
            $job->user,
            ['transactions' => [[
                'type' => $deposit ? TransactionType::Deposit->value : TransactionType::Withdrawal->value,
                'date' => $row['date'],
                'description' => $row['description'],
                'amount' => Money::abs($amount),
                'source_id' => $deposit ? null : $account->id,
                'source_name' => $deposit ? $counterparty : null,
                'destination_id' => $deposit ? $account->id : null,
                'destination_name' => $deposit ? null : $counterparty,
                'category_name' => $row['category'],
                'tags' => $row['tags'],
                'notes' => $row['notes'],
            ]]],
            applyRules: $applyRules,
            notify: false,
        );

        $group->transactions()->update(['import_job_id' => $job->id, 'external_id' => $row['external_id']]);
    }
}
