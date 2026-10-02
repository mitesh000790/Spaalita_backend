<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_policies', function (Blueprint $table) {
            $table->objectId('id')->primary();
            $table->string('title');
            $table->longText('description');
            $table->text('button_url');
            $table->string('status', 20)->nullable()->default('inactive')->index();
            $table->datetimes(3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_policies');
    }
};
