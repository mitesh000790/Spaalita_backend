<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galleries', function (Blueprint $table) {
            $table->objectId('id')->primary();
            $table->text('url')->comment('Public path of the uploaded image');
            $table->datetimes(3);

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('galleries');
    }
};
