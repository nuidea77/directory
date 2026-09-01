<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Category;
use App\Models\SearchAlias;
use App\Support\SearchText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Хайлт v1: галигийн хөрвүүлэлт, үсгийн алдаа, ярианы нэр (синоним).
 */
class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected Category $health;

    protected Category $dental;

    protected Category $construction;

    protected Category $electrician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->health = Category::factory()->create(['name' => 'Эрүүл мэнд', 'slug' => 'health']);
        $this->dental = Category::factory()->create(['name' => 'Шүдний эмнэлэг', 'slug' => 'health-1', 'parent_id' => $this->health->id]);
        $this->construction = Category::factory()->create(['name' => 'Барилга, засвар', 'slug' => 'construction']);
        $this->electrician = Category::factory()->create(['name' => 'Цахилгаанчин', 'slug' => 'construction-4', 'parent_id' => $this->construction->id]);
    }

    protected function branchFor(Category $category, string $name, string $district, array $attributes = []): Branch
    {
        $business = Business::factory()->create([
            'category_id' => $category->id,
            'name' => $name,
            'subcategory' => $attributes['subcategory'] ?? $category->name,
            'description' => $attributes['description'] ?? 'Тайлбар.',
        ]);

        return Branch::factory()->create([
            'business_id' => $business->id,
            'district' => $district,
            'city' => 'Улаанбаатар',
        ]);
    }

    public function test_cyrillic_latin_and_typo_all_find_the_same_business(): void
    {
        $this->branchFor($this->dental, 'Мишээл Дент', 'Баянзүрх');
        $this->branchFor($this->electrician, 'Гэрэл Электрик', 'Баянгол');

        foreach (['шүдний эмнэлэг', 'shudnii emneleg', 'shudni emneleg', 'shudni emnelg'] as $q) {
            $this->getJson('/api/v1/search?q='.urlencode($q))
                ->assertOk()
                ->assertJsonPath('meta.total', 1)
                ->assertJsonPath('data.0.business.name', 'Мишээл Дент');
        }
    }

    public function test_search_term_can_carry_the_district(): void
    {
        $this->branchFor($this->dental, 'Мишээл Дент', 'Баянзүрх');
        $this->branchFor($this->dental, 'Оюу Дент', 'Хан-Уул');

        $this->getJson('/api/v1/search?q='.urlencode('шүдний эмнэлэг'))
            ->assertJsonPath('meta.total', 2);

        $response = $this->getJson('/api/v1/search?q='.urlencode('shudnii emneleg bayanzurh'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.business.name', 'Мишээл Дент')
            ->assertJsonPath('parsed.district', 'Баянзүрх');

        $this->assertSame(['Шүдний эмнэлэг'], $response->json('parsed.categories'));
    }

    public function test_colloquial_synonym_finds_the_category(): void
    {
        SearchAlias::create(['category_id' => $this->electrician->id, 'term' => 'тог']);
        SearchAlias::create(['category_id' => $this->electrician->id, 'term' => 'тог татах']);

        $this->branchFor($this->electrician, 'Гэрэл Электрик', 'Баянзүрх');
        $this->branchFor($this->dental, 'Мишээл Дент', 'Баянзүрх');

        foreach (['тог', 'tog', 'тог татах'] as $q) {
            $this->getJson('/api/v1/search?q='.urlencode($q))
                ->assertOk()
                ->assertJsonPath('meta.total', 1)
                ->assertJsonPath('data.0.business.name', 'Гэрэл Электрик');
        }
    }

    public function test_business_registered_under_the_parent_category_is_still_found(): void
    {
        // Эзэн нь «Эрүүл мэнд» гэж бүртгүүлээд дэд ангиллаа зөвхөн текстээр бичсэн
        $this->branchFor($this->health, 'Оюу Дент', 'Хан-Уул', ['subcategory' => 'Шүдний эмнэлэг']);

        $this->getJson('/api/v1/search?q='.urlencode('shudnii emneleg'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.business.name', 'Оюу Дент');
    }

    public function test_free_text_still_matches_business_name(): void
    {
        $this->branchFor($this->dental, 'Мишээл Дент', 'Баянзүрх');
        $this->branchFor($this->dental, 'Оюу Дент', 'Хан-Уул');

        $this->getJson('/api/v1/search?q='.urlencode('misheel'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.business.name', 'Мишээл Дент');
    }

    public function test_search_index_is_rebuilt_when_a_business_is_renamed(): void
    {
        $branch = $this->branchFor($this->dental, 'Мишээл Дент', 'Баянзүрх');

        $branch->business->update(['name' => 'Шинэ Дент']);

        $this->getJson('/api/v1/search?q=shine')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/search?q=misheel')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_fold_maps_cyrillic_and_latin_to_the_same_key(): void
    {
        $this->assertSame(SearchText::fold('Шүдний эмнэлэг'), SearchText::fold('shudnii emneleg'));
        $this->assertSame(SearchText::fold('Баянзүрх'), SearchText::fold('bayanzurh'));
        $this->assertSame(SearchText::fold('Цахилгаан'), SearchText::fold('tsahilgaan'));
        $this->assertSame(SearchText::fold('Хан-Уул'), SearchText::fold('khan uul'));
    }
}
