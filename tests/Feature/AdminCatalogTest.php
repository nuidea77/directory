<?php

namespace Tests\Feature;

use App\Models\Amenity;
use App\Models\Branch;
use App\Models\Category;
use App\Models\PaymentApp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Админаас үйлчилгээ/онцлог болон зээлийн аппыг удирдах.
 */
class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    // --- Үйлчилгээ, онцлог -------------------------------------------------

    public function test_admin_can_add_an_amenity_to_a_category(): void
    {
        $category = Category::factory()->create(['name' => 'Зочид буудал', 'slug' => 'hotels']);

        $this->actingAs($this->admin())
            ->postJson('/api/v1/admin/amenities', [
                'category_id' => $category->id,
                'name' => 'Тэшүүрийн талбай',
                'icon' => 'snowflake',
            ])
            ->assertCreated();

        // Нийтийн API-д шууд гарна (кэш модел дээрээ цэвэрлэгддэг)
        $names = array_column(
            $this->getJson('/api/v1/amenities?category=hotels')->assertOk()->json('data'),
            'name',
        );

        $this->assertContains('Тэшүүрийн талбай', $names);
    }

    public function test_amenity_without_a_category_shows_everywhere(): void
    {
        Category::factory()->create(['slug' => 'hotels']);

        $this->actingAs($this->admin())
            ->postJson('/api/v1/admin/amenities', ['name' => 'Цэнэглэгч', 'icon' => 'zap'])
            ->assertCreated();

        foreach (['/api/v1/amenities', '/api/v1/amenities?category=hotels'] as $url) {
            $names = array_column($this->getJson($url)->json('data'), 'name');
            $this->assertContains('Цэнэглэгч', $names);
        }
    }

    public function test_renaming_an_amenity_moves_the_value_stored_on_branches(): void
    {
        $amenity = Amenity::create(['name' => 'Усан сан', 'icon' => 'waves']);
        $branch = Branch::factory()->create(['amenities' => ['Усан сан', 'Зогсоол']]);
        $other = Branch::factory()->create(['amenities' => ['Зогсоол']]);

        $this->actingAs($this->admin())
            ->putJson("/api/v1/admin/amenities/{$amenity->id}", ['name' => 'Бассейн'])
            ->assertOk();

        $this->assertSame(['Бассейн', 'Зогсоол'], $branch->fresh()->amenities);
        $this->assertSame(['Зогсоол'], $other->fresh()->amenities);

        // Шүүлтүүр шинэ нэрээр ажиллана
        $this->getJson('/api/v1/search?amenity='.urlencode('Бассейн'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_duplicate_amenity_in_the_same_category_is_rejected(): void
    {
        $category = Category::factory()->create(['slug' => 'hotels']);
        Amenity::create(['category_id' => $category->id, 'name' => 'Сауна', 'icon' => 'flame']);

        $this->actingAs($this->admin())
            ->postJson('/api/v1/admin/amenities', [
                'category_id' => $category->id, 'name' => 'Сауна', 'icon' => 'flame',
            ])
            ->assertStatus(422);
    }

    public function test_deleting_an_amenity_removes_it_from_the_public_list(): void
    {
        $amenity = Amenity::create(['name' => 'Wi-Fi', 'icon' => 'wifi']);

        $this->assertContains('Wi-Fi', array_column($this->getJson('/api/v1/amenities')->json('data'), 'name'));

        $this->actingAs($this->admin())->deleteJson("/api/v1/admin/amenities/{$amenity->id}")->assertOk();

        $this->assertNotContains('Wi-Fi', array_column($this->getJson('/api/v1/amenities')->json('data'), 'name'));
    }

    // --- Зээлийн апп --------------------------------------------------------

    public function test_admin_can_add_a_payment_app(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/v1/admin/payment-apps', [
                'slug' => 'simple', 'name' => 'Simple', 'color' => '#123456',
            ])
            ->assertCreated();

        $apps = $this->getJson('/api/v1/payments')->assertOk()->json('data');

        $this->assertContains('Simple', array_column($apps, 'name'));
        $this->assertSame('#123456', collect($apps)->firstWhere('slug', 'simple')['color']);
    }

    public function test_inactive_payment_app_is_hidden_from_the_public_list(): void
    {
        $app = PaymentApp::create(['slug' => 'lendmn', 'name' => 'LendMN', 'color' => '#1a7f5a']);

        $this->assertContains('LendMN', array_column($this->getJson('/api/v1/payments')->json('data'), 'name'));

        $this->actingAs($this->admin())
            ->putJson("/api/v1/admin/payment-apps/{$app->id}", ['is_active' => false])
            ->assertOk();

        $this->assertNotContains('LendMN', array_column($this->getJson('/api/v1/payments')->json('data'), 'name'));
    }

    public function test_renaming_a_payment_app_moves_the_value_stored_on_branches(): void
    {
        $app = PaymentApp::create(['slug' => 'ard', 'name' => 'Ард Апп', 'color' => '#0b63ce']);
        $branch = Branch::factory()->create(['payments' => ['Ард Апп', 'QPay']]);

        $this->actingAs($this->admin())
            ->putJson("/api/v1/admin/payment-apps/{$app->id}", ['name' => 'Ard App'])
            ->assertOk();

        $this->assertSame(['Ard App', 'QPay'], $branch->fresh()->payments);

        $this->getJson('/api/v1/search?payment='.urlencode('Ard App'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_admin_can_upload_and_remove_a_logo(): void
    {
        Storage::fake('public');

        $app = PaymentApp::create(['slug' => 'toki', 'name' => 'Toki', 'color' => '#111827']);

        $this->actingAs($this->admin())
            ->postJson("/api/v1/admin/payment-apps/{$app->id}/logo", [
                'logo' => UploadedFile::fake()->image('toki.png', 64, 64),
            ])
            ->assertOk();

        $stored = $app->fresh()->logo_path;
        $this->assertNotNull($stored);
        Storage::disk('public')->assertExists($stored);

        $logo = collect($this->getJson('/api/v1/payments')->json('data'))->firstWhere('slug', 'toki')['logo'];
        $this->assertNotNull($logo);

        $this->actingAs($this->admin())
            ->deleteJson("/api/v1/admin/payment-apps/{$app->id}/logo")
            ->assertOk();

        $this->assertNull($app->fresh()->logo_path);
        Storage::disk('public')->assertMissing($stored);
    }

    public function test_a_regular_user_cannot_manage_the_catalog(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->postJson('/api/v1/admin/amenities', ['name' => 'Х', 'icon' => 'zap'])->assertForbidden();
        $this->actingAs($user)->getJson('/api/v1/admin/payment-apps')->assertForbidden();
    }
}
