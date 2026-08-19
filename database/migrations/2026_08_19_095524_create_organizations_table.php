<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Байгууллага — эрхийн бичиг (subscription) эзэмшигч. Доороо 1-5 бизнестэй.
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('registration_number', 20)->nullable(); // УБД (улсын бүртгэлийн дугаар)
            $table->string('plan', 20)->default('free'); // free | standard | business
            $table->unsignedTinyInteger('plan_term_years')->default(1); // 1 | 2
            $table->timestamp('plan_started_at')->nullable();
            $table->timestamp('plan_expires_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->unsignedInteger('extra_branches')->default(0); // худалдаж авсан нэмэлт салбарын эрх
            $table->index(['plan', 'plan_expires_at']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
