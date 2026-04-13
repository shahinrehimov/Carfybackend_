<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_option_items', static function (Blueprint $table) {
            $table->json('details')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('product_option_items', static function (Blueprint $table) {
            $table->dropColumn('details');
        });
    }
};
