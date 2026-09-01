<?php

namespace App\Support;

use App\Models\Category;

/**
 * Ангилалд тохирсон үйлчилгээ/онцлогийн санг config/amenities.php-ээс
 * уншина. Дэд ангилал эцгүүдийнхээ багцыг өвлөнө (common → root → ... → leaf).
 */
class Amenities
{
    /**
     * @return array<int, array{name: string, icon: string}>
     */
    public static function forCategory(?Category $category): array
    {
        $merged = config('amenities.common', []);

        if ($category !== null) {
            $slugs = array_column($category->ancestors(), 'slug');
            $slugs[] = $category->slug;

            foreach ($slugs as $slug) {
                $merged = [...$merged, ...config("amenities.{$slug}", [])];
            }
        }

        return array_map(
            fn (string $name, string $icon) => ['name' => $name, 'icon' => $icon],
            array_keys($merged),
            array_values($merged),
        );
    }

    /**
     * Ангилал тодорхойгүй үеийн нэрсийн жагсаалт (хуучин flat хэлбэр).
     *
     * @return array<int, string>
     */
    public static function defaultNames(): array
    {
        return array_column(self::forCategory(null), 'name');
    }
}
