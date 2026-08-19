<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('list_name')->nullable(); // "Оройн хоол", "Ажлын кафе" ...
            $table->timestamps();

            $table->unique(['user_id', 'business_id']);
        });

        // Хэрэглэгч ↔ бизнесийн зурвас (нэг бизнес+хэрэглэгч = нэг thread)
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('sender', 10); // user | business
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'user_id']);
        });

        // Хэрэглэгчийн залруулга ("Орох хаалга барилгын хажуу талд" гэх мэт)
        Schema::create('corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->string('status', 20)->default('pending'); // pending | accepted | rejected
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corrections');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('favorites');
    }
};
