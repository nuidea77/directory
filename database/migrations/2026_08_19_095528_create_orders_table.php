<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Захиалга — эрхийн бичиг, салбарын нэмэлт, онцлох байршлыг нэг
        // byl.mn нэхэмжлэхээр төлнө. Дугаар: KH-YYYY-MM-XXXX.
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('total'); // MNT, НӨАТ орсон
            $table->string('status', 20)->default('pending'); // pending | paid | void | expired
            $table->unsignedBigInteger('byl_checkout_id')->nullable()->unique();
            $table->string('invoice_url')->nullable(); // byl.mn төлбөрийн хуудасны URL
            $table->json('provider_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // plan | branch_addon | campaign
            $table->string('name');
            $table->string('meta')->nullable();
            $table->unsignedInteger('amount'); // MNT (0 байж болно — хөнгөлөлт мөрөнд сөрөг биш, тусдаа хасалтаар)
            $table->integer('discount')->default(0); // хасагдах дүн (эерэг тоо)
            $table->json('payload')->nullable(); // идэвхжүүлэлтэд хэрэгтэй өгөгдөл
            $table->timestamps();
        });

        // Онцлох байршлын кампанит ажил
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30); // category_featured | home_featured | keyword
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->string('keyword')->nullable();
            $table->unsignedTinyInteger('slot')->nullable(); // 1..3 (ангилал) / 1..6 (нүүр)
            $table->unsignedSmallInteger('days'); // 7 | 14 | 30
            $table->unsignedInteger('price');
            $table->string('status', 20)->default('pending_payment'); // pending_payment | queued | active | expired | canceled
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('calls_count')->default(0);
            $table->timestamps();

            $table->index(['type', 'status', 'category_id', 'district']);
        });

        // Салбарын өдөр тутмын статистик (аналитик график)
        Schema::create('branch_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('calls')->default(0);
            $table->unsignedInteger('directions')->default(0);
            // "Хэрхэн олсон" задаргаа
            $table->unsignedInteger('views_category')->default(0);
            $table->unsignedInteger('views_search')->default(0);
            $table->unsignedInteger('views_map')->default(0);
            $table->unsignedInteger('views_direct')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_stats');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
