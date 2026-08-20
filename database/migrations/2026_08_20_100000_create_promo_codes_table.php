<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Промо код: эрхийн бичиг (subscription) ба сурталчилгаа (ad) гэсэн
 * 2 ангилалтай. Хөнгөлөлт нь хувиар эсвэл тогтмол дүнгээр.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique(); // үргэлж ТОМ үсгээр хадгална
            $table->string('scope', 20); // subscription | ad
            $table->string('type', 10); // percent | fixed
            $table->unsignedInteger('value'); // хувь (1-100) эсвэл MNT
            $table->unsignedInteger('min_amount')->default(0); // энэ дүнгээс дээш захиалгад
            $table->unsignedInteger('max_uses')->nullable(); // null = хязгааргүй
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedInteger('max_uses_per_user')->default(1); // 0 = хязгааргүй
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('note')->nullable(); // дотоод тэмдэглэл (кампанит ажил гэх мэт)
            $table->timestamps();

            $table->index(['is_active', 'scope']);
        });

        Schema::create('promo_code_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount'); // бодитоор хасагдсан дүн
            $table->timestamps();

            // Нэг захиалгад нэг л удаа бүртгэгдэнэ (давхар webhook-д хамгаалалт)
            $table->unique(['promo_code_id', 'order_id']);
            $table->index(['promo_code_id', 'user_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('subtotal')->default(0)->after('organization_id');
            $table->unsignedInteger('discount_total')->default(0)->after('subtotal');
            $table->foreignId('promo_code_id')->nullable()->after('discount_total')->constrained()->nullOnDelete();
            $table->string('promo_code', 40)->nullable()->after('promo_code_id'); // хожим устсан ч баримтад үлдэнэ
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promo_code_id');
            $table->dropColumn(['subtotal', 'discount_total', 'promo_code']);
        });

        Schema::dropIfExists('promo_code_redemptions');
        Schema::dropIfExists('promo_codes');
    }
};
