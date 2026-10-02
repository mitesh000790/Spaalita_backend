<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->objectId('id')->primary();
            $table->string('service_name');
            $table->longText('service_image')->comment('Image URL/path or base64 data URI');
            $table->longText('service_description');
            $table->text('button_url');
            $table->string('status', 20)->nullable()->default('active')->index();
            $table->datetimes(3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
