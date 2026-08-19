<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Хоол, ресторан', 'slug' => 'restaurants', 'icon' => '🍽️'],
            ['name' => 'Эмнэлэг, эрүүл мэнд', 'slug' => 'health', 'icon' => '🏥'],
            ['name' => 'Гоо сайхан, спа', 'slug' => 'beauty', 'icon' => '💇'],
            ['name' => 'Авто засвар, угаалга', 'slug' => 'auto', 'icon' => '🚗'],
            ['name' => 'Барилга, засвар', 'slug' => 'construction', 'icon' => '🏗️'],
            ['name' => 'Боловсрол, сургалт', 'slug' => 'education', 'icon' => '🎓'],
            ['name' => 'Спорт, фитнес', 'slug' => 'sports', 'icon' => '🏋️'],
            ['name' => 'Худалдаа, дэлгүүр', 'slug' => 'shopping', 'icon' => '🛍️'],
            ['name' => 'Санхүү, хууль', 'slug' => 'finance-legal', 'icon' => '⚖️'],
            ['name' => 'Аялал жуулчлал', 'slug' => 'travel', 'icon' => '✈️'],
            ['name' => 'Мэдээлэл технологи', 'slug' => 'it', 'icon' => '💻'],
            ['name' => 'Үл хөдлөх хөрөнгө', 'slug' => 'real-estate', 'icon' => '🏢'],
        ];

        foreach ($categories as $i => $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                [...$category, 'sort_order' => $i],
            );
        }
    }
}
