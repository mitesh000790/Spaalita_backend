<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->objectId('id')->primary();
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->text('address')->nullable();
            $table->string('subject');
            $table->longText('message');
            $table->dateTime('created_at', 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
