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
        Schema::create('payment_method_configs', static function (Blueprint $table) {
            $table->id();
            $table->string('method_name', 100);
            $table->string('display_name');
            $table->boolean('enabled')->default(false);
            $table->integer('priority')->default(100);
            $table->json('config')->nullable(); // JSON config
            $table->json('metadata')->nullable(); // Extra metadata
            $table->timestamps();
            $table->nullableMorphs('creatable');
            $table->nullableMorphs('updatable');
            $table->nullableMorphs('deletable');
            $table->string('is_deleted', 30)->default('false');
            $table->softDeletes();

            $table->unique(['method_name', 'is_deleted'], 'payment_method_configs_method_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_method_configs');
    }
};
