<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('stop_processing')->default(false);
            $table->timestamps();
        });

        Schema::create('rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rule_group_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('trigger_on', 10)->default('both');
            $table->boolean('strict')->default(true);
            $table->boolean('stop_processing')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('rule_triggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('value')->nullable();
            $table->boolean('negated')->default(false);
            $table->unsignedSmallInteger('order')->default(0);
        });

        Schema::create('rule_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('value')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_actions');
        Schema::dropIfExists('rule_triggers');
        Schema::dropIfExists('rules');
        Schema::dropIfExists('rule_groups');
    }
};
