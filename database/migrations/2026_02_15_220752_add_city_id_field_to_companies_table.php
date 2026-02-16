<?php

use App\Models\City;
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
        Schema::table('companies', static function (Blueprint $table) {
            $table->foreignIdFor(City::class)->nullable()->constrained()->cascadeOnDelete();
        });

        $city = City::query()->active()->firstOrFail();

        Company::withTrashed()->update(['city_id' => $city->id]);

        Schema::table('companies', static function (Blueprint $table) {
            $table->unsignedBigInteger('city_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', static function (Blueprint $table) {
            $table->dropForeignIdFor(City::class);
        });
    }
};
