<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Дизайны нүүр хуудасны 12 ангилал
        $categories = [
            ['name' => 'Хоол, ресторан', 'slug' => 'restaurants', 'children' => ['Монгол хоол', 'Кафе, бэйкери', 'Азийн хоол', 'Барбекю, гриль', 'Пицца, фаст фүүд', 'Цайны газар', 'Баар, паб']],
            ['name' => 'Эрүүл мэнд', 'slug' => 'health', 'children' => ['Шүдний эмнэлэг', 'Амбулатори', 'Эмийн сан', 'Оношилгоо']],
            ['name' => 'Гоо сайхан', 'slug' => 'beauty', 'children' => ['Үсчин', 'Гоо сайхны салон', 'Спа, массаж']],
            ['name' => 'Авто засвар', 'slug' => 'auto', 'children' => ['Хөдөлгүүрийн засвар', 'Тохируулга', 'Дугуй засвар', 'Авто угаалга']],
            ['name' => 'Барилга', 'slug' => 'construction', 'children' => ['Барилгын материал', 'Засал чимэглэл', 'Сантехник']],
            ['name' => 'Хууль зүй', 'slug' => 'legal', 'children' => ['Өмгөөлөл', 'Нотариат']],
            ['name' => 'Боловсрол', 'slug' => 'education', 'children' => ['Хэлний сургалт', 'Мэргэжлийн сургалт', 'Цэцэрлэг']],
            ['name' => 'Зочид буудал', 'slug' => 'hotels', 'children' => ['Зочид буудал', 'Гэр бааз', 'Амралтын газар']],
            ['name' => 'Тээвэр, логистик', 'slug' => 'logistics', 'children' => ['Ачаа тээвэр', 'Карго', 'Такси']],
            ['name' => 'IT, дизайн', 'slug' => 'it', 'children' => ['Вэб хөгжүүлэлт', 'График дизайн', 'Техник засвар']],
            ['name' => 'Худалдаа', 'slug' => 'shopping', 'children' => ['Их дэлгүүр', 'Хүнсний дэлгүүр', 'Электрон бараа']],
            ['name' => 'Банк, финанс', 'slug' => 'finance', 'children' => ['Банк', 'ББСБ', 'Даатгал']],
        ];

        foreach ($categories as $i => $data) {
            $category = Category::updateOrCreate(
                ['slug' => $data['slug']],
                ['name' => $data['name'], 'sort_order' => $i],
            );

            foreach ($data['children'] as $j => $childName) {
                Category::updateOrCreate(
                    ['slug' => $data['slug'].'-'.($j + 1)],
                    ['name' => $childName, 'parent_id' => $category->id, 'sort_order' => $j],
                );
            }
        }
    }
}
