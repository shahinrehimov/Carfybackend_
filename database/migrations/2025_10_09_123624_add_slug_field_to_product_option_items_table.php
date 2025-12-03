<?php

use App\Models\ProductOptionItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_option_items', static function (Blueprint $table) {
            $table->string('slug', 10000)->nullable();
        });

        ProductOptionItem::withTrashed()->update(['slug' => DB::raw('id')]);

        $items = ProductOptionItem::query()
            ->with([
                'translatable',
            ])
            ->get();

        foreach ($items as $item) {
            $item->slug = ProductOptionItem::instanceSlug()
                ->setText($item->getLang('title'))
                ->make()
                ->getRealSlug();

            $item->save();
        }

        Schema::table('product_option_items', static function (Blueprint $table) {
            $table->unique(['slug', 'is_deleted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_option_items', static function (Blueprint $table) {
            $table->dropUnique(['slug', 'is_deleted']);

            $table->dropColumn('slug');
        });
    }
};
