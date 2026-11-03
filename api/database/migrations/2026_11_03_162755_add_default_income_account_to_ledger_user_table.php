<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_user', function (Blueprint $table) {
            $table->foreignId('default_income_account_id')->nullable()->after('default_account_id')->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ledger_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_income_account_id');
        });
    }
};
