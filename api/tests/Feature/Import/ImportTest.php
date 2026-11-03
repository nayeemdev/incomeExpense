<?php

use App\Models\Account;
use App\Models\ImportJob;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Notifications\ImportFinishedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('ledgerfly.attachments.disk'));
    Notification::fake();
});

function upload(Ledger $ledger, string $name, string $contents): array
{
    return test()->post("/api/v1/ledgers/{$ledger->id}/imports", [
        'file' => UploadedFile::fake()->createWithContent($name, $contents),
    ], ['Accept' => 'application/json'])->assertCreated()->json('data');
}

it('imports a CSV with suggested columns, skipping duplicates and applying rules', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->currency('USD')->create();
    $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
        'type' => 'withdrawal', 'date' => '2026-03-01', 'description' => 'Rent', 'amount' => '900',
        'source_id' => $checking->id, 'destination_name' => 'Landlord',
    ]]])->assertCreated();
    $group = $this->postJson("/api/v1/ledgers/{$ledger->id}/rule-groups", ['title' => 'Default'])->json('data.id');
    $this->postJson("/api/v1/ledgers/{$ledger->id}/rules", [
        'rule_group_id' => $group, 'title' => 'Coffee',
        'triggers' => [['type' => 'description_contains', 'value' => 'coffee']],
        'actions' => [['type' => 'set_category', 'value' => 'Eating out']],
    ])->assertCreated();

    $job = upload($ledger, 'statement.csv', "Date,Description,Amount,Payee\n2026-03-01,Rent,-900.00,Landlord\n2026-03-02,Coffee shop,-4.50,Cafe\n2026-03-05,Salary,2500,Employer\nbad,row,x,y\n");

    expect($job['suggested_columns'])->toBe(['date' => 0, 'description' => 1, 'amount' => 2, 'counterparty' => 3])
        ->and($job['sample'])->toHaveCount(5);

    $config = ['account_id' => $checking->id, 'columns' => $job['suggested_columns']];

    $this->postJson("/api/v1/ledgers/{$ledger->id}/imports/{$job['id']}/preview", $config)
        ->assertJsonPath('meta', ['total' => 4, 'invalid' => 1, 'duplicates' => 1])
        ->assertJsonPath('data.0.duplicate', true);

    $this->postJson("/api/v1/ledgers/{$ledger->id}/imports/{$job['id']}/run", [...$config, 'save_profile_as' => 'My bank'])
        ->assertOk();

    $imported = ImportJob::sole();
    expect($imported->status)->toBe('completed')
        ->and($imported->stats)->toMatchArray(['created' => 2, 'duplicates' => 1, 'failed' => 1])
        ->and(Transaction::query()->where('description', 'Coffee shop')->sole()->category?->name)->toBe('Eating out')
        ->and(Transaction::query()->where('description', 'Salary')->sole()->type->value)->toBe('deposit');

    Notification::assertSentTo($ledger->members()->sole(), ImportFinishedNotification::class);
    $this->getJson("/api/v1/ledgers/{$ledger->id}/imports")->assertJsonPath('profiles.0.name', 'My bank');
    $this->postJson("/api/v1/ledgers/{$ledger->id}/imports/{$job['id']}/run", $config)->assertStatus(409);
});

it('imports a camt.053 statement and skips entries already imported', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->currency('EUR')->create();
    $xml = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.053.001.02"><BkToCstmrStmt><Stmt>'
        .'<Ntry><AcctSvcrRef>A1</AcctSvcrRef><Amt Ccy="EUR">12.00</Amt><CdtDbtInd>DBIT</CdtDbtInd><BookgDt><Dt>2026-03-02</Dt></BookgDt>'
        .'<NtryDtls><TxDtls><RltdPties><Cdtr><Nm>Bakery</Nm></Cdtr></RltdPties></TxDtls></NtryDtls></Ntry>'
        .'</Stmt></BkToCstmrStmt></Document>';

    $first = upload($ledger, 'march.xml', $xml);
    $this->postJson("/api/v1/ledgers/{$ledger->id}/imports/{$first['id']}/run", ['account_id' => $checking->id])->assertOk();

    $second = upload($ledger, 'march-again.xml', $xml);
    $this->postJson("/api/v1/ledgers/{$ledger->id}/imports/{$second['id']}/preview", ['account_id' => $checking->id])
        ->assertJsonPath('meta.duplicates', 1);

    expect(Transaction::sole())->external_id->toBe('A1')->description->toBe('Bakery');
});

it('requires date and amount columns for CSV files', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();
    $job = upload($ledger, 'x.csv', "a,b\n1,2\n");

    $this->postJson("/api/v1/ledgers/{$ledger->id}/imports/{$job['id']}/preview", ['account_id' => $checking->id, 'columns' => ['description' => 0]])
        ->assertJsonValidationErrors('columns');
});

it('only imports into own accounts of the ledger', function () {
    $ledger = actingAsMember();
    $job = upload($ledger, 'x.csv', "date,amount\n2026-01-01,1\n");

    $this->postJson("/api/v1/ledgers/{$ledger->id}/imports/{$job['id']}/preview", [
        'account_id' => Account::factory()->create()->id, 'columns' => ['date' => 0, 'amount' => 1],
    ])->assertJsonValidationErrors('account_id');
});
