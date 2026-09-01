<?php

namespace Database\Seeders;

use App\Services\SearchIndexer;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            CategorySeeder::class,
            SearchAliasSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }

        // Ангилал, синоним бэлэн болсны дараа хайлтын индексийг үүсгэнэ
        app(SearchIndexer::class)->reindexAll();
    }
}
