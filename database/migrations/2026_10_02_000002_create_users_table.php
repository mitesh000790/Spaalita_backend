<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->objectId('id')->primary();
            $table->string('profile_picture')->nullable()->default('');
            $table->string('user_name')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('country');
            $table->string('city');
            $table->string('address');
            $table->string('postal_code');
            $table->string('password');
            $table->string('role', 50)->nullable()->default('user');
            $table->string('status', 20)->nullable()->default('active')->index();
            $table->string('reset_password_token')->nullable()->index();
            $table->dateTime('reset_password_expire', 3)->nullable();
            $table->datetimes(3);

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
