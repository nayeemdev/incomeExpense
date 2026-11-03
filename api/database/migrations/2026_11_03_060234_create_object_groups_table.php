<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('object_groups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ledger_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->unique(['ledger_id', 'title']);
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignUlid('object_group_id')->nullable()->after('ledger_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('object_group_id');
        });

        Schema::dropIfExists('object_groups');
    }
};
