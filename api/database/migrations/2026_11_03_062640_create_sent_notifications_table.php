<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sent_notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->timestamp('created_at');

            $table->unique(['user_id', 'key']);
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->decimal('low_balance_threshold', 32, 12)->nullable()->after('virtual_balance');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('low_balance_threshold');
        });

        Schema::dropIfExists('sent_notifications');
    }
};
