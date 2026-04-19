<?php

use App\Enum\SupportTicketStatusEnum;
use App\Models\Admin;
use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', static function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->string('subject', 500);
            $table->string('status', 30)->default(SupportTicketStatusEnum::OPEN->value)->comment('SupportTicketStatusEnum');
            $table->string('opened_by', 20)->comment('seller | admin');
            $table->unsignedBigInteger('opened_by_id');
            $table->foreignId('assigned_to_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->nullableMorphs('creatable');
            $table->nullableMorphs('updatable');
            $table->nullableMorphs('deletable');
            $table->string('is_deleted', 30)->default('false');
            $table->softDeletes();

            $table->unique(['uuid', 'is_deleted'], 'support_tickets_uuid_unique');
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
