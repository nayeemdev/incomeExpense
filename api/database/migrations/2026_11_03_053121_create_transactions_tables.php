<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_groups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ledger_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ledger_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('transaction_group_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->date('date');
            $table->string('description');
            $table->foreignUlid('source_account_id')->constrained('accounts');
            $table->foreignUlid('destination_account_id')->constrained('accounts');
            $table->foreignId('currency_id')->constrained('currencies');
            $table->decimal('amount', 32, 12);
            $table->foreignId('foreign_currency_id')->nullable()->constrained('currencies');
            $table->decimal('foreign_amount', 32, 12)->nullable();
            $table->foreignUlid('category_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ledger_id', 'date']);
            $table->index(['ledger_id', 'description']);
        });

        Schema::create('postings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts');
            $table->foreignId('currency_id')->constrained('currencies');
            $table->decimal('amount', 32, 12);

            $table->index(['account_id', 'transaction_id']);
        });

        Schema::create('tag_transaction', function (Blueprint $table) {
            $table->foreignUlid('tag_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('transaction_id')->constrained()->cascadeOnDelete();

            $table->primary(['tag_id', 'transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_transaction');
        Schema::dropIfExists('postings');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('transaction_groups');
    }
};
