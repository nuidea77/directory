<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Category extends Model
{
    use HasFactory;

    /**
     * Модны хамгийн их гүн: үндсэн → дэд → дэд дэд.
     * Ж: Боловсрол → Хэлний сургалт → Англи хэлний сургалт.
     */
    public const MAX_DEPTH = 3;

    protected $fillable = ['name', 'slug', 'description', 'icon', 'parent_id', 'sort_order'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Бүх түвшний дэд ангиллыг рекурсиваар ачаална.
     */
    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    /**
     * Энэ ангиллыг ҮНДСЭН ангилалаа болгосон бизнесүүд.
     */
    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }

    /**
     * Энэ ангилалд харагдах БҮХ бизнес — үндсэн ба нэмэлт ангилалаар.
     * Тоолол, хайлт, устгалын хамгаалалт бүгд үүн дээр тулгуурлана.
     */
    public function allBusinesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class);
    }

    /**
     * id => parent_id ба parent_id => [id, ...] хоёр газрын зураг.
     * Мод бараг өөрчлөгддөггүй тул нэг query-г cache-лээд бүх зам дээр
     * ашиглана (үр удам, өвөг, гүн — бүгд үүн дээр тооцогдоно).
     */
    public static function treeMaps(): array
    {
        return Cache::remember('categories:maps:v1', 600, function () {
            $rows = static::query()->orderBy('sort_order')->get(['id', 'parent_id', 'name', 'slug']);

            $parents = [];
            $children = [];
            $meta = [];

            foreach ($rows as $row) {
                $parents[$row->id] = $row->parent_id;
                $children[$row->parent_id ?? 0][] = $row->id;
                $meta[$row->id] = ['id' => $row->id, 'name' => $row->name, 'slug' => $row->slug];
            }

            return ['parents' => $parents, 'children' => $children, 'meta' => $meta];
        });
    }

    /**
     * Өөрийгөө оруулаад бүх түвшний үр удмын id.
     * Хайлт, статистик энд тулгуурлана — эцэг ангилал сонгоход
     * дэд дэд ангиллын бизнесүүд ч илэрнэ.
     */
    public function descendantIds(bool $includeSelf = true): array
    {
        $children = static::treeMaps()['children'];
        $ids = $includeSelf ? [$this->id] : [];
        $stack = [$this->id];

        while ($stack !== []) {
            $current = array_pop($stack);

            foreach ($children[$current] ?? [] as $childId) {
                $ids[] = $childId;
                $stack[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * Дээд ангиллуудын гинж (үндсэнээс эхлээд) — breadcrumb-д.
     */
    public function ancestors(): array
    {
        $maps = static::treeMaps();
        $chain = [];
        $parentId = $this->parent_id;

        while ($parentId !== null && isset($maps['meta'][$parentId])) {
            array_unshift($chain, $maps['meta'][$parentId]);
            $parentId = $maps['parents'][$parentId] ?? null;
        }

        return $chain;
    }

    /**
     * Гүн: үндсэн ангилал = 1.
     */
    public function depth(): int
    {
        return count($this->ancestors()) + 1;
    }

    public static function flushCache(): void
    {
        Cache::forget('categories:index:v3');
        Cache::forget('categories:maps:v1');
    }
}
