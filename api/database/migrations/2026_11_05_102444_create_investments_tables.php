<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ledger_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('symbol')->nullable();
            $table->decimal('interest_rate', 8, 4)->nullable();
            $table->string('payout_frequency')->nullable();
            $table->decimal('tax_rate', 8, 4)->nullable();
            $table->decimal('contribution_amount', 32, 12)->nullable();
            $table->string('contribution_frequency')->nullable();
            $table->foreignUlid('contribution_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->date('next_contribution_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->date('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('investment_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('investment_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('transaction_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->date('date');
            $table->decimal('units', 32, 12)->nullable();
            $table->decimal('price', 32, 12)->nullable();
            $table->decimal('amount', 32, 12);
            $table->decimal('cost', 32, 12)->nullable();
            $table->boolean('reinvested')->default(false);
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['investment_id', 'date']);
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
        Schema::dropIfExists('investment_events');
        Schema::dropIfExists('investments');
    }
};
