<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Category;
use App\Models\SearchAlias;
use App\Support\SearchText;
use Illuminate\Support\Facades\Cache;

/**
 * Салбарын хайлтын индекс: нэр, ангилал (өвөг эцгүүдтэй нь), ярианы
 * синоним, байршил, үйлчилгээг нэг мөр болгож fold хэлбэрээр хадгална.
 */
class SearchIndexer
{
    /**
     * Ангилал бүрийн хайлтын үгс (нэр + өвөг + синоним) — cache-тэй.
     *
     * @return array<int, string>
     */
    public static function categoryTerms(): array
    {
        return Cache::remember('search:category-terms:v1', 600, function () {
            $categories = Category::query()->get(['id', 'name', 'parent_id']);
            $byId = $categories->keyBy('id');

            $aliases = SearchAlias::query()->get(['category_id', 'term'])
                ->groupBy('category_id')
                ->map(fn ($rows) => $rows->pluck('term')->all());

            $terms = [];

            foreach ($categories as $c) {
                $parts = [$c->name];

                // Өвөг ангиллын нэрсийг ч оруулна: «Англи хэл» → «Хэлний
                // сургалт», «Боловсрол» гэж хайхад ч олдоно
                $parent = $c->parent_id ? $byId->get($c->parent_id) : null;
                while ($parent) {
                    $parts[] = $parent->name;
                    $parent = $parent->parent_id ? $byId->get($parent->parent_id) : null;
                }

                foreach ($aliases->get($c->id, []) as $alias) {
                    $parts[] = $alias;
                }

                $terms[$c->id] = SearchText::fold(implode(' ', $parts));
            }

            return $terms;
        });
    }

    public static function flushCache(): void
    {
        Cache::forget('search:category-terms:v1');
    }

    /**
     * Нэг салбарын индексийн мөрийг угсарна.
     */
    public function textFor(Branch $branch): string
    {
        $business = $branch->relationLoaded('business') ? $branch->business : $branch->business()->first();

        if ($business === null) {
            return '';
        }

        $categoryTerms = self::categoryTerms();
        $categoryIds = $business->categories()->pluck('categories.id')->all();

        if ($business->category_id && ! in_array($business->category_id, $categoryIds, true)) {
            $categoryIds[] = $business->category_id;
        }

        $parts = [
            $business->name,
            $business->subcategory,
            $branch->name,
            $branch->city,
            $branch->district,
            $branch->khoroo,
            $branch->address,
            $branch->landmark,
            is_array($branch->amenities) ? implode(' ', $branch->amenities) : '',
            // «storepay», «lendmn» гэж хайхад олдоно
            is_array($branch->payments) ? implode(' ', $branch->payments) : '',
            mb_substr((string) $business->description, 0, 300),
        ];

        $folded = [SearchText::fold(implode(' ', array_filter($parts)))];

        foreach ($categoryIds as $id) {
            if (isset($categoryTerms[$id])) {
                $folded[] = $categoryTerms[$id];
            }
        }

        // Давхардсан үгсийг хасна — мөр богиносно
        $words = array_unique(explode(' ', implode(' ', $folded)));

        return trim(implode(' ', array_filter($words)));
    }

    public function index(Branch $branch): void
    {
        $text = $this->textFor($branch);

        if ($branch->search_text !== $text) {
            $branch->newQuery()->whereKey($branch->getKey())->update(['search_text' => $text]);
            $branch->setAttribute('search_text', $text);
            $branch->syncOriginalAttribute('search_text');
        }
    }

    public function indexBusiness(Business $business): void
    {
        $business->branches()->with('business')->get()->each(fn (Branch $b) => $this->index($b));
    }

    /**
     * Бүгдийг дахин индексжүүлнэ (ангилал/синоним өөрчлөгдсөн үед).
     */
    public function reindexAll(): int
    {
        self::flushCache();
        $count = 0;

        Branch::query()->with(['business.categories'])->chunkById(200, function ($branches) use (&$count) {
            foreach ($branches as $branch) {
                $this->index($branch);
                $count++;
            }
        });

        return $count;
    }
}
