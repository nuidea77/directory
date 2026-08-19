<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 20)->default('byl');
            $table->string('plan', 40); // featured_7 | featured_30
            $table->unsignedBigInteger('byl_invoice_id')->nullable()->unique();
            $table->string('invoice_url')->nullable();
            $table->unsignedInteger('amount'); // MNT
            $table->string('status', 20)->default('pending'); // pending | paid | void | expired
            $table->json('provider_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
