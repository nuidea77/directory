<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            // Зээл, хэсэгчилсэн төлбөрийн аппууд: ["LendMN", "Storepay", ...]
            $table->json('payments')->nullable()->after('amenities');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('payments');
        });
    }
};
