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
        Schema::create('model_translations', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->morphs('translatable');
            $table->string('column', 100);
            $table->text('text');
            $table->timestamps();
            $table->nullableMorphs('creatable');
            $table->nullableMorphs('updatable');
            $table->nullableMorphs('deletable');
            $table->string('is_deleted', 30)->default('false');
            $table->softDeletes();

            $table->unique(['translatable_type', 'translatable_id', 'column', 'language_id', 'is_deleted'], 'model_translations_unique_fields');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_translations');
    }
};
