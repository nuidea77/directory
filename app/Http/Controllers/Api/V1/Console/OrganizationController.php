<?php

namespace App\Http\Controllers\Api\V1\Console;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessResource;
use App\Http\Resources\OrganizationResource;
use App\Models\Business;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Бизнес зөвлөл: байгууллага, бизнес удирдлага.
 */
class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organizations = $request->user()->organizations()
            ->with(['businesses.category', 'businesses.branches.images'])
            ->get();

        return response()->json(['data' => OrganizationResource::collection($organizations)]);
    }

    /**
     * Бизнес бүртгэх 1-р шат: байгууллага + бизнес хамт үүсгэнэ.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'organization_name' => ['required', 'string', 'max:150'],
            'registration_number' => ['nullable', 'string', 'max:20'],
            'business_name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'subcategory' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'string', 'max:190'],
            'facebook' => ['nullable', 'string', 'max:190'],
            'instagram' => ['nullable', 'string', 'max:190'],
            'price_level' => ['nullable', 'in:₮,₮₮,₮₮₮'],
        ]);

        $organization = $request->user()->organizations()->firstOrCreate(
            ['name' => $data['organization_name']],
            ['registration_number' => $data['registration_number'] ?? null, 'plan' => 'free'],
        );

        if ($organization->businesses()->count() >= $organization->planLimit('businesses')) {
            throw ValidationException::withMessages([
                'business_name' => 'Таны эрхийн бичигт бизнесийн тоо хүрсэн байна. Эрхээ ахиулна уу.',
            ]);
        }

        $business = $organization->businesses()->create([
            'category_id' => $data['category_id'],
            'subcategory' => $data['subcategory'] ?? null,
            'name' => $data['business_name'],
            'slug' => $this->uniqueSlug(Business::class, $data['business_name']),
            'description' => $data['description'] ?? null,
            'website' => $data['website'] ?? null,
            'facebook' => $data['facebook'] ?? null,
            'instagram' => $data['instagram'] ?? null,
            'price_level' => $data['price_level'] ?? null,
            // Бизнес эрх идэвхтэй байгууллагын шинэ бизнес шууд ✓ тэмдэгтэй
            'is_verified' => ! empty($organization->planConfig()['verified_badge']),
        ]);

        if ($request->hasFile('logo')) {
            $request->validate(['logo' => ['image', 'max:2048']]);
            $business->update(['logo_path' => $request->file('logo')->store('logos', 'public')]);
        }

        return response()->json([
            'organization' => new OrganizationResource($organization->load('businesses.category')),
            'business' => new BusinessResource($business->load('category')),
        ], 201);
    }

    public function updateBusiness(Request $request, Business $business): BusinessResource
    {
        $this->authorizeOwner($request, $business->organization);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'subcategory' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'string', 'max:190'],
            'facebook' => ['nullable', 'string', 'max:190'],
            'instagram' => ['nullable', 'string', 'max:190'],
            'price_level' => ['nullable', 'in:₮,₮₮,₮₮₮'],
        ]);

        if (isset($data['name']) && $data['name'] !== $business->name) {
            $data['slug'] = $this->uniqueSlug(Business::class, $data['name'], $business->id);
        }

        $business->update($data);

        if ($request->hasFile('logo')) {
            $request->validate(['logo' => ['image', 'max:2048']]);
            $business->update(['logo_path' => $request->file('logo')->store('logos', 'public')]);
        }

        return new BusinessResource($business->refresh()->load(['category', 'branches.images']));
    }

    public function destroyBusiness(Request $request, Business $business): JsonResponse
    {
        $this->authorizeOwner($request, $business->organization);

        $business->delete();

        return response()->json(['message' => 'Бизнес устгагдлаа.']);
    }

    public function updateOrganization(Request $request, Organization $organization): OrganizationResource
    {
        $this->authorizeOwner($request, $organization);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'registration_number' => ['nullable', 'string', 'max:20'],
            'auto_renew' => ['sometimes', 'boolean'],
        ]);

        $organization->update($data);

        return new OrganizationResource($organization->refresh()->load('businesses.category'));
    }

    protected function authorizeOwner(Request $request, Organization $organization): void
    {
        abort_unless($organization->owner_id === $request->user()->id, 403, 'Энэ байгууллагыг удирдах эрх байхгүй.');
    }

    protected function uniqueSlug(string $model, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: Str::lower(Str::random(8));
        $slug = $base;
        $n = 1;

        while ($model::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }
}
