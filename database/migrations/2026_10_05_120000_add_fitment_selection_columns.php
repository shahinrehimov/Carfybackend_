<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a listing ask the customer which car (brand, optionally model) the part
 * is for, and carries that choice from the basket line onto the order item so
 * the seller hands the courier the right physical part.
 *
 * Price and stock stay shared across the choices; this is not a variant.
 *
 * See docs/tasks/fitment-selection-plan.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', static function (Blueprint $table) {
            $table->boolean('requires_fitment_selection')->default(false);
        });

        Schema::table('baskets', static function (Blueprint $table) {
            $table->foreignId('selected_brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('selected_brand_model_id')->nullable()->constrained('brand_models')->nullOnDelete();
        });

        Schema::table('order_items', static function (Blueprint $table) {
            $table->foreignId('selected_brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('selected_brand_model_id')->nullable()->constrained('brand_models')->nullOnDelete();
        });

        // A listing can now sit in the basket (and on an order) once per chosen
        // car, so the selection joins the uniqueness key. COALESCE because prod
        // runs Postgres 14, where NULLs are always distinct inside a unique index.
        // The old names are the 63-char truncations Postgres stored them under.
        DB::statement('ALTER TABLE baskets DROP CONSTRAINT IF EXISTS baskets_userable_type_userable_id_company_product_variant_id_is');
        DB::statement('
            CREATE UNIQUE INDEX baskets_line_unique ON baskets (
                userable_type,
                userable_id,
                company_product_variant_id,
                COALESCE(selected_brand_id, 0),
                COALESCE(selected_brand_model_id, 0),
                is_deleted
            )
        ');

        DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_order_id_company_product_variant_id_is_deleted_uniq');
        DB::statement('
            CREATE UNIQUE INDEX order_items_line_unique ON order_items (
                order_id,
                company_product_variant_id,
                COALESCE(selected_brand_id, 0),
                COALESCE(selected_brand_model_id, 0),
                is_deleted
            )
        ');
    }

    public function down(): void
    {
        // Lines that differ only by selection would collide with the restored
        // index, so they are collapsed onto their first row before it is rebuilt.
        DB::statement('
            UPDATE baskets b
            SET is_deleted = CONCAT(\'deleted_\', b.id), deleted_at = NOW()
            WHERE b.deleted_at IS NULL AND EXISTS (
                SELECT 1 FROM baskets o
                WHERE o.userable_type = b.userable_type
                  AND o.userable_id = b.userable_id
                  AND o.company_product_variant_id = b.company_product_variant_id
                  AND o.is_deleted = b.is_deleted
                  AND o.id < b.id
            )
        ');

        DB::statement('DROP INDEX IF EXISTS baskets_line_unique');
        DB::statement('DROP INDEX IF EXISTS order_items_line_unique');

        Schema::table('baskets', static function (Blueprint $table) {
            $table->dropConstrainedForeignId('selected_brand_model_id');
            $table->dropConstrainedForeignId('selected_brand_id');
            $table->unique(['userable_type', 'userable_id', 'company_product_variant_id', 'is_deleted']);
        });

        // Order items are history and are not rewritten; if an order holds the
        // same listing twice, this rollback fails here on purpose.
        Schema::table('order_items', static function (Blueprint $table) {
            $table->dropConstrainedForeignId('selected_brand_model_id');
            $table->dropConstrainedForeignId('selected_brand_id');
            $table->unique(['order_id', 'company_product_variant_id', 'is_deleted']);
        });

        Schema::table('product_variants', static function (Blueprint $table) {
            $table->dropColumn('requires_fitment_selection');
        });
    }
};
