<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->decimal('tax_rate', 8, 4)->nullable()->after('payout_frequency');
            $table->decimal('contribution_amount', 32, 12)->nullable()->after('tax_rate');
            $table->string('contribution_frequency')->nullable()->after('contribution_amount');
            $table->foreignUlid('contribution_account_id')->nullable()->after('contribution_frequency')->constrained('accounts')->nullOnDelete();
            $table->date('next_contribution_date')->nullable()->after('contribution_account_id');
        });

        Schema::table('investment_events', function (Blueprint $table) {
            $table->boolean('reinvested')->default(false)->after('cost');
            $table->foreignUlid('user_id')->nullable()->after('reinvested')->constrained()->nullOnDelete();
        });

        Schema::create('investment_targets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ledger_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->decimal('percent', 5, 2);
            $table->timestamps();

            $table->unique(['ledger_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_targets');

        Schema::table('investment_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('reinvested');
        });

        Schema::table('investments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contribution_account_id');
            $table->dropColumn(['tax_rate', 'contribution_amount', 'contribution_frequency', 'next_contribution_date']);
        });
    }
};
