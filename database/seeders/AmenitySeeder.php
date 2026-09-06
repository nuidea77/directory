<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * config/amenities.php-ээс анхны багцыг хүснэгтэд буулгана.
 * Байгаа мөрийг дарж бичихгүй — админаас хийсэн засварыг хадгална.
 */
class AmenitySeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::pluck('id', 'slug');

        foreach (config('amenities', []) as $key => $items) {
            $categoryId = $key === 'common' ? null : ($categories[$key] ?? null);

            // Ангилал нь байхгүй бол (устгагдсан) алгасна
            if ($key !== 'common' && $categoryId === null) {
                continue;
            }

            $order = 0;

            foreach ($items as $name => $icon) {
                Amenity::firstOrCreate(
                    ['category_id' => $categoryId, 'name' => $name],
                    ['icon' => $icon, 'sort_order' => $order],
                );

                $order += 10;
            }
        }
    }
}
