<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Үйлчилгээ/онцлог — ангилал бүрийн багц (өмнө нь config/amenities.php)
        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            // null = бүх ангилалд харагдах нийтлэг онцлог
            $table->foreignId('category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('icon', 40)->default('settings');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'name']);
            $table->index('sort_order');
        });

        // Зээл, хэсэгчилсэн төлбөрийн аппууд (өмнө нь config/payments.php)
        Schema::create('payment_apps', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 60);
            // Лого байхгүй үеийн брэндийн өнгө
            $table->string('color', 9)->default('#566a65');
            // storage/app/public дотор байршуулсан лого
            $table->string('logo_path')->nullable();
            // Лого нь нэрээ агуулсан бол хажууд нь текст давхардуулахгүй
            $table->boolean('wordmark')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_apps');
        Schema::dropIfExists('amenities');
    }
};
