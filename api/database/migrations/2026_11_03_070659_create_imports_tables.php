<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ledger_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('config');
            $table->timestamps();
        });

        Schema::create('import_jobs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ledger_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('format', 10);
            $table->string('filename');
            $table->string('path');
            $table->string('status', 20);
            $table->json('config')->nullable();
            $table->json('stats')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignUlid('import_job_id')->nullable()->after('bill_id')->constrained()->nullOnDelete();
            $table->string('external_id')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('import_job_id');
            $table->dropColumn('external_id');
        });

        Schema::dropIfExists('import_jobs');
        Schema::dropIfExists('import_profiles');
    }
};
