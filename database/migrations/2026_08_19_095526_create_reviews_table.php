<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Сэтгэгдэл салбар тус бүрт бичигдэнэ — аль салбарын үйлчилгээ
        // сул байгаа нь ил харагдана.
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1..5
            $table->text('comment')->nullable();
            $table->text('reply')->nullable(); // бизнесийн хариу
            $table->timestamp('replied_at')->nullable();
            $table->string('status', 20)->default('active'); // active | flagged | hidden
            $table->unsignedInteger('helpful_count')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'user_id']);
        });

        // «Хэрэгтэй» тэмдэглэгээ — хэрэглэгч тутамд нэг удаа
        Schema::create('review_helpful', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['review_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_helpful');
        Schema::dropIfExists('reviews');
    }
};
