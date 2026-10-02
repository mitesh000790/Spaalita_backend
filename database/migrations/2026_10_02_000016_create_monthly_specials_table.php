<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_specials', function (Blueprint $table) {
            $table->objectId('id')->primary();
            $table->string('month')->index();
            $table->longText('image')->comment('Image URL/path or base64 data URI');
            $table->datetimes(3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_specials');
    }
};
