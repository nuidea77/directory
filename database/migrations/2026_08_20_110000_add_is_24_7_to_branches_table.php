<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 24/7 ажиллах эсэхийг hours JSON-оос бодож багана болгон хадгална —
 * SQL-ээр шүүх боломжтой болж, хуудаслалтын тоо зөв гарна.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->boolean('is_24_7')->default(false)->after('hours');
            $table->index(['status', 'is_24_7']);
        });

        // Байгаа бүртгэлүүдийг нөхөж тооцно
        \App\Models\Branch::query()->select(['id', 'hours'])->chunkById(500, function ($branches) {
            foreach ($branches as $branch) {
                $branch->newQuery()->whereKey($branch->id)->update([
                    'is_24_7' => \App\Models\Branch::computeIs247($branch->hours),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropIndex(['status', 'is_24_7']);
            $table->dropColumn('is_24_7');
        });
    }
};
