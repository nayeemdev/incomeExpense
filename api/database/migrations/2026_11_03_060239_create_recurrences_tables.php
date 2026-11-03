<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('first_date');
            $table->date('repeat_until')->nullable();
            $table->unsignedInteger('max_occurrences')->nullable();
            $table->unsignedInteger('occurrences_created')->default(0);
            $table->date('latest_date')->nullable();
            $table->boolean('apply_rules')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('recurrence_repetitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurrence_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('moment', 20);
            $table->unsignedSmallInteger('skip')->default(0);
            $table->string('weekend', 20);
        });

        Schema::create('recurrence_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurrence_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 32, 12);
            $table->foreignId('foreign_currency_id')->nullable()->constrained('currencies');
            $table->decimal('foreign_amount', 32, 12)->nullable();
            $table->foreignId('source_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('destination_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('budget_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained()->nullOnDelete();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
        });

        Schema::table('transaction_groups', function (Blueprint $table) {
            $table->foreignId('recurrence_id')->nullable()->after('created_by')->constrained()->nullOnDelete();
            $table->date('recurrence_date')->nullable()->after('recurrence_id');

            $table->unique(['recurrence_id', 'recurrence_date']);
        });
    }

    public function down(): void
    {
        Schema::table('transaction_groups', function (Blueprint $table) {
            $table->dropUnique(['recurrence_id', 'recurrence_date']);
            $table->dropConstrainedForeignId('recurrence_id');
            $table->dropColumn('recurrence_date');
        });

        Schema::dropIfExists('recurrence_transactions');
        Schema::dropIfExists('recurrence_repetitions');
        Schema::dropIfExists('recurrences');
    }
};
