<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sliders', static function (Blueprint $table) {
            $table->id();
            $table->string('slug', 10000);
            $table->integer('sort')->default(0)->index();
            $table->string('type')->default('hero'); // hero, banner, gallery, custom
            $table->integer('height')->default(800); // desktop height
            $table->integer('mobile_height')->default(400); // mobile height
            $table->boolean('auto_play')->default(true);
            $table->integer('auto_play_interval')->default(5000); // 5 seconds
            $table->boolean('show_navigation')->default(true);
            $table->boolean('show_pagination')->default(true);
            $table->boolean('show_progress_bar')->default(false);
            $table->string('transition_effect')->default('slide'); // slide, fade, zoom, parallax
            $table->integer('transition_duration')->default(800); // milliseconds
            $table->json('settings')->nullable(); // additional settings
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->nullableMorphs('creatable');
            $table->nullableMorphs('updatable');
            $table->nullableMorphs('deletable');
            $table->string('is_deleted', 30)->default('false');
            $table->softDeletes();

            $table->unique(['slug', 'is_deleted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sliders');
    }
};
