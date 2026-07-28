<?php

use App\Models\BrandModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Moves car fitment (brand / model / year) out of three separate places
 * — products.brand_id, product_variant_brand_models and product_variants.year —
 * into a single table where NULL means "all".
 *
 * That is what makes universal products (a rag fits every car) and year ranges
 * (fits 2012-2017) expressible at all; neither was possible before.
 *
 * See docs/tasks/fitment-refactor-plan.md
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->writeBackup();

        Schema::create('product_variant_fitments', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            // NULL brand_id = fits every brand; NULL brand_model_id = fits every
            // model of that brand. brand_id is denormalised from the model so
            // brand filters never have to join brand_models.
            $table->foreignId('brand_id')->nullable()->constrained('brands')->cascadeOnDelete();
            $table->foreignId('brand_model_id')->nullable()->constrained('brand_models')->cascadeOnDelete();
            // NULL year bound = open-ended on that side.
            $table->smallInteger('year_from')->nullable();
            $table->smallInteger('year_to')->nullable();
            $table->timestamps();

            $table->index('brand_id');
            $table->index(['brand_model_id', 'year_from', 'year_to']);
        });

        // Postgres treats NULLs as distinct inside a UNIQUE constraint, so a plain
        // unique index would happily accept two identical universal rows. NULLS NOT
        // DISTINCT is 15+ only and prod is on 14, hence the COALESCE expression.
        // Its leading column also serves lookups by product_variant_id.
        DB::statement('
            CREATE UNIQUE INDEX product_variant_fitments_unique ON product_variant_fitments (
                product_variant_id,
                COALESCE(brand_id, 0),
                COALESCE(brand_model_id, 0),
                COALESCE(year_from, 0),
                COALESCE(year_to, 0)
            )
        ');

        $unknownModelIds = $this->unknownBrandModelIds();

        $this->backfill($unknownModelIds);
        $this->retirePlaceholderModels($unknownModelIds);

        Schema::table('product_variants', static function (Blueprint $table) {
            $table->dropColumn('year');
        });

        Schema::table('products', static function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropColumn('brand_id');
        });

        Schema::dropIfExists('product_variant_brand_models');
    }

    public function down(): void
    {
        Schema::table('products', static function (Blueprint $table) {
            // Restored as nullable: fitments cannot guarantee every product still
            // resolves to exactly one brand, which the original NOT NULL demanded.
            $table->foreignId('brand_id')->nullable()->constrained('brands')->cascadeOnDelete();
        });

        Schema::table('product_variants', static function (Blueprint $table) {
            $table->unsignedInteger('year')->nullable();
        });

        Schema::create('product_variant_brand_models', static function (Blueprint $table) {
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('brand_model_id')->constrained('brand_models')->cascadeOnDelete();
            $table->primary(['product_variant_id', 'brand_model_id']);
        });

        // Best effort: universal rows carry no model, so they cannot round-trip.
        // Compare against the backup JSON written by up() if exact state matters.
        DB::statement('
            INSERT INTO product_variant_brand_models (product_variant_id, brand_model_id)
            SELECT DISTINCT product_variant_id, brand_model_id
            FROM product_variant_fitments
            WHERE brand_model_id IS NOT NULL
        ');

        DB::statement('
            UPDATE product_variants pv
            SET year = (
                SELECT MIN(f.year_from)
                FROM product_variant_fitments f
                WHERE f.product_variant_id = pv.id
            )
        ');

        DB::statement('
            UPDATE products p
            SET brand_id = (
                SELECT f.brand_id
                FROM product_variant_fitments f
                JOIN product_variants pv ON pv.id = f.product_variant_id
                WHERE pv.product_id = p.id AND f.brand_id IS NOT NULL
                LIMIT 1
            )
        ');

        Schema::dropIfExists('product_variant_fitments');
    }

    /**
     * Snapshot the pre-migration state so a rollback can be verified against it.
     */
    private function writeBackup(): void
    {
        $variants = DB::table('product_variants as pv')
            ->leftJoin('products as p', 'p.id', '=', 'pv.product_id')
            ->orderBy('pv.id')
            ->get(['pv.id', 'pv.year', 'pv.deleted_at', 'p.brand_id']);

        $modelsByVariant = DB::table('product_variant_brand_models')
            ->get()
            ->groupBy('product_variant_id')
            ->map(fn ($rows) => $rows->pluck('brand_model_id')->all());

        $payload = $variants->map(fn ($variant) => [
            'product_variant_id' => $variant->id,
            'product_brand_id' => $variant->brand_id,
            'year' => $variant->year,
            'trashed' => $variant->deleted_at !== null,
            'brand_model_ids' => $modelsByVariant->get($variant->id, []),
        ]);

        $path = 'fitment-backup-' . now()->format('Y_m_d_His') . '.json';

        Storage::disk('local')->put($path, $payload->toJson(JSON_PRETTY_PRINT));

        // Logged because the rollback is lossy by design — placeholder rows become
        // universal and cannot round-trip — so whoever runs this needs the file.
        Log::info('Fitment migration backup written', [
            'path' => Storage::disk('local')->path($path),
            'variants' => $payload->count(),
        ]);
    }

    /**
     * Migration 2026_02_16_000002 created a placeholder BrandModel titled
     * "Unknown" and attached every existing variant to it. It never carried real
     * fitment data, so those rows become universal instead.
     *
     * The placeholder is identified by its signature — the same 'Unknown' title in
     * every language — rather than a hardcoded id, which differs per environment.
     * Note this matches BrandModel only; the Brand it was filed under is a real
     * brand (BMW in production) and must not be touched.
     */
    private function unknownBrandModelIds(): array
    {
        $titles = static fn ($query) => $query
            ->select(DB::raw(1))
            ->from('model_translations as mt')
            ->whereColumn('mt.translatable_id', 'bm.id')
            ->where('mt.translatable_type', BrandModel::class)
            ->where('mt.column', 'title');

        return DB::table('brand_models as bm')
            ->whereExists($titles)
            ->whereNotExists(fn ($query) => $titles($query)->where('mt.text', '!=', 'Unknown'))
            ->pluck('bm.id')
            ->all();
    }

    private function backfill(array $unknownModelIds): void
    {
        $now = now();

        // Deliberately queried through the query builder so soft-deleted variants
        // are included: a restored product must come back with its fitment intact.
        DB::table('product_variants')->chunkById(500, function ($variants) use ($unknownModelIds, $now) {
            $models = DB::table('product_variant_brand_models as pvbm')
                ->join('brand_models as bm', 'bm.id', '=', 'pvbm.brand_model_id')
                ->whereIn('pvbm.product_variant_id', $variants->pluck('id'))
                ->get(['pvbm.product_variant_id', 'bm.id', 'bm.brand_id'])
                ->groupBy('product_variant_id');

            $rows = [];

            foreach ($variants as $variant) {
                // A variant with no models carries no fitment information at all.
                // "unknown fitment" is not "fits everything", so nothing is written
                // here rather than inventing a universal row.
                foreach ($models->get($variant->id, []) as $model) {
                    $isPlaceholder = in_array($model->id, $unknownModelIds, true);

                    $rows[] = [
                        'product_variant_id' => $variant->id,
                        'brand_id' => $isPlaceholder ? null : $model->brand_id,
                        'brand_model_id' => $isPlaceholder ? null : $model->id,
                        'year_from' => $variant->year,
                        'year_to' => $variant->year,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($rows) {
                // A variant holding both the placeholder and a real model collapses
                // into duplicate universal rows, which the unique index rejects.
                DB::table('product_variant_fitments')->insertOrIgnore($rows);
            }
        });
    }

    /**
     * Hide the placeholder model so it stops polluting the car library dropdowns.
     */
    private function retirePlaceholderModels(array $unknownModelIds): void
    {
        if (! $unknownModelIds) {
            return;
        }

        DB::table('brand_models')
            ->whereIn('id', $unknownModelIds)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => now(),
                'is_deleted' => DB::raw("'deleted_' || id"),
            ]);
    }
};
