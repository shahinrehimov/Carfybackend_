<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $variants = DB::table('product_variants')
            ->whereNotNull('company_id')
            ->whereNull('deleted_at')
            ->get();

        foreach ($variants as $variant) {
            DB::table('company_product_variant')->insert([
                'uuid' => Str::uuid()->toString(),
                'company_id' => $variant->company_id,
                'product_variant_id' => $variant->id,
                'stock' => $variant->stock ?? 0,
                'price' => $variant->price ?? 0,
                'discount_type' => $variant->discount_type,
                'discount' => $variant->discount ?? 0,
                'discount_start_at' => $variant->discount_start_at,
                'discount_end_at' => $variant->discount_end_at,
                'city_id' => $variant->city_id,
                'is_active' => $variant->is_active,
                'approval_status' => $variant->approval_status ?? 'pending',
                'approval_status_message' => $variant->approval_status_message,
                'created_at' => $variant->created_at,
                'updated_at' => $variant->updated_at,
                'creatable_id' => $variant->creatable_id,
                'creatable_type' => $variant->creatable_type,
                'is_deleted' => 'false',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('company_product_variant')->truncate();
    }
};
