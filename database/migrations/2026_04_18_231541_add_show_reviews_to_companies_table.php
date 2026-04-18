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
        Schema::table('companies', static function (Blueprint $table) {
            $table->boolean('show_reviews')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('companies', static function (Blueprint $table) {
            $table->dropColumn('show_reviews');
        });
    }
};
