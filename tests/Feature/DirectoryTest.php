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
        \Illuminate\Support\Facades\Cache::store('file')->forget('categories:index:v2');

        $category = Category::factory()->create(['slug' => 'food']);
        Business::factory()->create(['category_id' => $category->id]);

        // 1-рт cache бөглөнө, 2-рт cache-ээс уншина — хоёулаа 200 байх ёстой
        $this->getJson('/api/v1/categories')->assertOk()->assertJsonPath('data.0.slug', 'food');
        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'food')
            ->assertJsonPath('data.0.businesses_count', 1);

        \Illuminate\Support\Facades\Cache::store('file')->forget('categories:index:v2');
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
        $this->assertTrue($response->json('data.0.business.is_featured'));
        $this->assertSame('Plain', $response->json('data.1.business.name'));
    }

    public function test_keyword_campaign_boosts_matching_search(): void
    {
        // Тэмдэглэл: SQLite-ийн LIKE кирилл үсэгт case-sensitive тул тестийн
        // өгөгдөл жижиг үсгээр — MySQL (utf8mb4_unicode_ci) дээр case-insensitive.
        $target = Business::factory()->create(['name' => 'Хангай авто сервис']);
        Branch::factory()->create(['business_id' => $target->id, 'rating_avg' => 3.0]);

        $other = Business::factory()->create(['name' => 'Мастер', 'description' => 'авто засвар']);
        Branch::factory()->create(['business_id' => $other->id, 'rating_avg' => 5.0]);

        Campaign::factory()->create([
            'organization_id' => $target->organization_id,
            'business_id' => $target->id,
            'type' => 'keyword',
            'keyword' => 'авто',
            'slot' => 1,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(10),
        ]);

        $response = $this->getJson('/api/v1/search?q='.urlencode('авто'));

        $this->assertSame('Хангай авто сервис', $response->json('data.0.business.name'));

        // Хэсэгчилсэн тохирол: «авто засвар» гэж хайхад ч «авто» түлхүүр гарна
        $partial = $this->getJson('/api/v1/search?q='.urlencode('авто засвар'));
        $this->assertSame('Хангай авто сервис', $partial->json('data.0.business.name'));
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
