<?php

namespace App\Services;

use App\Models\Category;
use App\Support\SearchText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Хэрэглэгчийн бичсэн мөрийг задалж хайлт болгоно.
 *
 * «shudnii emneleg bayanzurh» → ангилал: Шүдний эмнэлэг, дүүрэг: Баянзүрх.
 * Кирилл/латин, үсгийн алдаа, ярианы нэр бүгдийг хамарна.
 */
class SearchQuery
{
    /** @var array<int, string> Ангилалд хамаарахгүй үлдсэн үгс */
    public array $terms = [];

    /** @var array<int, int> Таарсан ангиллын id-ууд (үр удмаа оруулсан) */
    public array $categoryIds = [];

    /** @var array<int, int> Шууд таарсан ангиллууд (харуулахад) */
    public array $matchedRootIds = [];

    /**
     * Таарсан ангилал бүр нэг бүлэг: id-ууд + нэрний үгс.
     * Бүлгүүд хооронд БА, бүлэг дотор ангилал ЭСВЭЛ текст таарна.
     *
     * @var array<int, array{ids: array<int, int>, words: array<int, string>}>
     */
    public array $categoryGroups = [];

    public ?string $city = null;

    public ?string $district = null;

    /** @var array<int, string> Залруулсан үгс: буруу → зөв */
    public array $corrections = [];

    public static function parse(string $raw): self
    {
        $self = new self;
        $tokens = SearchText::tokens($raw);

        if ($tokens === []) {
            return $self;
        }

        $places = self::placeIndex();
        $categories = SearchIndexer::categoryTerms();

        // Ангиллын нэрийг УРТААР нь эхлээд шалгана («шүдний эмнэлэг» нь
        // «эмнэлэг»-ээс илүү тодорхой). 3 → 2 → 1 үгийн цонхоор гүйнэ.
        $used = [];
        $count = count($tokens);

        for ($window = min(3, $count); $window >= 1; $window--) {
            for ($i = 0; $i + $window <= $count; $i++) {
                $slice = array_slice($tokens, $i, $window);

                if (array_intersect(range($i, $i + $window - 1), $used) !== []) {
                    continue;
                }

                $phrase = implode(' ', $slice);

                // 1) Байршил уу?
                if ($window <= 2 && $self->matchPlace($phrase, $places)) {
                    $used = array_merge($used, range($i, $i + $window - 1));

                    continue;
                }

                // 2) Ангилал (эсвэл түүний ярианы нэр) үү?
                if ($self->matchCategory($phrase, $categories, $window)) {
                    $used = array_merge($used, range($i, $i + $window - 1));
                }
            }
        }

        foreach ($tokens as $i => $token) {
            if (! in_array($i, $used, true)) {
                $self->terms[] = $token;
            }
        }

        return $self;
    }

    /**
     * Хот/дүүргийн нэрсийн индекс: fold түлхүүр → [хот, дүүрэг].
     *
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function placeIndex(): array
    {
        return Cache::remember('search:places:v1', 3600, function () {
            $index = [];

            foreach (config('locations', []) as $city => $districts) {
                $index[SearchText::fold($city)] = [$city, null];

                foreach ($districts as $district) {
                    // Дүүргийн нэр давхардвал (ж: «Булган») хот нь эхэлж
                    // бүртгэгдсэн нь хүчинтэй
                    $key = SearchText::fold($district);
                    $index[$key] ??= [$city, $district];
                }
            }

            return $index;
        });
    }

    /** @param array<string, array{0: string, 1: string|null}> $places */
    protected function matchPlace(string $phrase, array $places): bool
    {
        if (isset($places[$phrase])) {
            [$city, $district] = $places[$phrase];
            $this->city ??= $city;
            $this->district ??= $district;

            return true;
        }

        foreach ($places as $key => [$city, $district]) {
            if (SearchText::isClose($phrase, $key)) {
                $this->city ??= $city;
                $this->district ??= $district;
                $this->corrections[$phrase] = $key;

                return true;
            }
        }

        return false;
    }

    /** @param array<int, string> $categories */
    protected function matchCategory(string $phrase, array $categories, int $window): bool
    {
        $best = null;

        foreach ($categories as $id => $terms) {
            $words = explode(' ', $terms);

            // Яг таарсан үг (ангиллын нэр эсвэл синоним дотор)
            if (in_array($phrase, $words, true) || str_contains(" {$terms} ", " {$phrase} ")) {
                $best = $id;
                break;
            }

            // Олон үгт хэллэг бүхэлдээ таарах уу («shudnii emneleg»)
            if ($window > 1 && str_contains($terms, $phrase)) {
                $best = $id;
                break;
            }
        }

        // Үсгийн алдаа: ойролцоо үгтэй ангиллыг хайна
        if ($best === null && $window === 1) {
            foreach ($categories as $id => $terms) {
                foreach (explode(' ', $terms) as $word) {
                    if (SearchText::isClose($phrase, $word)) {
                        $best = $id;
                        $this->corrections[$phrase] = $word;
                        break 2;
                    }
                }
            }
        }

        if ($best === null) {
            return false;
        }

        $category = Category::find($best);

        if ($category === null) {
            return false;
        }

        $this->matchedRootIds[] = $category->id;
        $ids = $category->descendantIds();
        $this->categoryIds = array_values(array_unique(
            array_merge($this->categoryIds, $ids),
        ));

        // Ангилалдаа бүртгэгдээгүй ч дэд ангиллын нэрээрээ («Шүдний эмнэлэг»
        // гэсэн subcategory) таарах бизнесүүдийг мөн олохын тулд нэрний үгсийг
        // хадгална.
        $this->categoryGroups[] = [
            'ids' => $ids,
            'words' => SearchText::tokens($category->name),
        ];

        return true;
    }

    public function isEmpty(): bool
    {
        return $this->terms === [] && $this->categoryIds === [] && $this->city === null && $this->district === null;
    }

    /**
     * Задалсан хайлтыг query дээр буулгана.
     */
    public function apply(Builder $query): void
    {
        foreach ($this->categoryGroups as $group) {
            $query->where(function (Builder $q) use ($group) {
                $q->whereHas('business.categories', fn ($c) => $c->whereIn('categories.id', $group['ids']));

                if ($group['words'] !== []) {
                    $q->orWhere(function (Builder $t) use ($group) {
                        foreach ($group['words'] as $word) {
                            $t->where('search_text', 'like', '%'.addcslashes($word, '%_\\').'%');
                        }
                    });
                }
            });
        }

        foreach ($this->terms as $term) {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where('search_text', 'like', $like);
        }
    }
}
