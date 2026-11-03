<?php

namespace App\Import;

use Illuminate\Support\Str;
use SimpleXMLElement;

/**
 * Reads ISO 20022 camt.053 bank statements (any 001.xx version).
 */
class CamtReader
{
    /**
     * @return list<ImportRow>
     */
    public function parse(string $contents): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            return [];
        }

        $rows = [];

        foreach ($xml->xpath('//*[local-name()="Ntry"]') ?: [] as $index => $entry) {
            $amount = $this->text($entry, 'Amt');
            $credit = $this->text($entry, 'CdtDbtInd') === 'CRDT';
            $date = $this->text($entry, 'BookgDt/*[local-name()="Dt"]') ?? $this->text($entry, 'ValDt/*[local-name()="Dt"]')
                ?? Str::substr((string) $this->text($entry, 'BookgDt/*[local-name()="DtTm"]'), 0, 10);
            $counterparty = $credit
                ? $this->text($entry, 'NtryDtls/TxDtls/RltdPties/Dbtr/Nm') ?? $this->text($entry, 'NtryDtls/TxDtls/RltdPties/Dbtr/Pty/Nm')
                : $this->text($entry, 'NtryDtls/TxDtls/RltdPties/Cdtr/Nm') ?? $this->text($entry, 'NtryDtls/TxDtls/RltdPties/Cdtr/Pty/Nm');
            $remittance = $this->text($entry, 'NtryDtls/TxDtls/RmtInf/Ustrd') ?? $this->text($entry, 'AddtlNtryInf');
            $errors = [];

            if (! is_numeric($amount)) {
                $errors[] = 'amount';
            }

            if (! $date) {
                $errors[] = 'date';
            }

            $rows[] = new ImportRow(
                line: $index + 1,
                date: $date ?: null,
                amount: is_numeric($amount) ? ($credit ? $amount : '-'.$amount) : null,
                description: $remittance ?? $counterparty ?? '-',
                counterparty: $counterparty,
                externalId: $this->text($entry, 'AcctSvcrRef') ?? $this->text($entry, 'NtryRef') ?? $this->text($entry, 'NtryDtls/TxDtls/Refs/EndToEndId'),
                errors: $errors,
            );
        }

        return $rows;
    }

    private function text(SimpleXMLElement $node, string $path): ?string
    {
        $query = implode('/', array_map(
            fn (string $part) => str_starts_with($part, '*') ? $part : '*[local-name()="'.$part.'"]',
            explode('/', $path),
        ));
        $match = $node->xpath($query)[0] ?? null;
        $value = $match !== null ? trim((string) $match) : '';

        return $value === '' ? null : $value;
    }
}
