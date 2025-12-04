<?php

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
        Schema::create('user_otp_codes', static function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->nullable()->constrained()->cascadeOnDelete();
            $table->string('receiver', 150)->comment('phone or email. use when user_id is null');
            $table->string('provider', 30)->comment('UserOtpProvidersEnum');
            $table->string('type', 30)->comment('UserOtpTypesEnum');
            $table->string('code', 6);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('used_at')->nullable();
            $table->string('confirmation_token', 100)->nullable();
            $table->dateTime('confirmation_token_used_at')->nullable();
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
        Schema::dropIfExists('user_otp_codes');
    }
};
