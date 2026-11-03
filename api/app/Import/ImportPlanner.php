<?php

namespace App\Import;

use App\Models\Account;
use App\Models\ImportJob;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Turns an uploaded file and its settings into rows, flagging rows that look like
 * transactions already in the account.
 */
class ImportPlanner
{
    public function __construct(private CsvReader $csv, private CamtReader $camt) {}

    /**
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    public function plan(ImportJob $job, Account $account, array $config): array
    {
        $contents = (string) Storage::disk(config()->string('ledgerfly.attachments.disk'))->get($job->path);

        /** @var array{columns: array<string, int|null>, has_header?: bool, date_format?: ?string, decimal_separator?: ?string, negate?: bool, delimiter?: ?string} $csvConfig */
        $csvConfig = $config;
        $rows = $job->format === 'camt053' ? $this->camt->parse($contents) : $this->csv->parse($contents, $csvConfig);
        $existing = $this->existingKeys($account, $rows);

        return array_map(function (ImportRow $row) use ($existing, $account) {
            $duplicate = $row->isValid() && (
                isset($existing['keys'][$this->key($row->date, $row->amount, $row->description, $account)])
                || ($row->externalId !== null && isset($existing['external'][$row->externalId]))
            );

            return [...$row->toArray(), 'duplicate' => $duplicate];
        }, $rows);
    }

    /**
     * @param  list<ImportRow>  $rows
     * @return array{keys: array<string, true>, external: array<string, true>}
     */
    private function existingKeys(Account $account, array $rows): array
    {
        $dates = array_filter(array_map(fn (ImportRow $row) => $row->date, $rows));

        if ($dates === []) {
            return ['keys' => [], 'external' => []];
        }

        $transactions = Transaction::query()
            ->where(fn ($query) => $query->where('source_account_id', $account->id)->orWhere('destination_account_id', $account->id))
            ->whereBetween('date', [min($dates), max($dates)])
            ->get(['date', 'amount', 'description', 'source_account_id', 'external_id']);

        $keys = [];
        $external = [];

        foreach ($transactions as $transaction) {
            $signed = $transaction->source_account_id === $account->id ? Money::negate($transaction->amount) : $transaction->amount;
            $keys[$this->key($transaction->date->toDateString(), $signed, $transaction->description, $account)] = true;

            $externalId = $transaction->getAttribute('external_id');

            if (is_string($externalId)) {
                $external[$externalId] = true;
            }
        }

        return ['keys' => $keys, 'external' => $external];
    }

    private function key(?string $date, ?string $amount, string $description, Account $account): string
    {
        $decimals = $account->currency->decimal_places ?? 2;

        return implode('|', [$date, is_numeric($amount) ? Money::round($amount, $decimals) : '', Str::lower(trim($description))]);
    }
}
