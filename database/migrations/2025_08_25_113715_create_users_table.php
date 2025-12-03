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
        Schema::create('users', static function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('surname', 150);
            $table->date('birth_date');
            $table->string('gender')->comment('GenderEnum');
            $table->string('email', 150);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email_verified', 3)->default('no');
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->nullableMorphs('creatable');
            $table->nullableMorphs('updatable');
            $table->nullableMorphs('deletable');
            $table->string('is_deleted', 30)->default('false');
            $table->softDeletes();

            $table->unique(['email', 'email_verified', 'is_deleted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
