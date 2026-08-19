<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_verifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // public identifier used by clients & callback URL
            $table->string('phone', 20);
            $table->string('purpose', 30); // register | reset_password
            $table->string('code', 10); // the SMS text the user must send to the shortcode
            $table->string('session_id')->nullable()->index(); // verify.mn sessionId
            $table->string('sms_uri')->nullable();
            $table->string('display_instruction')->nullable();
            $table->string('status', 20)->default('pending'); // pending | verified | expired | failed
            $table->string('callback_token', 64); // secret token protecting the callback URL
            $table->json('meta')->nullable(); // purpose-specific payload (e.g. pending registration data)
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['phone', 'purpose', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_verifications');
    }
};
