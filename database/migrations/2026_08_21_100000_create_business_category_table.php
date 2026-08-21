<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Нэг бизнес олон ангилалд харагдана (ж: үсчин + хумсны засал + гоо сайхан).
     * businesses.category_id нь ҮНДСЭН ангилал хэвээр (breadcrumb, зар, эрэмбэ),
     * энэ хүснэгт нь үндсэн + нэмэлт ангиллуудыг БҮГДийг агуулна.
     */
    public function up(): void
    {
        Schema::create('business_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unique(['business_id', 'category_id']);
            $table->index('category_id');
        });

        // Одоо байгаа бизнесүүдийн үндсэн ангиллыг хүснэгтэд буулгана
        DB::table('businesses')->orderBy('id')->chunk(200, function ($rows) {
            DB::table('business_category')->insertOrIgnore(
                collect($rows)->map(fn ($b) => [
                    'business_id' => $b->id,
                    'category_id' => $b->category_id,
                ])->all(),
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_category');
    }
};
