<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_options', static function (Blueprint $table) {
            $table->boolean('is_in_title')->default(true)->after('can_multiple');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_options', static function (Blueprint $table) {
            $table->dropColumn('is_in_title');
        });
    }
};
