<?php

namespace App\Support;

use App\Models\Amenity;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;

/**
 * Ангилалд тохирсон үйлчилгээ/онцлогийн сан (amenities хүснэгт).
 * Дэд ангилал эцгүүдийнхээ багцыг өвлөнө: нийтлэг → root → ... → leaf.
 */
class Amenities
{
    /**
     * category_id (эсвэл 0 = нийтлэг) → [нэр => icon]
     *
     * @return array<int, array<string, string>>
     */
    public static function map(): array
    {
        return Cache::remember('amenities:map:v1', 600, function () {
            $map = [];

            foreach (Amenity::orderBy('sort_order')->orderBy('id')->get() as $amenity) {
                $map[$amenity->category_id ?? 0][$amenity->name] = $amenity->icon;
            }

            return $map;
        });
    }

    /**
     * @return array<int, array{name: string, icon: string}>
     */
    public static function forCategory(?Category $category): array
    {
        $map = self::map();
        $merged = $map[0] ?? [];

        if ($category !== null) {
            $ids = array_column($category->ancestors(), 'id');
            $ids[] = $category->id;

            foreach ($ids as $id) {
                $merged = [...$merged, ...($map[$id] ?? [])];
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
