<?php

namespace App\Support;

/**
 * Зээл, хэсэгчилсэн төлбөрийн аппуудын жагсаалт.
 *
 * Бодит лого: public/img/payments/{slug}.svg (эсвэл .png/.webp) файл
 * байрлуулбал автоматаар харагдана. Файл байхгүй бол брэндийн өнгөтэй
 * түр тэмдэг гарна.
 */
class Payments
{
    /** Лого хайх өргөтгөлүүд — эрэмбээрээ */
    protected const EXTENSIONS = ['svg', 'png', 'webp'];

    /**
     * @return array<int, array{slug: string, name: string, logo: string|null, wordmark: bool}>
     */
    public static function all(): array
    {
        return array_map(function (array $app) {
            $logo = self::logoUrl($app['slug']);

            return [
                'slug' => $app['slug'],
                'name' => $app['name'],
                'logo' => $logo,
                // Лого нь нэрээ агуулсан wordmark бол хажууд нь нэрийг давхардуулахгүй
                'wordmark' => $logo !== null && ($app['wordmark'] ?? false),
            ];
        }, config('payments', []));
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_column(config('payments', []), 'name');
    }

    public static function logoUrl(string $slug): ?string
    {
        foreach (self::EXTENSIONS as $ext) {
            $relative = "img/payments/{$slug}.{$ext}";

            if (is_file(public_path($relative))) {
                // Лого солигдоход browser cache шинэчлэгдэнэ
                return asset($relative).'?v='.filemtime(public_path($relative));
            }
        }

        return null;
    }
}
