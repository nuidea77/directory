<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Category;
use App\Models\Message;
use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_wizard_creates_organization_business_and_branch(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/console/organizations', [
            'organization_name' => 'Тест ХХК',
            'registration_number' => '1234567',
            'business_name' => 'Тест Сервис',
            'category_id' => $category->id,
            'price_level' => '₮₮',
        ]);

        $response->assertCreated();
        $businessId = $response->json('business.id');

        $branch = $this->actingAs($user)->postJson("/api/v1/console/businesses/{$businessId}/branches", [
            'name' => 'Баянгол салбар',
            'district' => 'Баянгол',
            'address' => 'Тестийн гудамж 1',
            'phone' => '99881122',
        ]);

        // Шинэ салбар редакцын хяналтад орно
        $branch->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->assertTrue((bool) $branch->json('data.is_main'));
    }

    public function test_free_plan_blocks_second_branch(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['owner_id' => $user->id, 'plan' => 'free']);
        $business = Business::factory()->create(['organization_id' => $organization->id]);
        Branch::factory()->create(['business_id' => $business->id]);

        $this->actingAs($user)->postJson("/api/v1/console/businesses/{$business->id}/branches", [
            'name' => 'Хоёр дахь',
            'district' => 'Сүхбаатар',
            'address' => 'X',
            'phone' => '99881123',
        ])->assertStatus(422);
    }

    public function test_standard_plan_allows_many_branches(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->onPlan('standard')->create(['owner_id' => $user->id]);
        $business = Business::factory()->create(['organization_id' => $organization->id]);
        Branch::factory()->count(3)->create(['business_id' => $business->id]);

        $this->actingAs($user)->postJson("/api/v1/console/businesses/{$business->id}/branches", [
            'name' => 'Дөрөв дэх',
            'district' => 'Сүхбаатар',
            'address' => 'X',
            'phone' => '99881123',
        ])->assertCreated();
    }

    public function test_address_change_sends_branch_back_to_review(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['owner_id' => $user->id]);
        $business = Business::factory()->create(['organization_id' => $organization->id]);
        $branch = Branch::factory()->create(['business_id' => $business->id, 'status' => 'active']);

        $this->actingAs($user)->putJson("/api/v1/console/branches/{$branch->id}", [
            'address' => 'Шинэ хаяг 99',
        ])->assertOk()->assertJsonPath('data.status', 'pending');
    }

    public function test_stranger_cannot_manage_branch(): void
    {
        $branch = Branch::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->putJson("/api/v1/console/branches/{$branch->id}", ['address' => 'X'])->assertForbidden();
        $this->actingAs($stranger)->deleteJson("/api/v1/console/branches/{$branch->id}")->assertForbidden();
    }

    public function test_owner_replies_to_review(): void
    {
        $branch = Branch::factory()->create();
        $owner = $branch->business->organization->owner;
        $review = Review::factory()->create(['branch_id' => $branch->id]);

        $this->actingAs($owner)->postJson("/api/v1/console/reviews/{$review->id}/reply", ['reply' => 'Баярлалаа!'])
            ->assertOk()
            ->assertJsonPath('data.reply', 'Баярлалаа!');
    }

    public function test_message_thread_flow(): void
    {
        $branch = Branch::factory()->create();
        $business = $branch->business;
        $owner = $business->organization->owner;
        $customer = User::factory()->create();

        // Хэрэглэгч зурвас илгээнэ
        $this->actingAs($customer)->postJson("/api/v1/businesses/{$business->id}/messages", ['body' => 'Сайн байна уу?'])
            ->assertCreated();

        // Эзэн inbox-доо харна, хариулна
        $threads = $this->actingAs($owner)->getJson("/api/v1/console/businesses/{$business->id}/messages");
        $threads->assertOk();
        $this->assertCount(1, $threads->json('data'));
        $this->assertSame(1, $threads->json('data.0.unread'));

        $this->actingAs($owner)->postJson("/api/v1/console/businesses/{$business->id}/messages/{$customer->id}", ['body' => 'Сайн байна уу! Тавтай морил.'])
            ->assertCreated();

        // Хэрэглэгч харилцан яриагаа харна
        $conversation = $this->actingAs($customer)->getJson("/api/v1/businesses/{$business->id}/messages");
        $this->assertCount(2, $conversation->json('data'));
    }

    public function test_admin_businesses_list_filters_by_plan_pending_and_location(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Бизнес эрхтэй, Хөвсгөл·Мөрөнд салбартай, сурталчилгаа дараалалд
        $orgPaid = Organization::factory()->create(['plan' => 'business', 'plan_expires_at' => now()->addYear()]);
        $paid = Business::factory()->create(['organization_id' => $orgPaid->id, 'name' => 'Хөвсгөл тур']);
        Branch::factory()->create(['business_id' => $paid->id, 'city' => 'Хөвсгөл', 'district' => 'Мөрөн', 'status' => 'active']);
        \App\Models\Campaign::factory()->create(['organization_id' => $orgPaid->id, 'business_id' => $paid->id, 'status' => 'queued']);

        // Үнэгүй эрхтэй, УБ·Баянзүрхэд модерац хүлээж буй салбартай, эрхийн төлбөр хүлээж буй
        $orgFree = Organization::factory()->create(['plan' => 'free']);
        $free = Business::factory()->create(['organization_id' => $orgFree->id, 'name' => 'УБ дэлгүүр']);
        Branch::factory()->create(['business_id' => $free->id, 'city' => 'Улаанбаатар', 'district' => 'Баянзүрх', 'status' => 'pending']);
        \App\Models\Order::factory()->create(['organization_id' => $orgFree->id, 'status' => 'pending']);

        $get = fn (string $query) => $this->actingAs($admin)->getJson('/api/v1/admin/businesses?'.$query)->assertOk();

        // Эрхийн төрлөөр
        $get('plan=business')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Хөвсгөл тур')->assertJsonPath('data.0.plan', 'business');
        $get('plan=free')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'УБ дэлгүүр');

        // Хүлээгдэж буй: сурталчилгаа / эрхийн төлбөр / модерац
        $get('pending=ad')->assertJsonCount(1, 'data')->assertJsonPath('data.0.pending_ads_count', 1);
        $get('pending=plan_order')->assertJsonCount(1, 'data')->assertJsonPath('data.0.pending_orders_count', 1);
        $get('pending=moderation')->assertJsonCount(1, 'data')->assertJsonPath('data.0.pending_branches_count', 1);

        // Байршлаар: аймаг + сум / нийслэл
        $get('city='.urlencode('Хөвсгөл').'&district='.urlencode('Мөрөн'))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.locations.0', 'Хөвсгөл · Мөрөн');
        $get('city='.urlencode('Улаанбаатар'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'УБ дэлгүүр');

        // Ангиллаар
        $get('category_id='.$paid->category_id)->assertJsonPath('data.0.name', 'Хөвсгөл тур');
    }

    public function test_locations_endpoint_lists_cities_and_districts(): void
    {
        $this->getJson('/api/v1/locations')
            ->assertOk()
            ->assertJsonPath('data.0.city', 'Улаанбаатар')
            ->assertJsonCount(22, 'data');

        $districts = collect($this->getJson('/api/v1/locations')->json('data'))
            ->firstWhere('city', 'Улаанбаатар')['districts'];

        $this->assertContains('Баянзүрх', $districts);
        $this->assertCount(9, $districts);
    }

    public function test_image_upload_creates_thumbnail_and_cover_selection(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $branch = Branch::factory()->create();
        // Үнэгүй эрх 1 зурагтай тул стандарт эрх өгнө
        $branch->business->organization->update(['plan' => 'standard', 'plan_expires_at' => now()->addYear()]);
        $owner = $branch->business->organization->owner;

        $res = $this->actingAs($owner)->post("/api/v1/console/branches/{$branch->id}/images", [
            'images' => [
                \Illuminate\Http\UploadedFile::fake()->image('a.jpg', 1600, 900),
                \Illuminate\Http\UploadedFile::fake()->image('b.jpg', 400, 300),
            ],
        ], ['Accept' => 'application/json']);

        $res->assertOk();
        $images = $branch->images()->get();
        $this->assertCount(2, $images);
        $this->assertNotNull($images[0]->thumb_path); // жижигрүүлсэн хувилбар үүссэн
        $this->assertTrue((bool) $images[0]->is_cover);

        // Хоёр дахь зургийг нүүр болгоно
        $this->actingAs($owner)->postJson("/api/v1/console/branches/{$branch->id}/images/{$images[1]->id}/cover")->assertOk();
        $this->assertTrue((bool) $images[1]->refresh()->is_cover);
        $this->assertFalse((bool) $images[0]->refresh()->is_cover);

        // Салбар устгахад файлууд дискнээс устана
        $paths = $images->flatMap(fn ($i) => [$i->path, $i->thumb_path])->filter();
        $this->actingAs($owner)->deleteJson("/api/v1/console/branches/{$branch->id}")->assertOk();
        foreach ($paths as $path) {
            \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($path);
        }
    }

    public function test_admin_moderation_flow(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $regular = User::factory()->create();
        $branch = Branch::factory()->pending()->create();

        $this->actingAs($regular)->getJson('/api/v1/admin/moderation')->assertForbidden();

        $this->actingAs($admin)->getJson('/api/v1/admin/moderation')
            ->assertOk()
            ->assertJsonPath('kpis.pending', 1);

        $this->actingAs($admin)->postJson("/api/v1/admin/branches/{$branch->id}/approve")->assertOk();
        $this->assertSame('active', $branch->refresh()->status);
    }

    public function test_notifications_sent_for_moderation_review_and_message(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $branch = Branch::factory()->pending()->create();
        $branch->update(['status' => 'active']);
        $owner = $branch->business->organization->owner;
        $owner->update(['email' => 'owner@example.mn']);
        $visitor = User::factory()->create(['email' => 'visitor@example.mn']);

        // Модерацын шийдвэр
        $this->actingAs($admin)->postJson("/api/v1/admin/branches/{$branch->id}/reject", ['reason' => 'Х'])->assertOk();
        \Illuminate\Support\Facades\Notification::assertSentTo($owner, \App\Notifications\BranchModerated::class);

        $branch->refresh()->update(['status' => 'active']);

        // Шинэ сэтгэгдэл
        $this->actingAs($visitor)->postJson("/api/v1/branches/{$branch->id}/reviews", ['rating' => 5])->assertCreated();
        \Illuminate\Support\Facades\Notification::assertSentTo($owner, \App\Notifications\NewReview::class);

        // Зурвас: хэрэглэгч → эзэн, эзэн → хэрэглэгч
        $business = $branch->business;
        $this->actingAs($visitor)->postJson("/api/v1/businesses/{$business->id}/messages", ['body' => 'Сайн уу'])->assertCreated();
        \Illuminate\Support\Facades\Notification::assertSentTo($owner, \App\Notifications\NewMessage::class);

        $this->actingAs($owner)->postJson("/api/v1/console/businesses/{$business->id}/messages/{$visitor->id}", ['body' => 'Тавтай морил'])->assertCreated();
        \Illuminate\Support\Facades\Notification::assertSentTo($visitor, \App\Notifications\NewMessage::class);

        // Огт бичээгүй хэрэглэгч рүү хариулж болохгүй
        $stranger = User::factory()->create();
        $this->actingAs($owner)->postJson("/api/v1/console/businesses/{$business->id}/messages/{$stranger->id}", ['body' => 'x'])->assertNotFound();
    }

    public function test_rejection_reason_saved_and_visible_to_owner(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $branch = Branch::factory()->pending()->create();

        $this->actingAs($admin)->postJson("/api/v1/admin/branches/{$branch->id}/reject", [
            'reason' => 'Хаяг тодорхойгүй байна',
        ])->assertOk();

        $this->assertSame('rejected', $branch->refresh()->status);
        $this->assertSame('Хаяг тодорхойгүй байна', $branch->rejection_reason);

        // Эзэн нь шалтгааныг харна
        $owner = $branch->business->organization->owner;
        $this->actingAs($owner)->getJson("/api/v1/console/branches/{$branch->id}")
            ->assertOk()
            ->assertJsonPath('data.rejection_reason', 'Хаяг тодорхойгүй байна');

        // Дахин батлахад шалтгаан арилна
        $this->actingAs($admin)->postJson("/api/v1/admin/branches/{$branch->id}/approve")->assertOk();
        $this->assertNull($branch->refresh()->rejection_reason);
    }
}
