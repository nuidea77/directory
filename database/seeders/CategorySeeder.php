<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Монголын бизнесийн үндсэн ангиллууд. icon нь Lucide icon-ий нэр
 * (resources/js/data/categoryIcons.js дотор Vue компонент рүү холбогдоно).
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Хоол, ресторан', 'slug' => 'restaurants', 'icon' => 'utensils', 'children' => ['Монгол хоол', 'Кафе, бэйкери', 'Азийн хоол', 'Барбекю, гриль', 'Пицца, фаст фүүд', 'Цайны газар', 'Баар, паб']],
            ['name' => 'Эрүүл мэнд', 'slug' => 'health', 'icon' => 'stethoscope', 'children' => ['Шүдний эмнэлэг', 'Амбулатори', 'Эмийн сан', 'Оношилгоо', 'Нүдний эмнэлэг', 'Уламжлалт эм']],
            ['name' => 'Гоо сайхан', 'slug' => 'beauty', 'icon' => 'scissors', 'children' => ['Үсчин', 'Гоо сайхны салон', 'Спа, массаж', 'Хумсны засал', 'Косметик']],
            ['name' => 'Авто засвар', 'slug' => 'auto', 'icon' => 'car', 'children' => ['Хөдөлгүүрийн засвар', 'Тохируулга', 'Дугуй засвар', 'Авто угаалга', 'Авто сэлбэг', 'Авто цахилгаан']],
            ['name' => 'Барилга, засвар', 'slug' => 'construction', 'icon' => 'hammer', 'children' => ['Барилгын материал', 'Засал чимэглэл', 'Сантехник', 'Цахилгаанчин', 'Цонх, хаалга', 'Тавилга']],
            ['name' => 'Хууль зүй', 'slug' => 'legal', 'icon' => 'scale', 'children' => ['Өмгөөлөл', 'Нотариат', 'Аудит', 'Нягтлан бодох']],
            ['name' => 'Боловсрол', 'slug' => 'education', 'icon' => 'graduation-cap', 'children' => ['Хэлний сургалт', 'Мэргэжлийн сургалт', 'Цэцэрлэг', 'Хичээлийн дугуйлан', 'Жолооны сургууль']],
            ['name' => 'Зочид буудал', 'slug' => 'hotels', 'icon' => 'bed-double', 'children' => ['Зочид буудал', 'Гэр бааз', 'Амралтын газар', 'Түр орон сууц']],
            ['name' => 'Тээвэр, логистик', 'slug' => 'logistics', 'icon' => 'truck', 'children' => ['Ачаа тээвэр', 'Карго', 'Такси', 'Нүүлгэлт', 'Хот хоорондын тээвэр']],
            ['name' => 'IT, дизайн', 'slug' => 'it', 'icon' => 'monitor', 'children' => ['Вэб хөгжүүлэлт', 'График дизайн', 'Техник засвар', 'Программ хангамж', 'Сүлжээ, сервер']],
            ['name' => 'Худалдаа', 'slug' => 'shopping', 'icon' => 'shopping-bag', 'children' => ['Их дэлгүүр', 'Хүнсний дэлгүүр', 'Электрон бараа', 'Хувцас', 'Гэр ахуйн бараа', 'Ном, бичиг хэрэг']],
            ['name' => 'Банк, санхүү', 'slug' => 'finance', 'icon' => 'landmark', 'children' => ['Банк', 'ББСБ', 'Даатгал', 'Валют арилжаа', 'Ломбард']],
            ['name' => 'Спорт, фитнес', 'slug' => 'sport', 'icon' => 'dumbbell', 'children' => ['Фитнес заал', 'Бассейн', 'Йога', 'Тэмцээний заал', 'Спорт бараа']],
            ['name' => 'Үзвэр, амралт', 'slug' => 'entertainment', 'icon' => 'party-popper', 'children' => ['Кино театр', 'Караоке', 'Билльярд', 'Тоглоомын төв', 'Музей, галерей']],
            ['name' => 'Хурим, эвент', 'slug' => 'events', 'icon' => 'cake', 'children' => ['Хуримын танхим', 'Гэрэл зураг', 'Чимэглэл', 'Хөгжим, DJ', 'Кэйтеринг']],
            ['name' => 'Хэвлэл, сурталчилгаа', 'slug' => 'media', 'icon' => 'megaphone', 'children' => ['Хэвлэлийн газар', 'Сурталчилгааны агентлаг', 'Гадна байгууламж', 'Видео продакшн']],
            ['name' => 'Гэр ахуйн үйлчилгээ', 'slug' => 'home-services', 'icon' => 'house', 'children' => ['Цэвэрлэгээ', 'Угаалгын газар', 'Оёдол', 'Гутал засвар', 'Түлхүүр, цоож']],
            ['name' => 'Хөдөө аж ахуй', 'slug' => 'agriculture', 'icon' => 'sprout', 'children' => ['Мал эмнэлэг', 'Тэжээл', 'Үрийн дэлгүүр', 'Хүлэмж', 'ХАА техник']],
            ['name' => 'Үйлдвэрлэл', 'slug' => 'manufacturing', 'icon' => 'factory', 'children' => ['Хүнсний үйлдвэр', 'Оёдлын үйлдвэр', 'Мод боловсруулах', 'Метал боловсруулах']],
            ['name' => 'Аялал жуулчлал', 'slug' => 'travel', 'icon' => 'plane', 'children' => ['Аялалын агентлаг', 'Виз, паспорт', 'Авиа тийз', 'Жуулчны бааз']],
            ['name' => 'Тэжээвэр амьтан', 'slug' => 'pets', 'icon' => 'paw-print', 'children' => ['Мал эмнэлэг', 'Тэжээвэр амьтны дэлгүүр', 'Үс засалт']],
            ['name' => 'Төрийн үйлчилгээ', 'slug' => 'government', 'icon' => 'building-2', 'children' => ['Төрийн байгууллага', 'Дүүргийн товчоо', 'Нийтийн үйлчилгээ']],
        ];

        foreach ($categories as $i => $data) {
            $category = Category::updateOrCreate(
                ['slug' => $data['slug']],
                ['name' => $data['name'], 'icon' => $data['icon'], 'sort_order' => $i],
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
