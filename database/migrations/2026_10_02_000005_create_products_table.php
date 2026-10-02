<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->objectId('id')->primary();
            $table->string('product_name');
            $table->string('sku')->unique();
            $table->decimal('price', 12, 2);
            $table->string('regular_price')->nullable()->default('');
            $table->string('sale_price')->nullable()->default('');
            $table->integer('qty');
            $table->json('product_images')->nullable();
            $table->longText('description');
            $table->longText('short_description')->nullable();
            $table->string('status', 20)->nullable()->default('active')->index();
            $table->string('slug')->nullable()->default('');
            $table->string('tax_status', 20)->nullable()->default('none');
            $table->boolean('shipping_required')->nullable()->default(true);
            $table->boolean('shipping_taxable')->nullable()->default(false);
            $table->string('stock_status', 20)->nullable()->default('instock');
            $table->json('related_ids')->nullable();
            $table->datetimes(3);

            $table->index('created_at');
            $table->index('price');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
