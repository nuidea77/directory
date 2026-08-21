<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_active_branches_only(): void
    {
        Branch::factory()->count(3)->create();
        Branch::factory()->pending()->create();

        $this->getJson('/api/v1/search')->assertOk()->assertJsonPath('meta.total', 3);
    }

    public function test_search_filters_by_category_and_district(): void
    {
        $category = Category::factory()->create(['slug' => 'food']);
        $business = Business::factory()->create(['category_id' => $category->id]);
        Branch::factory()->create(['business_id' => $business->id, 'district' => 'Сүхбаатар']);
        Branch::factory()->create(['business_id' => $business->id, 'district' => 'Баянгол']);
        Branch::factory()->create(); // өөр ангилал

        $this->getJson('/api/v1/search?category=food')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/search?category=food&district='.urlencode('Сүхбаатар'))->assertJsonPath('meta.total', 1);
    }

    public function test_search_with_unknown_category_returns_no_results(): void
    {
        Branch::factory()->count(3)->create();

        // Байхгүй ангилал → бүх бизнесийг буцаахгүй, хоосон илэрц
        $this->getJson('/api/v1/search?category=ogt-baihgui-angilal')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_categories_endpoint_survives_a_serializing_cache_store(): void
    {
        // Production-д CACHE_STORE=database/file — cache-д Eloquent объект
        // хадгалбал unserialize эвдэрч 500 өгдөг байсан (regression)
        config(['cache.default' => 'file']);
        \Illuminate\Support\Facades\Cache::store('file')->forget('categories:index:v3');

        $category = Category::factory()->create(['slug' => 'food']);
        Business::factory()->create(['category_id' => $category->id]);

        // 1-рт cache бөглөнө, 2-рт cache-ээс уншина — хоёулаа 200 байх ёстой
        $this->getJson('/api/v1/categories')->assertOk()->assertJsonPath('data.0.slug', 'food');
        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'food')
            ->assertJsonPath('data.0.businesses_count', 1);

        \Illuminate\Support\Facades\Cache::store('file')->forget('categories:index:v3');
    }

    public function test_24_7_flag_is_computed_from_hours(): void
    {
        $full = collect(Branch::WEEKDAYS)->mapWithKeys(fn ($d) => [$d => ['from' => '00:00', 'to' => '00:00']])->all();
        $almost = $full;
        $almost['sun'] = ['closed' => true]; // ням гарагт хаалттай

        $day = collect(Branch::WEEKDAYS)->mapWithKeys(fn ($d) => [$d => ['from' => '09:00', 'to' => '18:00']])->all();

        $always = Branch::factory()->create(['hours' => $full]);
        $notQuite = Branch::factory()->create(['hours' => $almost]);
        $normal = Branch::factory()->create(['hours' => $day]);

        $this->assertTrue($always->refresh()->is_24_7);
        $this->assertFalse($notQuite->refresh()->is_24_7, 'Ням гарагт хаалттай бол 24/7 биш');
        $this->assertFalse($normal->refresh()->is_24_7);

        // 00:00–23:59 хэлбэрийг ч 24 цаг гэж үзнэ
        $normal->update(['hours' => collect(Branch::WEEKDAYS)->mapWithKeys(fn ($d) => [$d => ['from' => '00:00', 'to' => '23:59']])->all()]);
        $this->assertTrue($normal->refresh()->is_24_7, 'Цагийн хуваарь солиход туг дахин бодогдоно');

        // Буцаад энгийн болгоход туг арилна
        $normal->update(['hours' => $day]);
        $this->assertFalse($normal->refresh()->is_24_7);
    }

    public function test_search_can_filter_by_24_7(): void
    {
        $full = collect(Branch::WEEKDAYS)->mapWithKeys(fn ($d) => [$d => ['from' => '00:00', 'to' => '00:00']])->all();
        $day = collect(Branch::WEEKDAYS)->mapWithKeys(fn ($d) => [$d => ['from' => '09:00', 'to' => '18:00']])->all();

        Branch::factory()->create(['hours' => $full, 'name' => 'Шөнөжингөө']);
        Branch::factory()->count(2)->create(['hours' => $day]);

        $all = $this->getJson('/api/v1/search')->assertOk();
        $only = $this->getJson('/api/v1/search?open_24_7=1')->assertOk();

        $this->assertSame(3, $all->json('meta.total'));
        // Хуудаслалтын тоо ч зөв (SQL багана тул)
        $this->assertSame(1, $only->json('meta.total'));
        $this->assertSame('Шөнөжингөө', $only->json('data.0.name'));
        $this->assertTrue($only->json('data.0.is_24_7'));
    }

    public function test_overnight_hours_are_reported_open(): void
    {
        // 18:00–02:00 гэх мэт шөнө дамжсан цагийг «хаалттай» гэж үздэг байсан
        $hours = collect(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])
            ->mapWithKeys(fn ($d) => [$d => ['from' => '18:00', 'to' => '02:00']])->all();

        $branch = Branch::factory()->create(['hours' => $hours]);

        $this->travelTo(now()->setTime(20, 0));
        $this->assertTrue($branch->openState()['open'], '20:00 цагт нээлттэй байх ёстой');

        $this->travelTo(now()->setTime(23, 30));
        $this->assertTrue($branch->openState()['open'], '23:30 цагт нээлттэй байх ёстой');

        $this->travelTo(now()->addDay()->setTime(1, 0));
        $this->assertTrue($branch->openState()['open'], 'шөнийн 01:00 цагт нээлттэй байх ёстой');

        $this->travelTo(now()->setTime(10, 0));
        $this->assertFalse($branch->openState()['open'], '10:00 цагт хаалттай байх ёстой');

        $this->travelBack();
    }

    public function test_full_day_hours_are_labelled_24_hours(): void
    {
        // Зөвхөн Даваа гарагт 24 цаг ажилладаг салбар
        $hours = collect(Branch::WEEKDAYS)->mapWithKeys(fn ($d) => [
            $d => $d === 'mon' ? ['from' => '00:00', 'to' => '00:00'] : ['from' => '09:00', 'to' => '18:00'],
        ])->all();

        $branch = Branch::factory()->create(['hours' => $hours]);

        // Даваа гараг, шөнө дунд ч гэсэн «Нээлттэй · 24 цаг»
        $this->travelTo(now()->startOfWeek()->setTime(3, 0));
        $state = $branch->openState();
        $this->assertTrue($state['open']);
        $this->assertSame('Нээлттэй · 24 цаг', $state['label']);

        // Мягмар гарагт энгийн хуваарь — 24 цаг гэж бичихгүй
        $this->travelTo(now()->startOfWeek()->addDay()->setTime(10, 0));
        $this->assertStringNotContainsString('24 цаг', $branch->openState()['label']);

        // Ганц өдөр 24 цаг байснаар 24/7 болохгүй
        $this->assertFalse($branch->refresh()->is_24_7);

        $this->travelBack();
    }

    public function test_home_returns_themed_sections(): void
    {
        $food = Category::factory()->create(['slug' => 'restaurants', 'name' => 'Хоол, ресторан']);
        $business = Business::factory()->create(['category_id' => $food->id, 'price_level' => '₮₮']);

        $full = collect(Branch::WEEKDAYS)->mapWithKeys(fn ($d) => [$d => ['from' => '00:00', 'to' => '00:00']])->all();
        Branch::factory()->create([
            'business_id' => $business->id,
            'city' => 'Улаанбаатар',
            'hours' => $full,
            'rating_avg' => 4.8,
            'reviews_count' => 12,
        ]);

        \Illuminate\Support\Facades\Cache::forget('home:sections:v2:Улаанбаатар');

        $sections = collect($this->getJson('/api/v1/home')->assertOk()->json('sections'));

        $this->assertNotEmpty($sections);
        $this->assertEqualsCanonicalizing(
            ['eat', 'date', 'open_24_7', 'newest'],
            $sections->pluck('key')->all(),
        );

        // 24/7 блокод тухайн салбар орсон байх ёстой
        $open247 = $sections->firstWhere('key', 'open_24_7');
        $this->assertSame($business->name, $open247['items'][0]['name']);
        $this->assertTrue($open247['items'][0]['is_24_7']);

        \Illuminate\Support\Facades\Cache::forget('home:sections:v2:Улаанбаатар');
    }

    public function test_rejection_reason_is_not_exposed_publicly(): void
    {
        $branch = Branch::factory()->create([
            'status' => 'active',
            'rejection_reason' => 'ДОТООД ТЭМДЭГЛЭЛ — хуурамч хаяг',
        ]);

        $response = $this->getJson('/api/v1/search');

        $response->assertOk();
        $this->assertStringNotContainsString('ДОТООД ТЭМДЭГЛЭЛ', $response->getContent());
        $this->assertStringNotContainsString('rejection_reason', $response->getContent());

        // Бизнесийн дэлгэрэнгүйд ч мөн адил
        $detail = $this->getJson('/api/v1/businesses/'.$branch->business->slug);
        $this->assertStringNotContainsString('ДОТООД ТЭМДЭГЛЭЛ', $detail->getContent());
    }

    public function test_non_active_branches_are_not_returned_on_home(): void
    {
        $business = Business::factory()->create();
        Branch::factory()->create(['business_id' => $business->id, 'status' => 'active']);
        Branch::factory()->create([
            'business_id' => $business->id,
            'status' => 'rejected',
            'name' => 'ТАТГАЛЗСАН САЛБАР',
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk();
        $this->assertStringNotContainsString('ТАТГАЛЗСАН САЛБАР', $response->getContent());
    }

    public function test_category_stats_include_subcategory_businesses(): void
    {
        $parent = Category::factory()->create(['slug' => 'food']);
        $child = Category::factory()->create(['slug' => 'food-cafe', 'parent_id' => $parent->id]);

        Branch::factory()->create(['business_id' => Business::factory()->create(['category_id' => $parent->id])->id]);
        Branch::factory()->create(['business_id' => Business::factory()->create(['category_id' => $child->id])->id]);

        // Статистик ба илэрцийн тоо зөрөхгүй байх ёстой
        $stats = $this->getJson('/api/v1/categories/food')->assertOk()->json('stats.total');
        $results = $this->getJson('/api/v1/search?category=food')->assertOk()->json('meta.total');

        $this->assertSame(2, $stats);
        $this->assertSame($results, $stats);
    }

    public function test_categories_expose_their_icon(): void
    {
        Category::factory()->create(['slug' => 'food', 'icon' => 'utensils']);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data.0.icon', 'utensils');
    }

    public function test_admin_can_change_a_category_icon(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'phone_verified_at' => now()]);
        $category = Category::factory()->create(['slug' => 'food', 'icon' => 'utensils']);

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/categories/{$category->id}", ['icon' => 'cake'])
            ->assertOk();

        $this->assertSame('cake', $category->refresh()->icon);

        // Хоосон утга илгээвэл ерөнхий icon руу буцна
        $this->actingAs($admin)
            ->putJson("/api/v1/admin/categories/{$category->id}", ['icon' => ''])
            ->assertOk();

        $this->assertNull($category->refresh()->icon);

        // Буруу форматыг хүлээж авахгүй
        $this->actingAs($admin)
            ->putJson("/api/v1/admin/categories/{$category->id}", ['icon' => 'Not Valid!'])
            ->assertStatus(422);
    }

    public function test_admin_can_move_and_reorder_categories(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'phone_verified_at' => now()]);
        $food = Category::factory()->create(['slug' => 'food', 'sort_order' => 0]);
        $auto = Category::factory()->create(['slug' => 'auto', 'sort_order' => 1]);
        $child = Category::factory()->create(['slug' => 'food-cafe', 'parent_id' => $food->id]);

        // Дэд ангиллыг өөр эцэг рүү зөөх
        $this->actingAs($admin)
            ->putJson("/api/v1/admin/categories/{$child->id}", ['parent_id' => $auto->id])
            ->assertOk();

        $this->assertSame($auto->id, $child->refresh()->parent_id);

        // Эрэмбэ солих
        $this->actingAs($admin)
            ->putJson("/api/v1/admin/categories/{$auto->id}", ['sort_order' => 0])
            ->assertOk();

        $this->assertSame(0, $auto->refresh()->sort_order);

        // Өөрийгөө эцэг болгож мөчлөг үүсгэхийг хориглоно
        $this->actingAs($admin)
            ->putJson("/api/v1/admin/categories/{$auto->id}", ['parent_id' => $auto->id])
            ->assertStatus(422);

        // Тайлбарыг хоосон болгож болно
        $this->actingAs($admin)
            ->putJson("/api/v1/admin/categories/{$food->id}", ['description' => 'Хоолны газрууд'])
            ->assertOk();
        $this->assertSame('Хоолны газрууд', $food->refresh()->description);

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/categories/{$food->id}", ['description' => ''])
            ->assertOk();
        $this->assertNull($food->refresh()->description);
    }

    public function test_home_featured_is_scoped_to_the_requested_city(): void
    {
        // Улаанбаатарын бизнес
        $ubBusiness = Business::factory()->create(['name' => 'УБ Бизнес']);
        Branch::factory()->create(['business_id' => $ubBusiness->id, 'city' => 'Улаанбаатар', 'rating_avg' => 5.0]);

        // Дарханы бизнес
        $darkhan = Business::factory()->create(['name' => 'Дархан Бизнес']);
        Branch::factory()->create(['business_id' => $darkhan->id, 'city' => 'Дархан-Уул', 'rating_avg' => 3.0]);

        $ub = $this->getJson('/api/v1/home?city='.urlencode('Улаанбаатар'))->assertOk();
        $dk = $this->getJson('/api/v1/home?city='.urlencode('Дархан-Уул'))->assertOk();

        $ubNames = collect($ub->json('featured'))->pluck('name');
        $dkNames = collect($dk->json('featured'))->pluck('name');

        // Хот бүр өөрийн бизнесээ л харуулна — өмнө нь хаанаас орсон ч
        // Улаанбаатарын жагсаалт ижилхэн гарч байсан
        $this->assertContains('УБ Бизнес', $ubNames->all());
        $this->assertNotContains('Дархан Бизнес', $ubNames->all());

        $this->assertContains('Дархан Бизнес', $dkNames->all());
        $this->assertNotContains('УБ Бизнес', $dkNames->all());
    }

    public function test_home_featured_campaign_only_runs_in_its_own_city(): void
    {
        $darkhan = Business::factory()->create(['name' => 'Дархан Онцлох']);
        Branch::factory()->create(['business_id' => $darkhan->id, 'city' => 'Дархан-Уул']);

        Campaign::factory()->create([
            'organization_id' => $darkhan->organization_id,
            'business_id' => $darkhan->id,
            'type' => 'home_featured',
            'category_id' => null,
            'district' => null,
            'city' => 'Дархан-Уул',
            'keyword' => null,
            'slot' => 1,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(10),
        ]);

        $dk = collect($this->getJson('/api/v1/home?city='.urlencode('Дархан-Уул'))->json('featured'));
        $ub = collect($this->getJson('/api/v1/home?city='.urlencode('Улаанбаатар'))->json('featured'));

        $this->assertTrue($dk->firstWhere('name', 'Дархан Онцлох')['is_featured'] ?? false);
        $this->assertNull($ub->firstWhere('name', 'Дархан Онцлох'));
    }

    public function test_featured_campaign_pins_business_to_top(): void
    {
        $category = Category::factory()->create(['slug' => 'auto']);

        $plain = Business::factory()->create(['category_id' => $category->id, 'name' => 'Plain']);
        Branch::factory()->create(['business_id' => $plain->id, 'district' => 'Баянзүрх', 'rating_avg' => 5.0]);

        $promoted = Business::factory()->create(['category_id' => $category->id, 'name' => 'Promoted']);
        Branch::factory()->create(['business_id' => $promoted->id, 'district' => 'Баянзүрх', 'rating_avg' => 3.0]);

        Campaign::factory()->create([
            'organization_id' => $promoted->organization_id,
            'business_id' => $promoted->id,
            'type' => 'category_featured',
            'category_id' => $category->id,
            'district' => 'Баянзүрх',
            'slot' => 1,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(10),
        ]);

        $response = $this->getJson('/api/v1/search?category=auto&district='.urlencode('Баянзүрх'));

        $this->assertSame('Promoted', $response->json('data.0.business.name'));
        // Онцлох нь тухайн САЛБАР дээр тэмдэглэгддэг
        $this->assertTrue($response->json('data.0.is_featured'));
        $this->assertSame('Plain', $response->json('data.1.business.name'));
    }

    public function test_search_filters_by_city_and_business_plan_ranks_higher(): void
    {
        // Хөвсгөлд салбартай бизнес
        $khovsgol = Business::factory()->create(['name' => 'мөрөн зочид буудал']);
        Branch::factory()->create(['business_id' => $khovsgol->id, 'city' => 'Хөвсгөл', 'district' => 'Мөрөн', 'rating_avg' => 3.0]);

        // УБ-д: энгийн (өндөр үнэлгээ) + Бизнес эрхтэй (бага үнэлгээ)
        $plain = Business::factory()->create(['name' => 'энгийн буудал']);
        Branch::factory()->create(['business_id' => $plain->id, 'rating_avg' => 5.0]);

        $paidOrg = \App\Models\Organization::factory()->create(['plan' => 'business', 'plan_expires_at' => now()->addYear()]);
        $paid = Business::factory()->create(['organization_id' => $paidOrg->id, 'name' => 'топ буудал']);
        Branch::factory()->create(['business_id' => $paid->id, 'rating_avg' => 4.0]);

        // city шүүлтүүр
        $this->getJson('/api/v1/search?city='.urlencode('Хөвсгөл'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.business.name', 'мөрөн зочид буудал');

        // Бизнес эрхтэй нь (топ жагсаалт) энгийнээс өмнө гарна
        $all = $this->getJson('/api/v1/search?city='.urlencode('Улаанбаатар'));
        $this->assertSame('топ буудал', $all->json('data.0.business.name'));
    }

    public function test_business_detail_hides_business_without_active_branches(): void
    {
        $business = Business::factory()->create();
        Branch::factory()->pending()->create(['business_id' => $business->id]);

        $this->getJson("/api/v1/businesses/{$business->slug}")->assertNotFound();
    }

    public function test_events_recorded(): void
    {
        $branch = Branch::factory()->create();

        $this->postJson("/api/v1/branches/{$branch->id}/event", ['type' => 'view', 'source' => 'category'])->assertOk();
        $this->postJson("/api/v1/branches/{$branch->id}/event", ['type' => 'call'])->assertOk();

        $branch->refresh();
        $this->assertSame(1, $branch->views_count);
        $this->assertSame(1, $branch->calls_count);
        $this->assertDatabaseHas('branch_stats', ['branch_id' => $branch->id, 'views' => 1, 'views_category' => 1, 'calls' => 1]);
    }

    public function test_review_updates_branch_rating_and_owner_cannot_review(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson("/api/v1/branches/{$branch->id}/reviews", ['rating' => 4, 'comment' => 'Сайн'])
            ->assertCreated();

        $branch->refresh();
        $this->assertSame(1, $branch->reviews_count);
        $this->assertSame(4.0, (float) $branch->rating_avg);

        $owner = $branch->business->organization->owner;
        $this->actingAs($owner)->postJson("/api/v1/branches/{$branch->id}/reviews", ['rating' => 5])
            ->assertStatus(422);
    }

    public function test_review_report_helpful_and_corrections(): void
    {
        $branch = Branch::factory()->create();
        $author = User::factory()->create();
        $reader = User::factory()->create();

        $review = \App\Models\Review::factory()->create(['branch_id' => $branch->id, 'user_id' => $author->id, 'status' => 'active']);

        // Report → flagged, админы дараалалд орно
        $this->actingAs($reader)->postJson("/api/v1/branches/{$branch->id}/reviews/{$review->id}/report")->assertOk();
        $this->assertSame('flagged', $review->refresh()->status);

        // Өөрийн сэтгэгдлийг report хийж болохгүй
        $review->update(['status' => 'active']);
        $this->actingAs($author)->postJson("/api/v1/branches/{$branch->id}/reviews/{$review->id}/report")->assertStatus(422);

        // Helpful toggle
        $this->actingAs($reader)->postJson("/api/v1/reviews/{$review->id}/helpful")->assertOk()->assertJsonPath('helpful_count', 1);
        $this->actingAs($reader)->postJson("/api/v1/reviews/{$review->id}/helpful")->assertOk()->assertJsonPath('helpful_count', 0);

        // Залруулга → админд харагдана
        $this->actingAs($reader)->postJson("/api/v1/branches/{$branch->id}/corrections", ['text' => 'Утас солигдсон'])->assertCreated();

        $admin = User::factory()->create(['is_admin' => true]);
        $list = $this->actingAs($admin)->getJson('/api/v1/admin/corrections');
        $list->assertOk()->assertJsonPath('data.0.text', 'Утас солигдсон');

        $this->actingAs($admin)->postJson('/api/v1/admin/corrections/'.$list->json('data.0.id').'/moderate', ['action' => 'accept'])->assertOk();
        $this->assertSame('accepted', \App\Models\Correction::first()->status);
    }

    public function test_nearby_search_with_radius(): void
    {
        $near = Branch::factory()->create(['lat' => 47.9180, 'lng' => 106.9170]);
        Branch::factory()->create(['lat' => 47.5000, 'lng' => 105.0000]); // хол

        $response = $this->getJson('/api/v1/search?lat=47.9184&lng=106.9177&radius=5&sort=distance');

        $response->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertSame($near->id, $response->json('data.0.id'));
        $this->assertNotNull($response->json('data.0.distance_km'));
    }
}
