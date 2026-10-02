<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->objectId('id')->primary();
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role', 50)->nullable()->default('admin');
            $table->boolean('is_active')->default(true);
            $table->datetimes(3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
