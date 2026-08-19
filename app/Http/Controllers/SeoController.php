<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Category;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * SPA-ийн server талын SEO: хуудас бүрийн title/description/OG/JSON-LD,
 * sitemap.xml. Мэдэгдэхгүй slug-т жинхэнэ 404 статус буцаана.
 */
class SeoController extends Controller
{
    protected array $default = [
        'title' => 'Хаана.mn — Монголын бизнес лавлах',
        'description' => 'Монголын бизнесүүдийг нэг дороос. Ресторан, эмнэлэг, авто засвар, хууль зүйн үйлчилгээ — хаяг, цагийн хуваарь, үнэлгээ бүхий баталгаажсан лавлах.',
        'image' => null,
        'jsonld' => null,
        'canonical' => null,
    ];

    public function spa(): Response
    {
        // $default дотор canonical => null байдаг ба массивын + үйлдэлд ЗҮҮН
        // талын түлхүүр давамгайлдаг тул урьд нь canonical хэзээ ч бичигддэггүй
        // байсан (бүх query хувилбар тусдаа индекслэгдэнэ)
        return response()->view('app', ['meta' => ['canonical' => url('/')] + $this->default]);
    }

    public function business(string $slug): Response
    {
        $business = Business::where('slug', $slug)
            ->with(['category', 'branches' => fn ($q) => $q->where('status', 'active')->with('images')])
            ->first();

        if ($business === null || $business->branches->isEmpty()) {
            return response()->view('app', ['meta' => $this->default], 404);
        }

        $branch = $business->branches->first();
        $cover = $branch->images->first();
        $disk = Storage::disk('public');

        // Google-ийн local rich results-д зориулсан LocalBusiness schema
        $jsonld = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $business->name,
            'url' => url('/b/'.$business->slug),
            'telephone' => $branch->phone,
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => $branch->city ?: 'Улаанбаатар',
                'addressRegion' => $branch->district,
                'streetAddress' => $branch->address,
                'addressCountry' => 'MN',
            ],
        ];

        if ($business->description) {
            $jsonld['description'] = $business->description;
        }

        if ($branch->lat !== null) {
            $jsonld['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) $branch->lat, 'longitude' => (float) $branch->lng];
        }

        if ($branch->reviews_count > 0) {
            $jsonld['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (float) $branch->rating_avg,
                'reviewCount' => (int) $branch->reviews_count,
            ];
        }

        if ($cover) {
            $jsonld['image'] = $disk->url($cover->path);
        }

        return response()->view('app', ['meta' => [
            'title' => $business->name.' — '.($business->category?->name ?? 'Бизнес').' | Хаана.mn',
            'description' => mb_substr($business->description ?: sprintf('%s — %s, %s. Хаяг, утас, цагийн хуваарь, сэтгэгдэл — Хаана.mn лавлахаас.', $business->name, $branch->district, $branch->address), 0, 160),
            'image' => $cover ? $disk->url($cover->path) : null,
            'canonical' => url('/b/'.$business->slug),
            'jsonld' => $jsonld,
        ]]);
    }

    public function category(string $slug): Response
    {
        $category = Category::where('slug', $slug)->withCount('businesses')->first();

        if ($category === null) {
            return response()->view('app', ['meta' => $this->default], 404);
        }

        return response()->view('app', ['meta' => [
            'title' => $category->name.' — Монголын бизнес лавлах | Хаана.mn',
            'description' => mb_substr($category->description ?: sprintf('%s ангиллын %d бизнес: хаяг, утас, цагийн хуваарь, үнэлгээ. Дүүрэг, аймгаар шүүж хайгаарай.', $category->name, $category->businesses_count), 0, 160),
            'image' => null,
            'canonical' => url('/c/'.$category->slug),
            'jsonld' => [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Хаана.mn', 'item' => url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Ангилал', 'item' => url('/categories')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $category->name, 'item' => url('/c/'.$category->slug)],
                ],
            ],
        ]]);
    }

    /**
     * Sitemap — 1 цаг cache-лэнэ.
     */
    public function sitemap(): Response
    {
        $xml = Cache::remember('sitemap:xml', 3600, function () {
            $urls = [
                ['loc' => url('/'), 'priority' => '1.0'],
                ['loc' => url('/categories'), 'priority' => '0.8'],
                ['loc' => url('/search'), 'priority' => '0.7'],
                ['loc' => url('/pricing'), 'priority' => '0.5'],
                ['loc' => url('/terms'), 'priority' => '0.2'],
                ['loc' => url('/privacy'), 'priority' => '0.2'],
            ];

            Category::orderBy('id')->pluck('slug')->each(function ($slug) use (&$urls) {
                $urls[] = ['loc' => url('/c/'.$slug), 'priority' => '0.8'];
            });

            Business::whereHas('branches', fn ($q) => $q->where('status', 'active'))
                ->orderBy('id')
                ->pluck('slug')
                ->each(function ($slug) use (&$urls) {
                    $urls[] = ['loc' => url('/b/'.$slug), 'priority' => '0.6'];
                });

            $items = collect($urls)->map(fn ($u) => sprintf(
                '<url><loc>%s</loc><priority>%s</priority></url>',
                htmlspecialchars($u['loc'], ENT_XML1),
                $u['priority'],
            ))->implode('');

            return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$items.'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
