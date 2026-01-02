<?php

use App\Models\Company;
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
        Schema::create('company_admins', static function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->boolean('is_root')->default(false);
            $table->boolean('password_must_change')->default(false);
            $table->string('username', 100);
            $table->string('email', 150);
            $table->string('password');
            $table->string('name', 255)->nullable();
            $table->string('surname', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->nullableMorphs('creatable');
            $table->nullableMorphs('updatable');
            $table->nullableMorphs('deletable');
            $table->string('is_deleted', 30)->default('false');
            $table->softDeletes();

            $table->unique(['username', 'company_id', 'is_deleted']);
            $table->unique(['email', 'company_id', 'is_deleted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_admins');
    }
};
