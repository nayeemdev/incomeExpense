<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledgers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->foreignId('primary_currency_id')->constrained('currencies');
            $table->char('fiscal_year_start', 5)->default('01-01');
            $table->timestamps();
        });

        Schema::create('ledger_user', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('ledger_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestamps();

            $table->unique(['ledger_id', 'user_id']);
        });

        Schema::create('ledger_invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ledger_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 20);
            $table->char('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['ledger_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_invitations');
        Schema::dropIfExists('ledger_user');
        Schema::dropIfExists('ledgers');
    }
};
