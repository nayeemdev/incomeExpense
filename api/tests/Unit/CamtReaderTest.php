<?php

use App\Import\CamtReader;

it('reads entries from a camt.053 statement', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.053.001.08">
  <BkToCstmrStmt><Stmt>
    <Ntry>
      <NtryRef>REF1</NtryRef>
      <Amt Ccy="EUR">42.10</Amt>
      <CdtDbtInd>DBIT</CdtDbtInd>
      <BookgDt><Dt>2026-03-02</Dt></BookgDt>
      <NtryDtls><TxDtls>
        <RltdPties><Cdtr><Pty><Nm>Electric Co</Nm></Pty></Cdtr></RltdPties>
        <RmtInf><Ustrd>Invoice 2026-03</Ustrd></RmtInf>
      </TxDtls></NtryDtls>
    </Ntry>
    <Ntry>
      <Amt Ccy="EUR">1500.00</Amt>
      <CdtDbtInd>CRDT</CdtDbtInd>
      <BookgDt><DtTm>2026-03-25T08:00:00</DtTm></BookgDt>
      <NtryDtls><TxDtls><RltdPties><Dbtr><Nm>Employer</Nm></Dbtr></RltdPties></TxDtls></NtryDtls>
    </Ntry>
  </Stmt></BkToCstmrStmt>
</Document>
XML;

    $rows = (new CamtReader)->parse($xml);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->toArray())->toMatchArray(['date' => '2026-03-02', 'amount' => '-42.10', 'description' => 'Invoice 2026-03', 'counterparty' => 'Electric Co', 'external_id' => 'REF1'])
        ->and($rows[1]->toArray())->toMatchArray(['date' => '2026-03-25', 'amount' => '1500.00', 'description' => 'Employer']);
});

it('returns nothing for invalid XML', function () {
    expect((new CamtReader)->parse('<not xml'))->toBe([]);
});
