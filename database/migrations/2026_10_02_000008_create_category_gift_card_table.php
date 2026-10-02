<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories assigned to each gift card. The auto-increment id keeps the
 * order in which the categories were assigned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_gift_card', function (Blueprint $table) {
            $table->id();
            $table->objectId('category_id');
            $table->objectId('gift_card_id');

            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('gift_card_id')->references('id')->on('gift_cards')->cascadeOnDelete();
            $table->unique(['gift_card_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_gift_card');
    }
};
