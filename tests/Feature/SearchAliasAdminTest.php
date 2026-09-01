<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Category;
use App\Models\SearchAlias;
use App\Models\User;
use App\Services\SearchIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Хайлтын синонимыг админаас удирдах (код байршуулалгүйгээр).
 */
class SearchAliasAdminTest extends TestCase
{
    use RefreshDatabase;

    protected Category $electrician;

    protected function setUp(): void
    {
        parent::setUp();

        $root = Category::factory()->create(['name' => 'Барилга, засвар', 'slug' => 'construction']);
        $this->electrician = Category::factory()->create([
            'name' => 'Цахилгаанчин', 'slug' => 'construction-4', 'parent_id' => $root->id,
        ]);
    }

    protected function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_can_add_a_synonym_and_it_becomes_searchable_at_once(): void
    {
        $business = Business::factory()->create(['category_id' => $this->electrician->id, 'name' => 'Гэрэл Электрик']);
        Branch::factory()->create(['business_id' => $business->id]);
        Branch::factory()->create(); // өөр ангилал

        // Синоним нэмэхээс өмнө «тог» гэж хайхад олдохгүй
        $this->getJson('/api/v1/search?q='.urlencode('тог'))->assertOk()->assertJsonPath('meta.total', 0);

        $this->actingAs($this->admin())
            ->postJson('/api/v1/admin/search-aliases', [
                'category_id' => $this->electrician->id,
                'term' => 'тог',
            ])
            ->assertCreated();

        $this->getJson('/api/v1/search?q='.urlencode('тог'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.business.name', 'Гэрэл Электрик');

        // Латинаар бичсэн ч ажиллана
        $this->getJson('/api/v1/search?q=tog')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_duplicate_synonym_in_the_same_category_is_rejected(): void
    {
        SearchAlias::create(['category_id' => $this->electrician->id, 'term' => 'тог']);

        $this->actingAs($this->admin())
            ->postJson('/api/v1/admin/search-aliases', [
                'category_id' => $this->electrician->id,
                'term' => 'ТОГ', // fold хийхэд ижил түлхүүр
            ])
            ->assertStatus(422);
    }

    public function test_deleting_a_synonym_removes_it_from_search(): void
    {
        $business = Business::factory()->create(['category_id' => $this->electrician->id]);
        Branch::factory()->create(['business_id' => $business->id]);

        $alias = SearchAlias::create(['category_id' => $this->electrician->id, 'term' => 'тог']);
        app(SearchIndexer::class)->reindexAll();

        $this->getJson('/api/v1/search?q='.urlencode('тог'))->assertJsonPath('meta.total', 1);

        $this->actingAs($this->admin())
            ->deleteJson("/api/v1/admin/search-aliases/{$alias->id}")
            ->assertOk();

        $this->getJson('/api/v1/search?q='.urlencode('тог'))->assertJsonPath('meta.total', 0);
    }

    public function test_admin_listing_groups_synonyms_by_category(): void
    {
        SearchAlias::create(['category_id' => $this->electrician->id, 'term' => 'тог']);
        SearchAlias::create(['category_id' => $this->electrician->id, 'term' => 'тог татах']);

        $response = $this->actingAs($this->admin())->getJson('/api/v1/admin/search-aliases')->assertOk();

        $response->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.category.slug', 'construction-4')
            ->assertJsonCount(2, 'data.0.terms');
    }

    public function test_a_regular_user_cannot_manage_synonyms(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->postJson('/api/v1/admin/search-aliases', ['category_id' => $this->electrician->id, 'term' => 'тог'])
            ->assertForbidden();
    }
}
