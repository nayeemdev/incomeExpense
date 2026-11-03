<?php

use App\Enums\LedgerRole;
use App\Models\Account;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('uploads, downloads and deletes an attachment', function () {
    Storage::fake(config('ledgerfly.attachments.disk'));
    $ledger = actingAsMember();
    $account = Account::factory()->for($ledger)->create();

    $id = $this->post("/api/v1/ledgers/{$ledger->id}/attachments", [
        'file' => UploadedFile::fake()->create('statement.pdf', 120, 'application/pdf'),
        'attachable_type' => 'account',
        'attachable_id' => $account->id,
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.filename', 'statement.pdf')->json('data.id');

    $this->getJson("/api/v1/ledgers/{$ledger->id}/attachments?attachable_type=account&attachable_id={$account->id}")
        ->assertJsonCount(1, 'data');
    $this->get("/api/v1/ledgers/{$ledger->id}/attachments/{$id}/download")->assertDownload('statement.pdf');
    $this->deleteJson("/api/v1/ledgers/{$ledger->id}/attachments/{$id}")->assertNoContent();

    expect(Storage::disk(config('ledgerfly.attachments.disk'))->allFiles())->toBeEmpty();
});

it('rejects attaching to records of another ledger', function () {
    Storage::fake(config('ledgerfly.attachments.disk'));
    $ledger = actingAsMember();

    $this->post("/api/v1/ledgers/{$ledger->id}/attachments", [
        'file' => UploadedFile::fake()->image('receipt.jpg'),
        'attachable_type' => 'account',
        'attachable_id' => Account::factory()->create()->id,
    ], ['Accept' => 'application/json'])->assertNotFound();
});

it('does not let viewers upload', function () {
    $ledger = actingAsMember(LedgerRole::Viewer);

    $this->post("/api/v1/ledgers/{$ledger->id}/attachments", [], ['Accept' => 'application/json'])->assertForbidden();
});
