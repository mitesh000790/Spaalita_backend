<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Newsletter sign-ups (website footer, checkout checkbox, My Account).
 * One row per email address; unsubscribing keeps the row with status "unsubscribed".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->objectId('id')->primary();
            $table->string('email')->unique();
            $table->string('status', 20)->default('subscribed')->index();
            $table->string('source', 20)->nullable()->comment('footer, checkout, account or admin');
            $table->objectId('user_id')->nullable()->index()->comment('Customer account with this email, if any');
            $table->dateTime('subscribed_at', 3)->nullable();
            $table->dateTime('unsubscribed_at', 3)->nullable();
            $table->datetimes(3);

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
