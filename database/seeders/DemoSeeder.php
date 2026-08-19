<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Listing;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo data for local development: sample businesses with reviews.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::updateOrCreate(
            ['phone' => '99000000'],
            [
                'name' => 'Демо эзэмшигч',
                'password' => 'password123',
                'phone_verified_at' => now(),
            ],
        );

        $reviewers = User::factory()->count(6)->create();

        $samples = [
            ['name' => 'Модерн Номадс', 'category' => 'restaurants', 'district' => 'Сүхбаатар', 'description' => 'Монгол үндэсний хоолны сүлжээ ресторан. Гэр бүл, зочид төлөөлөгчдийг хүлээн авна.', 'featured' => true],
            ['name' => 'Хаан Бууз', 'category' => 'restaurants', 'district' => 'Баянгол', 'description' => 'Түргэн үйлчилгээтэй үндэсний хоолны газар.', 'featured' => false],
            ['name' => 'Интермед Эмнэлэг', 'category' => 'health', 'district' => 'Хан-Уул', 'description' => 'Олон улсын стандартын амбулатори, стационар эмнэлэг.', 'featured' => true],
            ['name' => 'Гранд Мед', 'category' => 'health', 'district' => 'Хан-Уул', 'description' => 'Мэс засал, оношилгооны төв.', 'featured' => false],
            ['name' => 'Люкс Салон', 'category' => 'beauty', 'district' => 'Сүхбаатар', 'description' => 'Үс засалт, гоо сайхны цогц үйлчилгээ.', 'featured' => false],
            ['name' => 'Авто Оч', 'category' => 'auto', 'district' => 'Баянзүрх', 'description' => 'Хөдөлгүүр, явах эд ангийн засвар, оношилгоо.', 'featured' => false],
            ['name' => 'Смарт Скул', 'category' => 'education', 'district' => 'Чингэлтэй', 'description' => 'Программчлал, англи хэлний сургалтын төв.', 'featured' => true],
            ['name' => 'Пауэр Фитнес', 'category' => 'sports', 'district' => 'Баянгол', 'description' => 'Орчин үеийн тоног төхөөрөмжтэй фитнес клуб.', 'featured' => false],
            ['name' => 'Номин Их Дэлгүүр', 'category' => 'shopping', 'district' => 'Сонгинохайрхан', 'description' => 'Өргөн хэрэглээний барааны их дэлгүүр.', 'featured' => false],
            ['name' => 'Ай Ти Солушнс', 'category' => 'it', 'district' => 'Сүхбаатар', 'description' => 'Вэб, мобайл апп хөгжүүлэлт, IT зөвлөх үйлчилгээ.', 'featured' => false],
        ];

        foreach ($samples as $sample) {
            $category = Category::where('slug', $sample['category'])->first();

            if ($category === null) {
                continue;
            }

            $listing = Listing::updateOrCreate(
                ['slug' => Str::slug(Str::ascii($sample['name'])) ?: Str::slug($sample['category'].'-'.$sample['district'])],
                [
                    'user_id' => $owner->id,
                    'category_id' => $category->id,
                    'name' => $sample['name'],
                    'description' => $sample['description'],
                    'phone' => (string) random_int(70000000, 99999999),
                    'address' => 'Улаанбаатар, '.$sample['district'].' дүүрэг',
                    'city' => 'Улаанбаатар',
                    'district' => $sample['district'],
                    'status' => 'active',
                    'featured_until' => $sample['featured'] ? now()->addDays(30) : null,
                    'views_count' => random_int(50, 2000),
                ],
            );

            foreach ($reviewers->random(random_int(2, 5)) as $reviewer) {
                Review::updateOrCreate(
                    ['listing_id' => $listing->id, 'user_id' => $reviewer->id],
                    ['rating' => random_int(3, 5), 'comment' => 'Үйлчилгээ сайн байсан, баярлалаа!'],
                );
            }

            $listing->refreshRating();
        }
    }
}
