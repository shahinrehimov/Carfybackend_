<?php

use App\Models\Slider;
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
        Schema::create('slider_layers', static function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Slider::class)->constrained()->cascadeOnDelete();
            $table->string('type'); // background, image, text, button, video, custom
            $table->integer('sort')->default(0);
            $table->integer('z_index')->default(1);
            $table->json('position'); // {x, y, width, height, unit}
            $table->json('mobile_position')->nullable(); // mobile specific positioning
            $table->json('animation')->nullable(); // {type, duration, delay, easing}
            $table->json('styles')->nullable(); // CSS styles
            $table->json('mobile_styles')->nullable(); // mobile specific styles
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->nullableMorphs('creatable');
            $table->nullableMorphs('updatable');
            $table->nullableMorphs('deletable');
            $table->string('is_deleted', 30)->default('false');
            $table->softDeletes();

            $table->index(['slider_id', 'sort']);
            $table->index(['slider_id', 'z_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slider_layers');
    }
};
