<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $disk = Storage::disk('public');
        $open = $this->openState();

        // rejection_reason нь редакцын дотоод тэмдэглэл — зөвхөн эзний зөвлөл
        // болон админы хариултад. Өмнө нь нийтийн хайлтад ч орж байсан.
        $private = $request->user() !== null
            && ($request->is('api/v1/console/*') || $request->is('api/v1/admin/*'));

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_main' => $this->is_main,
            'city' => $this->city,
            'district' => $this->district,
            'khoroo' => $this->khoroo,
            'address' => $this->address,
            'landmark' => $this->landmark,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'phone' => $this->phone,
            'email' => $this->email,
            'hours' => $this->hours,
            'amenities' => $this->amenities,
            'status' => $this->status,
            'rejection_reason' => $this->when($private, fn () => $this->rejection_reason),
            // Тухайн салбар онцлох зартай эсэх (хайлтын илэрцэд)
            'is_featured' => $this->when(isset($this->is_featured), fn () => (bool) $this->is_featured),
            'is_24_7' => (bool) $this->is_24_7,
            'is_open' => $open['open'],
            'open_label' => $open['label'],
            'rating_avg' => (float) $this->rating_avg,
            'reviews_count' => $this->reviews_count,
            'views_count' => $this->views_count,
            'calls_count' => $this->calls_count,
            'directions_count' => $this->directions_count,
            'completeness' => $this->when(true, fn () => $this->completeness()),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => $disk->url($img->path),
                'thumb_url' => $disk->url($img->thumb_path ?: $img->path),
                'is_cover' => $img->is_cover,
            ])),
            // Жагсаалтад жижигрүүлсэн хувилбарыг өгнө (байхгүй бол оригинал)
            'cover_url' => $this->whenLoaded('images', fn () => $this->images->first() ? $disk->url($this->images->first()->thumb_path ?: $this->images->first()->path) : null),
            'business' => new BusinessResource($this->whenLoaded('business')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'distance_km' => $this->when(isset($this->distance_km), fn () => round($this->distance_km, 1)),
        ];
    }
}
