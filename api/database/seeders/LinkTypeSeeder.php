<?php

namespace Database\Seeders;

use App\Models\LinkType;
use Illuminate\Database\Seeder;

class LinkTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Related', 'outward' => 'relates to', 'inward' => 'relates to'],
            ['name' => 'Refund', 'outward' => 'refunds', 'inward' => 'is refunded by'],
            ['name' => 'Payment', 'outward' => 'pays for', 'inward' => 'is paid by'],
            ['name' => 'Reimbursement', 'outward' => 'reimburses', 'inward' => 'is reimbursed by'],
        ];

        foreach ($types as $type) {
            LinkType::query()->whereNull('ledger_id')->updateOrCreate(['ledger_id' => null, 'name' => $type['name']], $type);
        }
    }
}
