<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->objectId('id')->primary();
            $table->string('code')->unique();
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 12, 2);
            $table->boolean('allow_free_shipping')->default(false);
            $table->dateTime('expiry_date', 3);
            $table->decimal('min_order_amount', 12, 2)->nullable()->default(0);
            $table->decimal('max_order_amount', 12, 2)->nullable();
            $table->json('categories')->nullable()->comment('Category names the coupon is limited to');
            $table->json('exclude_categories')->nullable()->comment('Category names the coupon never applies to');
            $table->integer('usage_limit_per_coupon')->nullable();
            $table->integer('usage_limit_per_user')->nullable();
            $table->integer('usage_count')->default(0);
            $table->string('status', 20)->nullable()->default('active');
            $table->datetimes(3);

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
