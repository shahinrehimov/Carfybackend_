<?php

use App\Models\Admin;
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
        Schema::create('companies', static function (Blueprint $table) {
            $table->id();
            $table->uuid();
            // files: logo, banner
            // translation fields: name, address
            $table->string('voen', 50);
            $table->string('phone', 30);
            $table->string('email', 150);
            $table->string('address', 500);
            $table->string('latitude', 100);
            $table->string('longitude', 100);
            $table->string('website', 150)->nullable();
            $table->string('root_admin_email', 150);
            $table->dateTime('verified_at')->nullable();
            $table->foreignIdFor(Admin::class, 'verified_by')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->nullableMorphs('creatable');
            $table->nullableMorphs('updatable');
            $table->nullableMorphs('deletable');
            $table->string('is_deleted', 30)->default('false');
            $table->softDeletes();

            $table->unique(['uuid', 'is_deleted']);
            $table->unique(['voen', 'is_deleted']);
            $table->unique(['email', 'is_deleted']);
            $table->unique(['phone', 'is_deleted']);
            $table->unique(['root_admin_email', 'is_deleted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
