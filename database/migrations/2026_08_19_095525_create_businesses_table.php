<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Бизнес (брэнд) — нэр, лого, ангилал, тайлбар байгууллагын хэмжээнд нэг.
        // Хаяг, утас, цаг, зураг, сэтгэгдэл нь салбар (branches) түвшинд.
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('subcategory')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('website')->nullable();
            $table->string('facebook')->nullable();
            $table->string('instagram')->nullable();
            $table->string('price_level', 5)->nullable(); // ₮ | ₮₮ | ₮₮₮
            $table->boolean('is_verified')->default(false); // ✓ Баталгаажсан тэмдэг
            $table->timestamps();

            $table->index('category_id');
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // "Баянзүрх салбар"
            $table->string('slug')->unique();
            $table->boolean('is_main')->default(false); // ТӨВ салбар
            $table->string('city')->default('Улаанбаатар');
            $table->string('district')->nullable();
            $table->string('khoroo')->nullable();
            $table->string('address')->nullable();
            $table->string('landmark')->nullable(); // ориентир
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('phone', 20);
            $table->boolean('phone_verified')->default(false);
            $table->string('email')->nullable();
            $table->json('hours')->nullable(); // {mon: {from, to, closed}, ...}
            $table->json('amenities')->nullable(); // ["Зогсоол", "Картаар", ...]
            $table->string('status', 20)->default('pending'); // draft | pending | active | hidden | rejected
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('calls_count')->default(0);
            $table->unsignedBigInteger('directions_count')->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->timestamps();

            $table->index(['district', 'status']);
            $table->index('business_id');
        });

        Schema::create('branch_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->boolean('is_cover')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_images');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('businesses');
    }
};
