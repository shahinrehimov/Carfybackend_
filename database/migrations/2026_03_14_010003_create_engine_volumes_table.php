<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engine_volumes', static function (Blueprint $table) {
            $table->id();
            $table->decimal('value_liters', 4, 1);
            $table->unsignedInteger('value_cc');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->nullableMorphs('creatable');
            $table->nullableMorphs('updatable');
            $table->nullableMorphs('deletable');
            $table->string('is_deleted', 30)->default('false');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engine_volumes');
    }
};
