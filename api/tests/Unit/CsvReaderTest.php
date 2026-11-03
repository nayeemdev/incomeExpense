<?php

use App\Import\CsvReader;

it('parses amounts in different notations', function (string $input, ?string $separator, ?string $expected) {
    expect((new CsvReader)->amount($input, $separator))->toBe($expected);
})->with([
    ['1,234.56', null, '1234.56'],
    ['1.234,56', null, '1234.56'],
    ['-12,50', null, '-12.50'],
    ['(42.00)', null, '-42.00'],
    ['৳ 1,500', '.', '1500'],
    ['15,00-', null, '-15.00'],
    ['', null, null],
]);

it('parses dates in a given or detected format', function (string $input, ?string $format, ?string $expected) {
    expect((new CsvReader)->date($input, $format))->toBe($expected);
})->with([
    ['2026-03-15', null, '2026-03-15'],
    ['15.03.2026', null, '2026-03-15'],
    ['03/15/2026', 'm/d/Y', '2026-03-15'],
    ['15/03/2026', null, '2026-03-15'],
    ['nonsense', null, null],
]);

it('maps a semicolon separated bank export with debit and credit columns', function () {
    $csv = "\xEF\xBB\xBFDatum;Omschrijving;Af;Bij;Tegenpartij\n15-03-2026;Albert Heijn;23,45;;AH Amsterdam\n16-03-2026;Salaris;;2.500,00;Werkgever BV\n;broken;;;\n";

    $rows = (new CsvReader)->parse($csv, [
        'columns' => ['date' => 0, 'description' => 1, 'debit' => 2, 'credit' => 3, 'counterparty' => 4],
        'decimal_separator' => ',',
    ]);

    expect($rows)->toHaveCount(3)
        ->and($rows[0]->toArray())->toMatchArray(['line' => 2, 'date' => '2026-03-15', 'amount' => '-23.45', 'counterparty' => 'AH Amsterdam'])
        ->and($rows[1]->amount)->toBe('2500.00')
        ->and($rows[2]->errors)->toBe(['date', 'amount']);
});
