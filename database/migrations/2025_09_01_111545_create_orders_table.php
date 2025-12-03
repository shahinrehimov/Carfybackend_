<?php

use App\Enum\OrderPaymentStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Models\AddressType;
use App\Models\City;
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
        Schema::create('orders', static function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('status', 50)->default(OrderStatusEnum::PENDING->value)->comment('OrderStatusEnum');
            $table->string('payment_status', 50)->default(OrderPaymentStatusEnum::PENDING)->comment('OrderPaymentStatusEnum');
            $table->string('payment_method', 100)->comment('Dynamic payment method name');
            $table->string('transaction_id', 500)->nullable()->comment('Payment gateway transaction ID');
            $table->unsignedInteger('amount')->comment('in cents');
            $table->unsignedInteger('amount_final')->comment('in cents');
            $table->unsignedInteger('total_discount_in_basket')->comment('in cents');
            $table->unsignedInteger('shipping')->comment('in cents');
            $table->unsignedInteger('total_quantity');
            // address
            $table->foreignIdFor(AddressType::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(City::class)->constrained()->cascadeOnDelete();
            $table->string('address_line_1', 1000);
            $table->string('address_line_2', 1000)->nullable();
            $table->string('latitude', 100);
            $table->string('longitude', 100);
            $table->string('phone', 30);
            $table->timestamps();
            $table->nullableMorphs('creatable');
            $table->nullableMorphs('updatable');
            $table->nullableMorphs('deletable');
            $table->string('is_deleted', 30)->default('false');
            $table->softDeletes();

            $table->index(['payment_method']);
            $table->index(['transaction_id']);
            $table->unique(['uuid', 'is_deleted'], 'orders_uuid_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
