<?php

namespace App\Support;

use App\Models\PaymentApp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Зээл, хэсэгчилсэн төлбөрийн аппуудын жагсаалт (payment_apps хүснэгт).
 *
 * Лого: админаас байршуулсан файл (logo_path) эсвэл
 * public/img/payments/{slug}.svg — эхнийх нь давуу эрхтэй.
 */
class Payments
{
    /** Лого хайх өргөтгөлүүд — эрэмбээрээ */
    protected const EXTENSIONS = ['svg', 'png', 'webp'];

    /**
     * @return array<int, array{slug: string, name: string, color: string, logo: string|null, wordmark: bool}>
     */
    public static function all(): array
    {
        return Cache::remember('payments:list:v1', 600, function () {
            return PaymentApp::active()->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (PaymentApp $app) => self::present($app))
                ->all();
        });
    }

    /**
     * @return array{slug: string, name: string, color: string, logo: string|null, wordmark: bool}
     */
    public static function present(PaymentApp $app): array
    {
        $logo = self::logoUrl($app);

        return [
            'slug' => $app->slug,
            'name' => $app->name,
            'color' => $app->color,
            'logo' => $logo,
            // Лого нь нэрээ агуулсан wordmark бол хажууд нь нэрийг давхардуулахгүй
            'wordmark' => $logo !== null && $app->wordmark,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_column(self::all(), 'name');
    }

    public static function logoUrl(PaymentApp $app): ?string
    {
        // 1) Админаас байршуулсан
        if ($app->logo_path && Storage::disk('public')->exists($app->logo_path)) {
            return Storage::disk('public')->url($app->logo_path)
                .'?v='.Storage::disk('public')->lastModified($app->logo_path);
        }

        // 2) Репод шууд тавьсан файл
        foreach (self::EXTENSIONS as $ext) {
            $relative = "img/payments/{$app->slug}.{$ext}";

            if (is_file(public_path($relative))) {
                return asset($relative).'?v='.filemtime(public_path($relative));
            }
        }

        return null;
    }
}
