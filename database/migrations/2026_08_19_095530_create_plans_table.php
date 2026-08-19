<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Эрхийн бичгүүд — админ dashboard-оос удирдана (config/billing.php нь
        // анхны утга буюу fallback; хүснэгтэд мөр байвал түүгээр override хийнэ)
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique(); // free | standard | business | ...
            $table->string('name', 60);
            $table->unsignedInteger('price'); // MNT, НӨАТ орсон
            $table->unsignedTinyInteger('term_years')->default(1);
            $table->json('limits'); // {businesses, branches (0=хязгааргүй), images_per_branch}
            $table->boolean('analytics')->default(false);
            $table->boolean('top_list')->default(false);
            $table->boolean('verified_badge')->default(false);
            $table->boolean('is_active')->default(true); // шинээр худалдахыг зогсоох (байгаа эзэмшигчид хэвээр)
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
