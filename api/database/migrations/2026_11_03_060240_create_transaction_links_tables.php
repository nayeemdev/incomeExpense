<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('outward');
            $table->string('inward');
            $table->timestamps();

            $table->unique(['ledger_id', 'name']);
        });

        Schema::create('transaction_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_id')->constrained()->cascadeOnDelete();
            $table->foreignId('link_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outward_group_id')->constrained('transaction_groups')->cascadeOnDelete();
            $table->foreignId('inward_group_id')->constrained('transaction_groups')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['link_type_id', 'outward_group_id', 'inward_group_id'], 'transaction_links_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_links');
        Schema::dropIfExists('link_types');
    }
};
