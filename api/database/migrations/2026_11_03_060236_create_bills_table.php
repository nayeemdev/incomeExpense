<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_id')->constrained()->cascadeOnDelete();
            $table->foreignId('object_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->foreignId('currency_id')->constrained('currencies');
            $table->decimal('amount_min', 32, 12);
            $table->decimal('amount_max', 32, 12);
            $table->date('date');
            $table->string('repeat_freq', 20);
            $table->unsignedSmallInteger('skip')->default(0);
            $table->date('end_date')->nullable();
            $table->date('extension_date')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['ledger_id', 'name']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('bill_id')->nullable()->after('budget_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bill_id');
        });

        Schema::dropIfExists('bills');
    }
};
