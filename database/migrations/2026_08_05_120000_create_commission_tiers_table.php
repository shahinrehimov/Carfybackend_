<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Amount-based commission rate tiers, e.g. 0-100 => 5%, 100-150 => 6%.
        // A tier set belongs to a company, a category, or — when both morph
        // columns are NULL — to the platform itself (the global tiers).
        //
        // Resolution stays the same chain as the flat rates (company →
        // category → global): on each level a matching tier wins over the
        // level's flat commission_percent, and a level without a match falls
        // through to the next one.
        Schema::create('commission_tiers', static function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('tierable');
            // Cents, like every other money column. min inclusive, max
            // exclusive; NULL max = no upper bound (must be the last tier).
            $table->bigInteger('min_amount');
            $table->bigInteger('max_amount')->nullable();
            $table->decimal('percent', 5, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_tiers');
    }
};
