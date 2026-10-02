<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products / gift cards a coupon is limited to (is_excluded = false) or
 * excluded from (is_excluded = true). productable_type is "Product" or "GiftCard".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_products', function (Blueprint $table) {
            $table->id();
            $table->objectId('coupon_id');
            $table->string('productable_type', 20);
            $table->objectId('productable_id')->nullable();
            $table->boolean('is_excluded')->default(false);

            $table->foreign('coupon_id')->references('id')->on('coupons')->cascadeOnDelete();
            $table->index(['coupon_id', 'is_excluded']);
            $table->index(['productable_type', 'productable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_products');
    }
};
