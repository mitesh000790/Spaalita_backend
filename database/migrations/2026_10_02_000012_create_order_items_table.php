<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->objectId('id')->primary();
            $table->objectId('order_id');
            // Not a foreign key: clients may send product or gift card ids.
            $table->objectId('product_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->integer('qty')->nullable();
            $table->text('image')->nullable();
            $table->unsignedInteger('position')->default(0);

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->index(['order_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
