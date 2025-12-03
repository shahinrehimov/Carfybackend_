<?php

use App\Enum\TranslationGroupEnum;
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
        Schema::create('translations', static function (Blueprint $table) {
            $table->id();
            $table->string('group', 30)->default(TranslationGroupEnum::ALL->value)->comment('TranslationGroupEnum');
            $table->foreignId('language_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('key', 255);
            $table->text('text');
            $table->timestamps();
            $table->nullableMorphs('creatable');
            $table->nullableMorphs('updatable');
            $table->nullableMorphs('deletable');
            $table->string('is_deleted', 30)->default('false');
            $table->softDeletes();

            $table->unique(['group', 'key', 'language_id', 'is_deleted'], 'unique_fields');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
