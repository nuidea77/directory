<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ангилалд тохирсон үйлчилгээ/онцлогийн сан.
 */
class AmenitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_category_returns_the_common_set(): void
    {
        $response = $this->getJson('/api/v1/amenities')->assertOk();

        $names = array_column($response->json('data'), 'name');

        $this->assertContains('Зогсоол', $names);
        $this->assertNotContains('VIP өрөө', $names);
        $this->assertNotNull($response->json('data.0.icon'));
    }

    public function test_category_set_extends_the_common_set(): void
    {
        Category::factory()->create(['name' => 'Зочид буудал', 'slug' => 'hotels']);

        $names = array_column(
            $this->getJson('/api/v1/amenities?category=hotels')->assertOk()->json('data'),
            'name',
        );

        $this->assertContains('Зогсоол', $names);          // common
        $this->assertContains('24 цагийн ресепшн', $names); // hotels
        $this->assertContains('Караоке', $names);
    }

    public function test_child_category_inherits_ancestor_sets(): void
    {
        $root = Category::factory()->create(['name' => 'Үзвэр, амралт', 'slug' => 'entertainment']);
        $pc = Category::factory()->create(['name' => 'PC тоглоомын газар', 'slug' => 'entertainment-6', 'parent_id' => $root->id]);
        Category::factory()->create(['name' => 'Киберспорт клуб', 'slug' => 'entertainment-6-1', 'parent_id' => $pc->id]);

        $names = array_column(
            $this->getJson('/api/v1/amenities?category=entertainment-6-1')->assertOk()->json('data'),
            'name',
        );

        $this->assertContains('Зогсоол', $names);                  // common
        $this->assertContains('VIP өрөө', $names);                 // entertainment (root)
        $this->assertContains('Буфет', $names);                    // entertainment (root)
        $this->assertContains('Өндөр хүчин чадлын тоног', $names); // entertainment-6 (эцэг)
    }

    public function test_billiard_has_its_own_table_and_rental_options(): void
    {
        $root = Category::factory()->create(['name' => 'Үзвэр, амралт', 'slug' => 'entertainment']);
        Category::factory()->create(['name' => 'Билльярд', 'slug' => 'entertainment-3', 'parent_id' => $root->id]);

        $data = $this->getJson('/api/v1/amenities?category=entertainment-3')->assertOk()->json('data');
        $names = array_column($data, 'name');

        $this->assertContains('Зогсоол', $names);                  // common
        $this->assertContains('Буфет', $names);                    // Үзвэр, амралт
        $this->assertContains('Пул ширээ', $names);                // Билльярд
        $this->assertContains('Снукер ширээ', $names);
        $this->assertContains('Цагаар түрээслэнэ', $names);
        $this->assertNotContains('Консол тоглоом', $names);        // PC тоглоомынх орохгүй

        // Icon бүр JS map-д бүртгэлтэй байх ёстой
        $icons = array_column($data, 'icon');
        $registered = file_get_contents(base_path('resources/js/data/amenityIcons.js'));

        foreach ($icons as $icon) {
            $this->assertMatchesRegularExpression("/[ ']".preg_quote($icon, '/')."'?:/", $registered, "«{$icon}» icon бүртгэлгүй байна.");
        }
    }

    public function test_unknown_category_slug_falls_back_to_common(): void
    {
        $this->getJson('/api/v1/amenities?category=baihgui-slug')
            ->assertOk()
            ->assertJsonPath('category', null);
    }

    public function test_locations_endpoint_keeps_the_flat_amenity_names(): void
    {
        $amenities = $this->getJson('/api/v1/locations')->assertOk()->json('amenities');

        $this->assertIsArray($amenities);
        $this->assertContains('Зогсоол', $amenities);
        $this->assertIsString($amenities[0]);
    }
}
