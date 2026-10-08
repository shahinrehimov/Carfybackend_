<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The admin audit log screen filters route logs by date range.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_logs', static function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('route_logs', static function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
