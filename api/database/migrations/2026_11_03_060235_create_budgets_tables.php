<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->string('auto_budget_type', 20)->nullable();
            $table->foreignId('auto_budget_currency_id')->nullable()->constrained('currencies');
            $table->decimal('auto_budget_amount', 32, 12)->nullable();
            $table->string('auto_budget_period', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['ledger_id', 'name']);
        });

        Schema::create('budget_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained('currencies');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('amount', 32, 12);
            $table->boolean('generated')->default(false);
            $table->timestamps();

            $table->unique(['budget_id', 'currency_id', 'start_date', 'end_date'], 'budget_limits_period_unique');
        });

        Schema::create('available_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained('currencies');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('amount', 32, 12);
            $table->timestamps();

            $table->unique(['ledger_id', 'currency_id', 'start_date', 'end_date'], 'available_budgets_period_unique');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('budget_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('budget_id');
        });

        Schema::dropIfExists('available_budgets');
        Schema::dropIfExists('budget_limits');
        Schema::dropIfExists('budgets');
    }
};
