<?php

namespace App\Console\Commands;

use App\Models\ProductVariant;
use App\Repositories\ProductVariantRepository;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BackfillVariantSlugsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backfill-variant-slugs {--dry-run : Only report the variants that would be updated}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate slugs for product variants left with a NULL/empty slug';

    /**
     * Execute the console command.
     */
    public function handle(ProductVariantRepository $repository): void
    {
        $query = ProductVariant::query()
            ->where(function (Builder $builder) {
                $builder->whereNull('slug')->orWhere('slug', '');
            })
            ->orderBy('product_id')
            ->orderBy('id');

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('No variants without a slug.');

            return;
        }

        if ($this->option('dry-run')) {
            $this->info("{$count} variant(s) without a slug would be updated:");

            (clone $query)->select(['id', 'product_id'])->chunkById(500, function ($variants) {
                foreach ($variants as $variant) {
                    $this->line("- variant #{$variant->id} (product #{$variant->product_id})");
                }
            });

            return;
        }

        $updated = 0;

        // chunkById would skip rows here because each iteration removes rows
        // from the filtered set (slug becomes non-null), so re-query instead.
        while (true) {
            $variants = (clone $query)->limit(500)->get();

            if ($variants->isEmpty()) {
                break;
            }

            foreach ($variants as $variant) {
                DB::transaction(function () use ($repository, $variant) {
                    $repository->rebuildSlug($variant);
                });

                $this->line("- variant #{$variant->id} (product #{$variant->product_id}) => {$variant->slug}");
                $updated++;
            }
        }

        $this->info("Done. {$updated} variant(s) updated.");
    }
}
