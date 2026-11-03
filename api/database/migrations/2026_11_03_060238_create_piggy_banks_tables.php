<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piggy_banks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ledger_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('object_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->foreignUlid('currency_id')->constrained('currencies');
            $table->decimal('target_amount', 32, 12)->nullable();
            $table->date('start_date')->nullable();
            $table->date('target_date')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['ledger_id', 'name']);
        });

        Schema::create('account_piggy_bank', function (Blueprint $table) {
            $table->foreignUlid('piggy_bank_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained()->cascadeOnDelete();
            $table->decimal('current_amount', 32, 12)->default(0);

            $table->primary(['piggy_bank_id', 'account_id']);
        });

        Schema::create('piggy_bank_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('piggy_bank_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 32, 12);
            $table->date('date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piggy_bank_events');
        Schema::dropIfExists('account_piggy_bank');
        Schema::dropIfExists('piggy_banks');
    }
};
