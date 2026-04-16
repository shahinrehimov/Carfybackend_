<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisements', static function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('key', 255);
            $table->text('description')->nullable();
            $table->string('link', 2048);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->string('platform', 10)->default('both'); // site, app, both
            $table->unsignedBigInteger('click_count')->default(0);
            $table->timestamps();
            $table->nullableMorphs('creatable');
            $table->nullableMorphs('updatable');
            $table->nullableMorphs('deletable');
            $table->string('is_deleted', 30)->default('false');
            $table->softDeletes();

            $table->unique(['key', 'is_deleted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisements');
    }
};
