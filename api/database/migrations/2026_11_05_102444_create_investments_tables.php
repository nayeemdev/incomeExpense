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
            $table->timestamps();

            $table->index(['investment_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_events');
        Schema::dropIfExists('investments');
    }
};
