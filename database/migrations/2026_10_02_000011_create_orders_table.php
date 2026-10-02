<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->objectId('id')->primary();
            // Not a foreign key: the id comes from the JWT and is stored as-is.
            $table->objectId('user_id')->nullable()->index();
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('guest_phone')->nullable();
            $table->decimal('total_amount', 12, 2);
            $table->string('status', 20)->nullable()->default('pending')->index();
            $table->string('payment_intent_id')->nullable()->index();
            $table->string('payment_method')->nullable();
            $table->string('payment_status', 20)->nullable()->default('pending');
            $table->string('coupon_code')->nullable();
            $table->decimal('coupon_discount_amount', 12, 2)->nullable();
            $table->decimal('coupon_free_shipping_amount', 12, 2)->nullable();
            $table->datetimes(3);

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
