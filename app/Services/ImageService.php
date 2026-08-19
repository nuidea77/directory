<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * GD-ээр жагсаалтад зориулсан жижигрүүлсэн хувилбар үүсгэнэ
 * (оригинал 4MB зургийг хайлтын хуудсанд шууд өгөхгүй).
 */
class ImageService
{
    public const CARD_WIDTH = 800;

    /**
     * @return string|null үүссэн thumb-ийн зам (public диск), бүтэлгүйтвэл null
     */
    public function makeCard(UploadedFile $file, string $dir = 'branches/thumbs'): ?string
    {
        $data = @file_get_contents($file->getRealPath());

        if ($data === false) {
            return null;
        }

        $src = @imagecreatefromstring($data);

        if ($src === false) {
            return null;
        }

        $width = imagesx($src);
        $height = imagesy($src);

        if ($width > self::CARD_WIDTH) {
            $scaled = imagescale($src, self::CARD_WIDTH, (int) round($height * self::CARD_WIDTH / $width), IMG_BICUBIC);
            imagedestroy($src);

            if ($scaled === false) {
                return null;
            }

            $src = $scaled;
        }

        $path = $dir.'/'.Str::random(40).'.jpg';

        ob_start();
        imageinterlace($src, true);
        imagejpeg($src, null, 80);
        $jpeg = ob_get_clean();
        imagedestroy($src);

        if ($jpeg === false || $jpeg === '') {
            return null;
        }

        Storage::disk('public')->put($path, $jpeg);

        return $path;
    }
}
