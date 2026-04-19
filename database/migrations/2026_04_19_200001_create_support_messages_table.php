<?php

use App\Models\SupportTicket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_messages', static function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(SupportTicket::class)->constrained()->cascadeOnDelete();
            $table->string('sender_type', 100)->comment('App\Models\Admin | App\Models\CompanyAdmin');
            $table->unsignedBigInteger('sender_id');
            $table->text('body');
            $table->boolean('is_read_by_seller')->default(false);
            $table->boolean('is_read_by_admin')->default(false);
            $table->timestamps();
            $table->nullableMorphs('creatable');
            $table->nullableMorphs('updatable');
            $table->nullableMorphs('deletable');
            $table->string('is_deleted', 30)->default('false');
            $table->softDeletes();

            $table->index(['support_ticket_id', 'sender_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
    }
};
