<?php

use App\Models\Brand;
use App\Models\BrandModel;
use App\Models\CarType;
use App\Models\EngineVolume;
use App\Models\FuelType;
use App\Models\TransmissionType;
use App\Models\User;
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
        Schema::create('user_cars', static function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(BrandModel::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(CarType::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(EngineVolume::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(FuelType::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(TransmissionType::class)->nullable()->constrained()->nullOnDelete();
            $table->smallInteger('year')->nullable();
            $table->string('engine_number', 100)->nullable();
            $table->string('vin_number', 100)->nullable();
            $table->timestamps();
            $table->nullableMorphs('creatable');
            $table->nullableMorphs('updatable');
            $table->nullableMorphs('deletable');
            $table->string('is_deleted', 30)->default('false');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_cars');
    }
};
