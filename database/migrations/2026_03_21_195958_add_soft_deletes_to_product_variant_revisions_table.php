<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variant_revisions', function (Blueprint $table) {
            $table->string('is_deleted')->default('false')->after('data');
            $table->softDeletes()->after('is_deleted');
        });
    }

    public function down(): void
    {
        Schema::table('product_variant_revisions', function (Blueprint $table) {
            $table->dropColumn('is_deleted');
            $table->dropSoftDeletes();
        });
    }
};
