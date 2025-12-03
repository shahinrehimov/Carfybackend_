<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->string('google_id')->nullable();
            $table->string('google_avatar')->nullable();

            $table->unique(['google_id', 'is_deleted']);

            $table->date('birth_date')->nullable()->change();
            $table->string('gender')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->dropUnique(['google_id', 'is_deleted']);

            $table->dropColumn('google_id');
            $table->dropColumn('google_avatar');

            $table->date('birth_date')->nullable(false)->change();
            $table->string('gender')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
