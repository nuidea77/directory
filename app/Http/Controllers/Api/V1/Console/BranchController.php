<?php

namespace App\Http\Controllers\Api\V1\Console;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Http\Resources\VerificationResource;
use App\Models\Branch;
use App\Models\BranchImage;
use App\Models\Business;
use App\Services\VerifyMn\PhoneVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BranchController extends Controller
{
    public function store(Request $request, Business $business): BranchResource
    {
        $this->authorizeOwner($request, $business);

        $organization = $business->organization;
        $branchLimit = $organization->planLimit('branches'); // 0 = хязгааргүй

        if ($branchLimit > 0 && $business->branches()->count() >= $branchLimit) {
            throw ValidationException::withMessages([
                'name' => 'Үнэгүй эрхэд салбар нэмэх боломжгүй. Стандарт эсвэл Бизнес эрх авна уу.',
            ]);
        }

        $data = $this->validateBranch($request);

        $branch = $business->branches()->create([
            ...$data,
            'slug' => $this->uniqueSlug($business->name.' '.($data['name'] ?? '')),
            'is_main' => $business->branches()->count() === 0,
            'status' => 'pending', // редакцын хяналтад
        ]);

        return new BranchResource($branch->load('images'));
    }

    public function show(Request $request, Branch $branch): BranchResource
    {
        $this->authorizeOwner($request, $branch->business);

        return new BranchResource($branch->load(['images', 'business.category']));
    }

    public function update(Request $request, Branch $branch): BranchResource
    {
        $this->authorizeOwner($request, $branch->business);

        $data = $this->validateBranch($request, updating: true);

        // Хаяг өөрчлөгдвөл редакцын хяналт дахин шаардана
        $needsReview = isset($data['address']) && $data['address'] !== $branch->address;

        // Утас өөрчлөгдвөл дахин баталгаажуулна
        if (isset($data['phone']) && $data['phone'] !== $branch->phone) {
            $data['phone_verified'] = false;
        }

        $branch->update($data);

        if ($needsReview && $branch->status === 'active') {
            $branch->update(['status' => 'pending']);
        }

        return new BranchResource($branch->refresh()->load('images'));
    }

    public function destroy(Request $request, Branch $branch): JsonResponse
    {
        $this->authorizeOwner($request, $branch->business);

        Storage::disk('public')->delete($branch->images->pluck('path')->all());
        $branch->delete();

        return response()->json(['message' => 'Салбар устгагдлаа.']);
    }

    public function addImages(Request $request, Branch $branch): BranchResource
    {
        $this->authorizeOwner($request, $branch->business);

        $request->validate([
            'images' => ['required', 'array', 'max:10'],
            'images.*' => ['image', 'max:4096'],
        ]);

        $limit = $branch->business->organization->planLimit('images_per_branch');
        $existing = $branch->images()->count();

        if ($existing + count($request->file('images', [])) > $limit) {
            throw ValidationException::withMessages([
                'images' => "Таны эрхийн бичигт салбар бүрт {$limit} зураг оруулах боломжтой.",
            ]);
        }

        foreach ($request->file('images', []) as $i => $file) {
            $branch->images()->create([
                'path' => $file->store('branches', 'public'),
                'is_cover' => $existing === 0 && $i === 0,
                'sort_order' => $existing + $i,
            ]);
        }

        return new BranchResource($branch->load('images'));
    }

    public function deleteImage(Request $request, Branch $branch, BranchImage $image): JsonResponse
    {
        $this->authorizeOwner($request, $branch->business);
        abort_unless($image->branch_id === $branch->id, 404);

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return response()->json(['message' => 'Зураг устгагдлаа.']);
    }

    /**
     * Салбарын утсыг verify.mn-ээр баталгаажуулах session эхлүүлэх.
     */
    public function verifyPhone(Request $request, Branch $branch, PhoneVerificationService $verifications): JsonResponse
    {
        $this->authorizeOwner($request, $branch->business);

        $verification = $verifications->start($branch->phone, 'branch_phone', ['branch_id' => $branch->id]);

        return response()->json(['verification' => new VerificationResource($verification)]);
    }

    protected function validateBranch(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
            'district' => [$required, 'string', 'max:80'],
            'khoroo' => ['nullable', 'string', 'max:80'],
            'address' => [$required, 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:190'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'phone' => [$required, 'string', 'regex:/^[0-9]{6,11}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'hours' => ['nullable', 'array'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string', 'max:50'],
        ]);
    }

    protected function authorizeOwner(Request $request, Business $business): void
    {
        abort_unless($business->organization->owner_id === $request->user()->id, 403, 'Энэ бизнесийг удирдах эрх байхгүй.');
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: Str::lower(Str::random(8));
        $slug = $base;
        $n = 1;

        while (Branch::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }
}
