<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', static function (Blueprint $table) {
            $table->foreignIdFor(Category::class, 'main_category_id')->nullable()->after('is_set')->constrained()->cascadeOnDelete();
        });

        Artisan::call('db:seed', [
            '--class' => 'ProductsRefreshMainCategorySeeder'
        ]);

        Schema::table('products', static function (Blueprint $table) {
            $table->foreignId('main_category_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', static function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor(Category::class, 'main_category_id');
        });
    }
};
