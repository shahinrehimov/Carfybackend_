<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_product_variant', function (Blueprint $table) {
            $table->enum('size_category', ['small', 'medium', 'large'])->default('small')->after('city_id');
            $table->unsignedInteger('weight_gram')->default(0)->after('size_category');
        });
    }

    public function down(): void
    {
        Schema::table('company_product_variant', function (Blueprint $table) {
            $table->dropColumn(['size_category', 'weight_gram']);
        });
    }
};
