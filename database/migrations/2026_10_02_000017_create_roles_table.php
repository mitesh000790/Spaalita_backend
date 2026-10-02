<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->objectId('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->nullable()->default('active');
            $table->datetimes(3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
