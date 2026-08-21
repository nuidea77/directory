<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ангиллын мод 3 түвшинтэй:
 * Боловсрол → Хэлний сургалт → Англи хэлний сургалт.
 */
class CategoryTreeTest extends TestCase
{
    use RefreshDatabase;

    protected Category $root;
    protected Category $child;
    protected Category $grandChild;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = Category::factory()->create(['name' => 'Боловсрол', 'slug' => 'education', 'sort_order' => 0]);
        $this->child = Category::factory()->create(['name' => 'Хэлний сургалт', 'slug' => 'education-1', 'parent_id' => $this->root->id]);
        $this->grandChild = Category::factory()->create(['name' => 'Англи хэл', 'slug' => 'education-1-1', 'parent_id' => $this->child->id]);
    }

    public function test_tree_endpoint_returns_three_levels(): void
    {
        $response = $this->getJson('/api/v1/categories')->assertOk();

        $response->assertJsonPath('data.0.slug', 'education')
            ->assertJsonPath('data.0.depth', 1)
            ->assertJsonPath('data.0.children.0.slug', 'education-1')
            ->assertJsonPath('data.0.children.0.depth', 2)
            ->assertJsonPath('data.0.children.0.children.0.slug', 'education-1-1')
            ->assertJsonPath('data.0.children.0.children.0.depth', 3);
    }

    public function test_parent_category_search_includes_grandchild_businesses(): void
    {
        $business = Business::factory()->create(['category_id' => $this->grandChild->id]);
        Branch::factory()->create(['business_id' => $business->id]);

        // Хамгийн гүн ангилалд бүртгэлтэй бизнес эцэг ба өвөг ангиллаас олдоно
        foreach (['education', 'education-1', 'education-1-1'] as $slug) {
            $this->getJson("/api/v1/search?category={$slug}")
                ->assertOk()
                ->assertJsonPath('meta.total', 1);
        }

        // Ангиллын хуудасны статистик ч мөн адил
        $this->getJson('/api/v1/categories/education')
            ->assertOk()
            ->assertJsonPath('stats.total', 1);
    }

    public function test_category_page_returns_ancestors_for_breadcrumb(): void
    {
        $this->getJson('/api/v1/categories/education-1-1')
            ->assertOk()
            ->assertJsonPath('ancestors.0.slug', 'education')
            ->assertJsonPath('ancestors.1.slug', 'education-1');
    }

    public function test_admin_cannot_create_a_fourth_level(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/categories', ['name' => 'Ахисан түвшин', 'parent_id' => $this->grandChild->id])
            ->assertStatus(422);

        $this->assertSame(0, $this->grandChild->children()->count());
    }

    public function test_admin_cannot_move_a_category_under_its_own_descendant(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/categories/{$this->root->id}", ['parent_id' => $this->grandChild->id])
            ->assertStatus(422);

        $this->assertNull($this->root->refresh()->parent_id);
    }

    public function test_deleting_a_category_removes_the_whole_subtree(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/categories/{$this->root->id}")
            ->assertOk();

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_a_category_with_businesses_in_a_grandchild_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Business::factory()->create(['category_id' => $this->grandChild->id]);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/categories/{$this->root->id}")
            ->assertStatus(422);

        $this->assertDatabaseCount('categories', 3);
    }
}
