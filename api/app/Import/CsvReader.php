<?php

namespace App\Import;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Reads bank CSV exports: detects encoding and delimiter, and maps columns to transaction
 * fields using a configuration chosen by the user.
 */
class CsvReader
{
    public const array FIELDS = ['date', 'description', 'amount', 'debit', 'credit', 'counterparty', 'category', 'notes', 'tags', 'external_id'];

    public const array DATE_FORMATS = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd.m.Y', 'd-m-Y', 'Y/m/d', 'd/m/y', 'm/d/y', 'j M Y', 'M j, Y', 'Ymd'];

    /**
     * @return list<list<string>>
     */
    public function rows(string $contents, ?string $delimiter = null): array
    {
        $contents = $this->toUtf8($contents);
        $delimiter ??= $this->detectDelimiter($contents);
        $rows = [];

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return [];
        }

        fwrite($handle, $contents);
        rewind($handle);

        while (($row = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $row = array_map(fn ($cell) => trim((string) $cell), $row);

            if (array_filter($row, fn (string $cell) => $cell !== '') !== []) {
                $rows[] = $row;
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array{columns: array<string, int|null>, has_header?: bool, date_format?: ?string, decimal_separator?: ?string, negate?: bool, delimiter?: ?string}  $config
     * @return list<ImportRow>
     */
    public function parse(string $contents, array $config): array
    {
        $rows = $this->rows($contents, $config['delimiter'] ?? null);

        if ($config['has_header'] ?? true) {
            array_shift($rows);
        }

        $columns = $config['columns'];
        $cell = fn (array $row, string $field) => isset($columns[$field]) ? ($row[$columns[$field]] ?? '') : '';
        $result = [];

        foreach ($rows as $index => $row) {
            $errors = [];
            $date = $this->date($cell($row, 'date'), $config['date_format'] ?? null);
            $amount = $this->amountFrom($row, $cell, $config);

            if ($date === null) {
                $errors[] = 'date';
            }

            if ($amount === null) {
                $errors[] = 'amount';
            }

            $description = $cell($row, 'description');
            $tags = array_values(array_filter(array_map('trim', preg_split('/[,;|]/', $cell($row, 'tags')) ?: [])));

            $result[] = new ImportRow(
                line: $index + (($config['has_header'] ?? true) ? 2 : 1),
                date: $date,
                amount: $amount,
                description: $description !== '' ? $description : ($cell($row, 'counterparty') ?: '-'),
                counterparty: $cell($row, 'counterparty') ?: null,
                category: $cell($row, 'category') ?: null,
                notes: $cell($row, 'notes') ?: null,
                tags: $tags,
                externalId: $cell($row, 'external_id') ?: null,
                errors: $errors,
            );
        }

        return $result;
    }

    public function amount(string $value, ?string $decimalSeparator = null): ?string
    {
        $value = trim($value);
        $negative = str_starts_with($value, '-') || str_ends_with($value, '-') || (str_starts_with($value, '(') && str_ends_with($value, ')'));
        $value = preg_replace('/[^\d.,]/', '', $value) ?? '';

        if ($value === '') {
            return null;
        }

        $decimalSeparator ??= $this->guessDecimalSeparator($value);
        $thousands = $decimalSeparator === ',' ? '.' : ',';
        $value = str_replace([$thousands, $decimalSeparator], ['', '.'], $value);

        if (! is_numeric($value)) {
            return null;
        }

        return ($negative ? '-' : '').$value;
    }

    public function date(string $value, ?string $format = null): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        foreach ($format ? [$format] : self::DATE_FORMATS as $candidate) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$candidate, $value);

                if ($date !== null && $date->format($candidate) === $value) {
                    return $date->toDateString();
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $row
     * @param  callable(list<string>, string): string  $cell
     * @param  array<string, mixed>  $config
     */
    private function amountFrom(array $row, callable $cell, array $config): ?string
    {
        $separator = $config['decimal_separator'] ?? null;
        $separator = is_string($separator) ? $separator : null;
        $amount = $this->amount($cell($row, 'amount'), $separator);

        if ($amount === null) {
            $debit = $this->amount($cell($row, 'debit'), $separator);
            $credit = $this->amount($cell($row, 'credit'), $separator);

            if ($debit !== null && (float) $debit != 0.0) {
                $amount = '-'.ltrim($debit, '-');
            } elseif ($credit !== null) {
                $amount = ltrim($credit, '-');
            }
        }

        if ($amount !== null && ($config['negate'] ?? false)) {
            $amount = str_starts_with($amount, '-') ? substr($amount, 1) : '-'.$amount;
        }

        return $amount;
    }

    private function guessDecimalSeparator(string $value): string
    {
        $comma = strrpos($value, ',');
        $dot = strrpos($value, '.');

        if ($comma === false) {
            return '.';
        }

        if ($dot === false) {
            return strlen($value) - $comma - 1 === 3 && substr_count($value, ',') >= 1 && strlen($value) > 4 ? '.' : ',';
        }

        return $comma > $dot ? ',' : '.';
    }

    private function detectDelimiter(string $contents): string
    {
        $sample = implode("\n", array_slice(preg_split('/\R/', $contents) ?: [], 0, 5));
        $counts = [];

        foreach ([',', ';', "\t", '|'] as $delimiter) {
            $counts[$delimiter] = substr_count($sample, $delimiter);
        }

        arsort($counts);

        return (string) array_key_first($counts);
    }

    private function toUtf8(string $contents): string
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        return mb_check_encoding($contents, 'UTF-8') ? $contents : mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
    }
}
