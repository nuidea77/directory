<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Салбар бүрийн хайлтын индекс: нэр, ангилал, синоним, байршил бүгд
        // нэг ижил «түлхүүр» хэлбэрт хөрвүүлж хадгална (SearchText::fold)
        Schema::table('branches', function (Blueprint $table) {
            $table->text('search_text')->nullable()->after('amenities');
        });

        // Ярианы нэрс: «тог» → Цахилгаанчин, «шил хийх» → Цонх, хаалга
        Schema::create('search_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('term', 80);              // хэрэглэгчийн бичсэн хэлбэр
            $table->string('term_key', 80)->index(); // fold хийсэн түлхүүр
            $table->timestamps();

            $table->unique(['category_id', 'term_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_aliases');
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('search_text');
        });
    }
};
