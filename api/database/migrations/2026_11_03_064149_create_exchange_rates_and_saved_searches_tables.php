<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ledger_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUlid('from_currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->foreignUlid('to_currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('rate', 32, 12);
            $table->timestamps();

            $table->unique(['ledger_id', 'from_currency_id', 'to_currency_id', 'date'], 'exchange_rates_unique');
            $table->index(['from_currency_id', 'to_currency_id', 'date']);
        });

        Schema::create('saved_searches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ledger_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('query', 1000);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
        Schema::dropIfExists('exchange_rates');
    }
};
