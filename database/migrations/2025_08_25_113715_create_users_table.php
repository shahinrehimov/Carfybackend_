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
            $table->uuid();
            $table->string('register_type', 30)->comment('UserRegisterTypesEnum');
            $table->string('name', 150);
            $table->string('surname', 150);
            $table->date('birth_date')->nullable();
            $table->string('gender')->nullable()->comment('GenderEnum');
            $table->string('email', 150)->nullable();
            $table->string('email_old', 150)->nullable();
            $table->string('email_unique_key', 50)->comment('if email exists and verified then md5(email) else md5(uuid)');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('phone_old', 20)->nullable();
            $table->string('phone_unique_key', 50)->comment('if phone exists and verified then md5(phone) else md5(uuid)');
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->nullableMorphs('creatable');
            $table->nullableMorphs('updatable');
            $table->nullableMorphs('deletable');
            $table->string('is_deleted', 30)->default('false');
            $table->softDeletes();

            $table->unique(['uuid', 'is_deleted']);
            $table->unique(['email_unique_key', 'is_deleted']);
            $table->unique(['phone_unique_key', 'is_deleted']);
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
