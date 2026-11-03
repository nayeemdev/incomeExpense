<?php

use App\Support\CategoryStyle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('icon', 40)->nullable()->after('name');
            $table->string('color', 20)->nullable()->after('icon');
        });

        DB::table('categories')->select('id', 'name')->orderBy('id')->each(function (object $category) {
            /** @var object{id: int, name: string} $category */
            DB::table('categories')->where('id', $category->id)->update(CategoryStyle::suggest($category->name));
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['icon', 'color']);
        });
    }
};
