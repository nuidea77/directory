<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Listing;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_listing_index_with_filters(): void
    {
        $category = Category::factory()->create(['slug' => 'food']);
        Listing::factory()->count(3)->create(['category_id' => $category->id]);
        Listing::factory()->create(['status' => 'hidden', 'category_id' => $category->id]);
        Listing::factory()->create(); // other category

        $this->getJson('/api/v1/listings')->assertOk()->assertJsonCount(4, 'data');
        $this->getJson('/api/v1/listings?category=food')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_featured_listings_rank_first(): void
    {
        Listing::factory()->create(['name' => 'Plain']);
        Listing::factory()->featured()->create(['name' => 'Featured']);

        $response = $this->getJson('/api/v1/listings');

        $this->assertSame('Featured', $response->json('data.0.name'));
        $this->assertTrue($response->json('data.0.is_featured'));
    }

    public function test_search_matches_name_and_description(): void
    {
        Listing::factory()->create(['name' => 'Модерн Номадс']);
        Listing::factory()->create(['name' => 'Other', 'description' => 'үндэсний хоол']);
        Listing::factory()->create(['name' => 'Unrelated', 'description' => 'nothing']);

        $this->getJson('/api/v1/listings?q='.urlencode('Номадс'))->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/listings?q='.urlencode('үндэсний'))->assertJsonCount(1, 'data');
    }

    public function test_show_increments_views_and_hides_inactive(): void
    {
        $listing = Listing::factory()->create(['views_count' => 0]);

        $this->getJson("/api/v1/listings/{$listing->slug}")->assertOk();
        $this->assertSame(1, $listing->refresh()->views_count);

        $hidden = Listing::factory()->create(['status' => 'hidden']);
        $this->getJson("/api/v1/listings/{$hidden->slug}")->assertNotFound();
    }

    public function test_guest_cannot_create_listing(): void
    {
        $this->postJson('/api/v1/my/listings', [])->assertUnauthorized();
    }

    public function test_owner_can_create_update_and_delete_listing(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $create = $this->actingAs($user)->postJson('/api/v1/my/listings', [
            'name' => 'Тест Кафе',
            'category_id' => $category->id,
            'phone' => '99887766',
            'district' => 'Баянгол',
        ]);

        $create->assertCreated()->assertJsonPath('data.name', 'Тест Кафе');
        $id = $create->json('data.id');

        $this->actingAs($user)->postJson("/api/v1/my/listings/{$id}", [
            'name' => 'Шинэ Нэр',
        ])->assertOk()->assertJsonPath('data.name', 'Шинэ Нэр');

        // Another user cannot touch it.
        $other = User::factory()->create();
        $this->actingAs($other)->postJson("/api/v1/my/listings/{$id}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($other)->deleteJson("/api/v1/my/listings/{$id}")->assertForbidden();

        $this->actingAs($user)->deleteJson("/api/v1/my/listings/{$id}")->assertOk();
        $this->assertDatabaseMissing('listings', ['id' => $id]);
    }

    public function test_review_updates_listing_rating(): void
    {
        $listing = Listing::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson("/api/v1/listings/{$listing->id}/reviews", [
            'rating' => 4,
            'comment' => 'Сайн',
        ])->assertCreated();

        $listing->refresh();
        $this->assertSame(1, $listing->reviews_count);
        $this->assertSame(4.0, (float) $listing->rating_avg);

        // Same user re-reviews: updates, does not duplicate.
        $this->actingAs($user)->postJson("/api/v1/listings/{$listing->id}/reviews", [
            'rating' => 2,
        ])->assertOk();

        $listing->refresh();
        $this->assertSame(1, $listing->reviews_count);
        $this->assertSame(2.0, (float) $listing->rating_avg);
    }

    public function test_owner_cannot_review_own_listing(): void
    {
        $user = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->postJson("/api/v1/listings/{$listing->id}/reviews", [
            'rating' => 5,
        ])->assertStatus(422);
    }

    public function test_favorites_toggle_and_index(): void
    {
        $user = User::factory()->create();
        $listing = Listing::factory()->create();

        $this->actingAs($user)->postJson("/api/v1/listings/{$listing->id}/favorite")
            ->assertOk()->assertJson(['favorited' => true]);

        $this->actingAs($user)->getJson('/api/v1/favorites')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($user)->postJson("/api/v1/listings/{$listing->id}/favorite")
            ->assertOk()->assertJson(['favorited' => false]);

        $this->actingAs($user)->getJson('/api/v1/favorites')
            ->assertOk()->assertJsonCount(0, 'data');
    }
}
