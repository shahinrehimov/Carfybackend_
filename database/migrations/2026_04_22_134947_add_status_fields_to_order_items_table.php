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
        Schema::table('order_items', static function (Blueprint $table) {
            $table->string('status', 50)->default('pending')->after('quantity');
            $table->text('cancel_reason')->nullable()->after('status');
            $table->nullableMorphs('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', static function (Blueprint $table) {
            $table->dropColumn(['status', 'cancel_reason']);
            $table->dropMorphs('cancelled_by');
        });
    }
};
