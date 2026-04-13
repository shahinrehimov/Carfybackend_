<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $colorOptionId = DB::table('product_options')->where('name', 'color')->value('id');

        if (!$colorOptionId) {
            return;
        }

        $colorItemIds = DB::table('product_option_items')
            ->where('product_option_id', $colorOptionId)
            ->pluck('id');

        if ($colorItemIds->isEmpty()) {
            return;
        }

        DB::table('model_translations')
            ->where('translatable_type', 'App\\Models\\CarColor')
            ->whereIn('translatable_id', $colorItemIds)
            ->update(['translatable_type' => 'App\\Models\\ProductOptionItem']);
    }

    public function down(): void
    {
        $colorOptionId = DB::table('product_options')->where('name', 'color')->value('id');

        if (!$colorOptionId) {
            return;
        }

        $colorItemIds = DB::table('product_option_items')
            ->where('product_option_id', $colorOptionId)
            ->pluck('id');

        if ($colorItemIds->isEmpty()) {
            return;
        }

        DB::table('model_translations')
            ->where('translatable_type', 'App\\Models\\ProductOptionItem')
            ->whereIn('translatable_id', $colorItemIds)
            ->update(['translatable_type' => 'App\\Models\\CarColor']);
    }
};
