<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Category tree (adjacency list). Deleting a category deletes its sub-categories.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->objectId('id')->primary();
            $table->unsignedBigInteger('o_id')->nullable()->index()->comment('Legacy WooCommerce category id');
            $table->objectId('parent_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('image_id')->nullable();
            $table->text('image_src')->nullable();
            $table->integer('count')->default(0);
            $table->unsignedInteger('position')->default(0)->comment('Order among siblings');
            $table->datetimes(3);

            $table->foreign('parent_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->index(['parent_id', 'position']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
