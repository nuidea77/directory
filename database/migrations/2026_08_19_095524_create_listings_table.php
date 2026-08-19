<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->default('Улаанбаатар');
            $table->string('district')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->json('opening_hours')->nullable();
            $table->json('socials')->nullable();
            $table->string('status', 20)->default('active'); // active | hidden | blocked
            $table->timestamp('featured_until')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            // Denormalized rating fields, kept in sync when reviews change.
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->timestamps();

            $table->index(['category_id', 'status']);
            $table->index('featured_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
