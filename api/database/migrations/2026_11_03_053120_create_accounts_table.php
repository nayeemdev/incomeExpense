<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ledger_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('name');
            $table->foreignId('currency_id')->nullable()->constrained('currencies');
            $table->string('asset_role', 20)->nullable();
            $table->string('liability_type', 20)->nullable();
            $table->string('liability_direction', 10)->nullable();
            $table->decimal('interest', 8, 4)->nullable();
            $table->string('interest_period', 10)->nullable();
            $table->unsignedTinyInteger('credit_card_payment_day')->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('bic', 11)->nullable();
            $table->string('account_number')->nullable();
            $table->decimal('virtual_balance', 32, 12)->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('include_net_worth')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ledger_id', 'type', 'active']);
        });

        Schema::table('ledger_user', function (Blueprint $table) {
            $table->foreignUlid('default_account_id')->nullable()->after('role')->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ledger_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_account_id');
        });

        Schema::dropIfExists('accounts');
    }
};
